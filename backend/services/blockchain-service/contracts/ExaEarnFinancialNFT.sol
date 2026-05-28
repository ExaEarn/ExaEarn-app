// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import {AccessControl} from "@openzeppelin/contracts/access/AccessControl.sol";
import {ERC721} from "@openzeppelin/contracts/token/ERC721/ERC721.sol";
import {ERC721URIStorage} from "@openzeppelin/contracts/token/ERC721/extensions/ERC721URIStorage.sol";
import {ReentrancyGuard} from "@openzeppelin/contracts/utils/ReentrancyGuard.sol";

contract ExaEarnFinancialNFT is ERC721URIStorage, AccessControl, ReentrancyGuard {
    bytes32 public constant OPERATOR_ROLE = keccak256("OPERATOR_ROLE");

    struct Listing {
        uint256 id;
        uint256 tokenId;
        address seller;
        uint256 price;
        bool active;
    }

    struct Auction {
        uint256 id;
        uint256 tokenId;
        address seller;
        uint256 startingPrice;
        uint256 reservePrice;
        uint256 highestBid;
        address highestBidder;
        uint256 endsAt;
        bool settled;
    }

    struct FinancialProfile {
        string utilityType;
        string tier;
        uint256 level;
        address creator;
        uint256 cumulativeRevenue;
    }

    string private _baseTokenName;
    string private _baseTokenSymbol;
    address public treasury;
    uint96 public platformFeeBps;
    uint256 public nextTokenId = 1;
    uint256 public nextListingId = 1;
    uint256 public nextAuctionId = 1;

    mapping(uint256 => FinancialProfile) public profiles;
    mapping(uint256 => Listing) public listings;
    mapping(uint256 => Auction) public auctions;

    event NFTMinted(uint256 indexed tokenId, address indexed owner, string utilityType, string tier, string metadataURI, uint256 mintFee);
    event NFTListed(uint256 indexed listingId, uint256 indexed tokenId, address indexed seller, uint256 price);
    event NFTSold(uint256 indexed listingId, uint256 indexed tokenId, address indexed buyer, address seller, uint256 price, uint256 platformFee);
    event BidPlaced(uint256 indexed auctionId, uint256 indexed tokenId, address indexed bidder, uint256 amount);
    event AuctionEnded(uint256 indexed auctionId, uint256 indexed tokenId, address indexed winner, uint256 amount, uint256 platformFee);
    event NFTUpgraded(uint256 indexed tokenId, string tier, uint256 level, uint256 upgradeFee);

    constructor(address admin, address treasury_, uint96 platformFeeBps_) ERC721("ExaEarn Financial NFT", "EXAFN") {
        require(admin != address(0), "admin required");
        require(treasury_ != address(0), "treasury required");
        require(platformFeeBps_ <= 2000, "fee too high");

        treasury = treasury_;
        platformFeeBps = platformFeeBps_;
        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(OPERATOR_ROLE, admin);
    }

    function mintFinancialNft(
        address to,
        string calldata utilityType,
        string calldata tier,
        string calldata metadataURI,
        uint256 mintFee
    ) external onlyRole(OPERATOR_ROLE) returns (uint256 tokenId) {
        require(to != address(0), "invalid owner");

        tokenId = nextTokenId++;
        _safeMint(to, tokenId);
        _setTokenURI(tokenId, metadataURI);

        profiles[tokenId] = FinancialProfile({
            utilityType: utilityType,
            tier: tier,
            level: 1,
            creator: to,
            cumulativeRevenue: mintFee
        });

        emit NFTMinted(tokenId, to, utilityType, tier, metadataURI, mintFee);
    }

    function upgradeFinancialNft(uint256 tokenId, string calldata tier, uint256 level, uint256 upgradeFee) external {
        require(_isAuthorized(_ownerOf(tokenId), msg.sender, tokenId) || hasRole(OPERATOR_ROLE, msg.sender), "not authorized");
        require(level >= profiles[tokenId].level, "invalid level");

        profiles[tokenId].tier = tier;
        profiles[tokenId].level = level;
        profiles[tokenId].cumulativeRevenue += upgradeFee;

        emit NFTUpgraded(tokenId, tier, level, upgradeFee);
    }

    function createListing(uint256 tokenId, uint256 price) external returns (uint256 listingId) {
        address owner = ownerOf(tokenId);
        require(owner == msg.sender || hasRole(OPERATOR_ROLE, msg.sender), "not owner");
        require(price > 0, "price required");

        listingId = nextListingId++;
        listings[listingId] = Listing({
            id: listingId,
            tokenId: tokenId,
            seller: owner,
            price: price,
            active: true
        });

        emit NFTListed(listingId, tokenId, owner, price);
    }

    function buyListing(uint256 listingId, address buyer) external onlyRole(OPERATOR_ROLE) returns (uint256 platformFee) {
        Listing storage listing = listings[listingId];
        require(listing.active, "listing inactive");
        require(buyer != address(0), "buyer required");

        listing.active = false;
        platformFee = (listing.price * platformFeeBps) / 10_000;
        profiles[listing.tokenId].cumulativeRevenue += listing.price;
        _transfer(listing.seller, buyer, listing.tokenId);

        emit NFTSold(listingId, listing.tokenId, buyer, listing.seller, listing.price, platformFee);
    }

    function createAuction(uint256 tokenId, uint256 startingPrice, uint256 reservePrice, uint256 endsAt) external returns (uint256 auctionId) {
        address owner = ownerOf(tokenId);
        require(owner == msg.sender || hasRole(OPERATOR_ROLE, msg.sender), "not owner");
        require(startingPrice > 0, "starting price required");
        require(endsAt > block.timestamp, "invalid end time");

        auctionId = nextAuctionId++;
        auctions[auctionId] = Auction({
            id: auctionId,
            tokenId: tokenId,
            seller: owner,
            startingPrice: startingPrice,
            reservePrice: reservePrice,
            highestBid: 0,
            highestBidder: address(0),
            endsAt: endsAt,
            settled: false
        });
    }

    function placeBid(uint256 auctionId, address bidder, uint256 amount) external onlyRole(OPERATOR_ROLE) {
        Auction storage auction = auctions[auctionId];
        require(!auction.settled, "auction settled");
        require(block.timestamp < auction.endsAt, "auction ended");
        require(bidder != address(0), "bidder required");
        require(amount >= auction.startingPrice, "below starting price");
        require(amount > auction.highestBid, "bid too low");

        auction.highestBid = amount;
        auction.highestBidder = bidder;

        emit BidPlaced(auctionId, auction.tokenId, bidder, amount);
    }

    function finalizeAuction(uint256 auctionId) external onlyRole(OPERATOR_ROLE) returns (uint256 platformFee) {
        Auction storage auction = auctions[auctionId];
        require(!auction.settled, "auction settled");
        require(block.timestamp >= auction.endsAt, "auction still active");

        auction.settled = true;

        if (auction.highestBidder != address(0) && auction.highestBid >= auction.reservePrice) {
            platformFee = (auction.highestBid * platformFeeBps) / 10_000;
            profiles[auction.tokenId].cumulativeRevenue += auction.highestBid;
            _transfer(auction.seller, auction.highestBidder, auction.tokenId);
        }

        emit AuctionEnded(auctionId, auction.tokenId, auction.highestBidder, auction.highestBid, platformFee);
    }

    function setTreasury(address treasury_) external onlyRole(DEFAULT_ADMIN_ROLE) {
        require(treasury_ != address(0), "invalid treasury");
        treasury = treasury_;
    }

    function setPlatformFeeBps(uint96 feeBps) external onlyRole(DEFAULT_ADMIN_ROLE) {
        require(feeBps <= 2000, "fee too high");
        platformFeeBps = feeBps;
    }

    function supportsInterface(bytes4 interfaceId)
        public
        view
        override(ERC721URIStorage, AccessControl)
        returns (bool)
    {
        return super.supportsInterface(interfaceId);
    }
}
