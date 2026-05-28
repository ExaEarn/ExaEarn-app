const { ethers } = require('ethers');
const config = require('../config');
const blockchain = require('./blockchain');
const contractInteractionService = require('./contractInteractionService');
const webhookNotifier = require('./webhookNotifier');
const logger = require('../utils/logger');

const NFT_ABI = [
  'function nextTokenId() view returns (uint256)',
  'function nextListingId() view returns (uint256)',
  'function nextAuctionId() view returns (uint256)',
  'function ownerOf(uint256 tokenId) view returns (address)',
  'function listings(uint256 listingId) view returns (uint256 id,uint256 tokenId,address seller,uint256 price,bool active)',
  'function auctions(uint256 auctionId) view returns (uint256 id,uint256 tokenId,address seller,uint256 startingPrice,uint256 reservePrice,uint256 highestBid,address highestBidder,uint256 endsAt,bool settled)',
  'function profiles(uint256 tokenId) view returns (string utilityType,string tier,uint256 level,address creator,uint256 cumulativeRevenue)',
  'function mintFinancialNft(address to,string utilityType,string tier,string metadataURI,uint256 mintFee) returns (uint256)',
  'function upgradeFinancialNft(uint256 tokenId,string tier,uint256 level,uint256 upgradeFee)',
  'function createListing(uint256 tokenId,uint256 price) returns (uint256)',
  'function buyListing(uint256 listingId,address buyer) returns (uint256)',
  'function createAuction(uint256 tokenId,uint256 startingPrice,uint256 reservePrice,uint256 endsAt) returns (uint256)',
  'function placeBid(uint256 auctionId,address bidder,uint256 amount)',
  'function finalizeAuction(uint256 auctionId) returns (uint256)',
  'event NFTMinted(uint256 indexed tokenId,address indexed owner,string utilityType,string tier,string metadataURI,uint256 mintFee)',
  'event NFTListed(uint256 indexed listingId,uint256 indexed tokenId,address indexed seller,uint256 price)',
  'event NFTSold(uint256 indexed listingId,uint256 indexed tokenId,address indexed buyer,address seller,uint256 price,uint256 platformFee)',
  'event BidPlaced(uint256 indexed auctionId,uint256 indexed tokenId,address indexed bidder,uint256 amount)',
  'event AuctionEnded(uint256 indexed auctionId,uint256 indexed tokenId,address indexed winner,uint256 amount,uint256 platformFee)',
  'event NFTUpgraded(uint256 indexed tokenId,string tier,uint256 level,uint256 upgradeFee)',
];

function requireContract() {
  const address = config.contracts.financialNft || config.contracts.nftMarketplace;
  if (!address) {
    throw new Error('Financial NFT contract not configured');
  }
  return address;
}

function toExaUnits(value) {
  return ethers.parseUnits(String(value ?? '0'), 18).toString();
}

class FinancialNftContractService {
  constructor() {
    this.lastProcessedBlock = 0;
    this.pollTimer = null;
  }

  getAddress() {
    return requireContract();
  }

  getContract() {
    return new ethers.Contract(this.getAddress(), NFT_ABI, blockchain.getEthersProvider('base'));
  }

  async mintUtilityNft(payload) {
    const address = this.getAddress();
    const nextTokenId = await contractInteractionService.read({ address, abi: NFT_ABI, method: 'nextTokenId', args: [] });
    const result = await contractInteractionService.write({
      address,
      abi: NFT_ABI,
      method: 'mintFinancialNft',
      args: [
        String(payload.wallet_address),
        String(payload.utility_type),
        String(payload.tier || 'standard'),
        String(payload.metadata_url),
        toExaUnits(payload.mint_fee_exa || '0'),
      ],
    });

    return {
      ...result,
      token_id: Number(nextTokenId),
      contract_address: address,
      metadata_url: String(payload.metadata_url),
    };
  }

  async verifyOwnership(payload) {
    const address = this.getAddress();
    const owner = await contractInteractionService.read({
      address,
      abi: NFT_ABI,
      method: 'ownerOf',
      args: [Number(payload.token_id)],
    });

    const expected = String(payload.wallet_address || payload.owner_wallet || '').toLowerCase();
    const actual = String(owner).toLowerCase();

    return {
      verified: expected === actual,
      owner_wallet: owner,
      token_id: Number(payload.token_id),
      contract_address: address,
    };
  }

  async createListing(payload) {
    const address = this.getAddress();
    const nextListingId = await contractInteractionService.read({ address, abi: NFT_ABI, method: 'nextListingId', args: [] });
    const result = await contractInteractionService.write({
      address,
      abi: NFT_ABI,
      method: 'createListing',
      args: [Number(payload.token_id), toExaUnits(payload.price_exa)],
    });

    return {
      ...result,
      listing_id: Number(nextListingId),
      token_id: Number(payload.token_id),
      contract_address: address,
    };
  }

  async buyListing(payload) {
    const address = this.getAddress();
    const result = await contractInteractionService.write({
      address,
      abi: NFT_ABI,
      method: 'buyListing',
      args: [Number(payload.listing_id), String(payload.buyer_wallet)],
    });

    return {
      ...result,
      listing_id: Number(payload.listing_id),
      buyer_wallet: String(payload.buyer_wallet),
      contract_address: address,
    };
  }

  async createAuction(payload) {
    const address = this.getAddress();
    const nextAuctionId = await contractInteractionService.read({ address, abi: NFT_ABI, method: 'nextAuctionId', args: [] });
    const result = await contractInteractionService.write({
      address,
      abi: NFT_ABI,
      method: 'createAuction',
      args: [
        Number(payload.token_id),
        toExaUnits(payload.starting_price_exa),
        toExaUnits(payload.reserve_price_exa || '0'),
        Number(payload.ends_at),
      ],
    });

    return {
      ...result,
      auction_id: Number(nextAuctionId),
      token_id: Number(payload.token_id),
      contract_address: address,
    };
  }

  async placeBid(payload) {
    const address = this.getAddress();
    const result = await contractInteractionService.write({
      address,
      abi: NFT_ABI,
      method: 'placeBid',
      args: [Number(payload.auction_id), String(payload.bidder_wallet), toExaUnits(payload.bid_amount_exa)],
    });

    return {
      ...result,
      auction_id: Number(payload.auction_id),
      bidder_wallet: String(payload.bidder_wallet),
      contract_address: address,
    };
  }

  async finalizeAuction(payload) {
    const address = this.getAddress();
    const result = await contractInteractionService.write({
      address,
      abi: NFT_ABI,
      method: 'finalizeAuction',
      args: [Number(payload.auction_id)],
    });

    return {
      ...result,
      auction_id: Number(payload.auction_id),
      contract_address: address,
    };
  }

  async upgradeNft(payload) {
    const address = this.getAddress();
    const result = await contractInteractionService.write({
      address,
      abi: NFT_ABI,
      method: 'upgradeFinancialNft',
      args: [
        Number(payload.token_id),
        String(payload.target_tier || 'standard'),
        Number(payload.target_level),
        toExaUnits(payload.upgrade_fee_exa || '0'),
      ],
    });

    return {
      ...result,
      token_id: Number(payload.token_id),
      contract_address: address,
    };
  }

  async startEventListener() {
    if (this.pollTimer || !this.getAddress()) {
      return;
    }

    const provider = blockchain.getEthersProvider('base');
    this.lastProcessedBlock = await provider.getBlockNumber();

    this.pollTimer = setInterval(async () => {
      try {
        await this.syncRecentEvents();
      } catch (error) {
        logger.error('Financial NFT event sync failed', { error: error.message });
      }
    }, config.nft?.eventPollIntervalMs || 15000);

    logger.info('Financial NFT event listener started', {
      contract: this.getAddress(),
      from_block: this.lastProcessedBlock,
    });
  }

  async syncRecentEvents() {
    const contract = this.getContract();
    const provider = blockchain.getEthersProvider('base');
    const latestBlock = await provider.getBlockNumber();

    if (latestBlock <= this.lastProcessedBlock) {
      return;
    }

    const fromBlock = this.lastProcessedBlock + 1;
    const toBlock = latestBlock;
    const eventNames = ['NFTMinted', 'NFTListed', 'NFTSold', 'BidPlaced', 'AuctionEnded', 'NFTUpgraded'];

    for (const eventName of eventNames) {
      const events = await contract.queryFilter(contract.filters[eventName](), fromBlock, toBlock);
      for (const event of events) {
        await this.forwardEvent(eventName, event);
      }
    }

    this.lastProcessedBlock = latestBlock;
  }

  async forwardEvent(eventName, event) {
    const args = event.args || [];
    const payload = {
      event: eventName,
      tx_hash: event.transactionHash,
      contract_address: event.address,
      payload: {
        block_number: event.blockNumber,
        log_index: event.index,
      },
    };

    if (eventName === 'NFTMinted') {
      payload.token_id = Number(args.tokenId);
      payload.owner_wallet = args.owner;
      payload.tier = args.tier;
      payload.payload = {
        ...payload.payload,
        utility_type: args.utilityType,
        metadata_url: args.metadataURI,
        mint_fee_exa: ethers.formatUnits(args.mintFee ?? 0, 18),
      };
    }

    if (eventName === 'NFTListed') {
      payload.token_id = Number(args.tokenId);
      payload.seller_wallet = args.seller;
      payload.payload = {
        ...payload.payload,
        listing_id: Number(args.listingId),
        price_exa: ethers.formatUnits(args.price ?? 0, 18),
      };
    }

    if (eventName === 'NFTSold') {
      payload.token_id = Number(args.tokenId);
      payload.buyer_wallet = args.buyer;
      payload.seller_wallet = args.seller;
      payload.sale_price_exa = ethers.formatUnits(args.price ?? 0, 18);
      payload.payload = {
        ...payload.payload,
        listing_id: Number(args.listingId),
        platform_fee_exa: ethers.formatUnits(args.platformFee ?? 0, 18),
      };
    }

    if (eventName === 'BidPlaced') {
      payload.token_id = Number(args.tokenId);
      payload.buyer_wallet = args.bidder;
      payload.payload = {
        ...payload.payload,
        auction_id: Number(args.auctionId),
        bid_amount_exa: ethers.formatUnits(args.amount ?? 0, 18),
      };
    }

    if (eventName === 'AuctionEnded') {
      payload.token_id = Number(args.tokenId);
      payload.buyer_wallet = args.winner;
      payload.sale_price_exa = ethers.formatUnits(args.amount ?? 0, 18);
      payload.payload = {
        ...payload.payload,
        auction_id: Number(args.auctionId),
        platform_fee_exa: ethers.formatUnits(args.platformFee ?? 0, 18),
      };
    }

    if (eventName === 'NFTUpgraded') {
      payload.token_id = Number(args.tokenId);
      payload.tier = args.tier;
      payload.level = Number(args.level);
      payload.payload = {
        ...payload.payload,
        upgrade_fee_exa: ethers.formatUnits(args.upgradeFee ?? 0, 18),
      };
    }

    await webhookNotifier.notifyNftEvent(payload);
  }
}

module.exports = new FinancialNftContractService();
