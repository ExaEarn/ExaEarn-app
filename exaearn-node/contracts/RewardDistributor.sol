// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";

interface IRewardToken {
    function distributeReward(address to, uint256 amount, bytes32 rewardType) external;
}

contract RewardDistributor is AccessControl, ReentrancyGuard {
    bytes32 public constant DISTRIBUTOR_ROLE = keccak256("DISTRIBUTOR_ROLE");

    IRewardToken public immutable exaToken;
    mapping(bytes32 => mapping(address => uint256)) public distributedRewards;

    event RewardPaid(address indexed user, uint256 amount, bytes32 indexed rewardType, bytes32 campaignId);

    constructor(address token, address admin) {
        require(token != address(0), "invalid token");
        require(admin != address(0), "invalid admin");

        exaToken = IRewardToken(token);
        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(DISTRIBUTOR_ROLE, admin);
    }

    function distribute(address to, uint256 amount, bytes32 rewardType, bytes32 campaignId)
        external
        onlyRole(DISTRIBUTOR_ROLE)
        nonReentrant
    {
        require(to != address(0), "invalid recipient");
        require(amount > 0, "invalid amount");

        exaToken.distributeReward(to, amount, rewardType);
        distributedRewards[campaignId][to] += amount;
        emit RewardPaid(to, amount, rewardType, campaignId);
    }

    function claimReward(address to, uint256 amount, bytes32 rewardType, bytes32 campaignId)
        external
        onlyRole(DISTRIBUTOR_ROLE)
        nonReentrant
    {
        require(to != address(0), "invalid recipient");
        require(amount > 0, "invalid amount");

        exaToken.distributeReward(to, amount, rewardType);
        distributedRewards[campaignId][to] += amount;
        emit RewardPaid(to, amount, rewardType, campaignId);
    }

    function batchDistribute(
        address[] calldata recipients,
        uint256[] calldata amounts,
        bytes32 rewardType,
        bytes32 campaignId
    ) external onlyRole(DISTRIBUTOR_ROLE) nonReentrant {
        require(recipients.length == amounts.length, "length mismatch");

        for (uint256 i = 0; i < recipients.length; i++) {
            require(recipients[i] != address(0), "invalid recipient");
            require(amounts[i] > 0, "invalid amount");
            exaToken.distributeReward(recipients[i], amounts[i], rewardType);
            distributedRewards[campaignId][recipients[i]] += amounts[i];
            emit RewardPaid(recipients[i], amounts[i], rewardType, campaignId);
        }
    }

    function verifyReward(address user, bytes32 campaignId) external view returns (bool exists, uint256 amount) {
        amount = distributedRewards[campaignId][user];
        exists = amount > 0;
    }
}
