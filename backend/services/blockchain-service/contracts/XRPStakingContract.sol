// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";

interface IExaRewardToken {
    function distributeReward(address to, uint256 amount, bytes32 rewardType) external;
}

contract XRPStakingContract is AccessControl, ReentrancyGuard {
    bytes32 public constant CONFIG_ROLE = keccak256("CONFIG_ROLE");
    bytes32 public constant STAKING_REWARD_TYPE = keccak256("xrp_staking_reward");
    uint256 public constant DECIMAL_SCALE = 1e12; // Convert 6-decimal wXRP amount to 18-decimal EXA accounting.

    struct StakeInfo {
        uint256 amount;
        uint256 startTime;
        uint256 lockDuration;
        uint256 rewardRate;
        uint256 lastClaimTime;
        bool withdrawn;
    }

    IERC20 public immutable wrappedXrp;
    IExaRewardToken public immutable exaToken;

    mapping(address => StakeInfo[]) public stakes;
    mapping(uint256 => uint256) public apyByDuration;

    event Staked(address indexed user, uint256 amount, uint256 duration);
    event RewardClaimed(address indexed user, uint256 amount);
    event Unstaked(address indexed user, uint256 amount);

    constructor(address admin, address wXrpToken, address rewardToken) {
        require(admin != address(0), "invalid admin");
        require(wXrpToken != address(0), "invalid wXRP");
        require(rewardToken != address(0), "invalid EXA");

        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(CONFIG_ROLE, admin);

        wrappedXrp = IERC20(wXrpToken);
        exaToken = IExaRewardToken(rewardToken);

        apyByDuration[7 days] = 5;
        apyByDuration[30 days] = 12;
        apyByDuration[90 days] = 20;
    }

    function setApyForDuration(uint256 duration, uint256 apyPercent) external onlyRole(CONFIG_ROLE) {
        require(duration > 0, "invalid duration");
        require(apyPercent > 0, "invalid apy");
        apyByDuration[duration] = apyPercent;
    }

    function stake(uint256 amount, uint256 lockDuration) external nonReentrant {
        require(amount > 0, "invalid amount");

        uint256 rate = apyByDuration[lockDuration];
        require(rate > 0, "unsupported lock duration");

        require(wrappedXrp.transferFrom(msg.sender, address(this), amount), "wXRP transfer failed");

        stakes[msg.sender].push(
            StakeInfo({
                amount: amount,
                startTime: block.timestamp,
                lockDuration: lockDuration,
                rewardRate: rate,
                lastClaimTime: block.timestamp,
                withdrawn: false
            })
        );

        emit Staked(msg.sender, amount, lockDuration);
    }

    function claimRewards(uint256 stakeIndex) external nonReentrant returns (uint256 reward) {
        StakeInfo storage info = stakes[msg.sender][stakeIndex];
        require(!info.withdrawn, "stake withdrawn");

        reward = _calculateReward(info);
        info.lastClaimTime = block.timestamp;

        if (reward > 0) {
            exaToken.distributeReward(msg.sender, reward, STAKING_REWARD_TYPE);
            emit RewardClaimed(msg.sender, reward);
        }
    }

    function unstake(uint256 stakeIndex) external nonReentrant returns (uint256 reward) {
        StakeInfo storage info = stakes[msg.sender][stakeIndex];
        require(!info.withdrawn, "stake withdrawn");
        require(block.timestamp >= info.startTime + info.lockDuration, "Stake still locked");

        reward = _calculateReward(info);
        info.lastClaimTime = block.timestamp;
        info.withdrawn = true;

        require(wrappedXrp.transfer(msg.sender, info.amount), "wXRP return failed");

        if (reward > 0) {
            exaToken.distributeReward(msg.sender, reward, STAKING_REWARD_TYPE);
            emit RewardClaimed(msg.sender, reward);
        }

        emit Unstaked(msg.sender, info.amount);
    }

    function getStakeCount(address user) external view returns (uint256) {
        return stakes[user].length;
    }

    function pendingReward(address user, uint256 stakeIndex) external view returns (uint256) {
        StakeInfo memory info = stakes[user][stakeIndex];
        if (info.withdrawn) {
            return 0;
        }
        return _calculateReward(info);
    }

    function _calculateReward(StakeInfo memory info) internal view returns (uint256) {
        if (info.amount == 0 || info.lastClaimTime == 0 || info.withdrawn) {
            return 0;
        }

        uint256 elapsed = block.timestamp - info.lastClaimTime;
        if (elapsed == 0) {
            return 0;
        }

        uint256 normalizedAmount = info.amount * DECIMAL_SCALE;
        return (normalizedAmount * info.rewardRate * elapsed) / (365 days * 100);
    }
}

