// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/AccessControl.sol";

contract AgriInvestment is AccessControl {
    bytes32 public constant PROJECT_MANAGER_ROLE = keccak256("PROJECT_MANAGER_ROLE");
    bytes32 public constant LEASE_MANAGER_ROLE = keccak256("LEASE_MANAGER_ROLE");

    struct Project {
        uint256 projectId;
        string name;
        uint256 investmentTarget;
        uint256 totalShares;
        uint256 pricePerShare;
        string tokenSymbol;
        bool exists;
    }

    struct InvestmentRecord {
        uint256 projectId;
        uint256 userId;
        uint256 sharesOwned;
        uint256 amount;
        uint256 recordedAt;
    }

    struct LeaseRecord {
        uint256 projectId;
        uint256 farmerId;
        uint256 investmentId;
        uint256 profitShare;
        string leaseTerms;
        uint256 recordedAt;
    }

    mapping(uint256 => Project) public projects;
    mapping(bytes32 => InvestmentRecord) public investments;
    mapping(bytes32 => LeaseRecord) public leases;

    event ProjectTokenized(
        uint256 indexed projectId,
        string name,
        uint256 investmentTarget,
        uint256 totalShares,
        uint256 pricePerShare,
        string tokenSymbol
    );
    event InvestmentRecorded(uint256 indexed projectId, uint256 indexed userId, uint256 sharesOwned, uint256 amount);
    event LeaseRegistered(
        uint256 indexed projectId,
        uint256 indexed farmerId,
        uint256 indexed investmentId,
        uint256 profitShare,
        string leaseTerms
    );

    constructor(address admin) {
        require(admin != address(0), "invalid admin");

        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(PROJECT_MANAGER_ROLE, admin);
        _grantRole(LEASE_MANAGER_ROLE, admin);
    }

    function tokenizeProject(
        uint256 projectId,
        string calldata name,
        uint256 investmentTarget,
        uint256 totalShares,
        uint256 pricePerShare,
        string calldata tokenSymbol
    ) external onlyRole(PROJECT_MANAGER_ROLE) {
        require(projectId > 0, "invalid project");
        require(bytes(name).length > 0, "invalid name");
        require(investmentTarget > 0, "invalid target");
        require(totalShares > 0, "invalid shares");
        require(pricePerShare > 0, "invalid price");

        projects[projectId] = Project({
            projectId: projectId,
            name: name,
            investmentTarget: investmentTarget,
            totalShares: totalShares,
            pricePerShare: pricePerShare,
            tokenSymbol: tokenSymbol,
            exists: true
        });

        emit ProjectTokenized(projectId, name, investmentTarget, totalShares, pricePerShare, tokenSymbol);
    }

    function recordInvestment(uint256 projectId, uint256 userId, uint256 sharesOwned, uint256 amount)
        external
        onlyRole(PROJECT_MANAGER_ROLE)
    {
        require(projects[projectId].exists, "project missing");
        require(userId > 0, "invalid user");
        require(sharesOwned > 0, "invalid shares");
        require(amount > 0, "invalid amount");

        bytes32 recordKey = keccak256(abi.encodePacked(projectId, userId, sharesOwned, amount, block.timestamp));
        investments[recordKey] = InvestmentRecord({
            projectId: projectId,
            userId: userId,
            sharesOwned: sharesOwned,
            amount: amount,
            recordedAt: block.timestamp
        });

        emit InvestmentRecorded(projectId, userId, sharesOwned, amount);
    }

    function registerLease(
        uint256 projectId,
        uint256 farmerId,
        uint256 investmentId,
        uint256 profitShare,
        string calldata leaseTerms
    ) external onlyRole(LEASE_MANAGER_ROLE) {
        require(projects[projectId].exists, "project missing");
        require(farmerId > 0, "invalid farmer");
        require(profitShare > 0 && profitShare <= 100, "invalid share");
        require(bytes(leaseTerms).length > 0, "invalid terms");

        bytes32 leaseKey = keccak256(abi.encodePacked(projectId, farmerId, investmentId, profitShare, block.timestamp));
        leases[leaseKey] = LeaseRecord({
            projectId: projectId,
            farmerId: farmerId,
            investmentId: investmentId,
            profitShare: profitShare,
            leaseTerms: leaseTerms,
            recordedAt: block.timestamp
        });

        emit LeaseRegistered(projectId, farmerId, investmentId, profitShare, leaseTerms);
    }
}
