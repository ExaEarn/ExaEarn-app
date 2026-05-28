// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";

interface IExaRewardToken is IERC20 {
    function distributeReward(address to, uint256 amount, bytes32 rewardType) external;
}

contract ExaStaking is AccessControl, ReentrancyGuard {
    bytes32 public constant POOL_ADMIN_ROLE = keccak256("POOL_ADMIN_ROLE");
    bytes32 public constant OPERATOR_ROLE = keccak256("OPERATOR_ROLE");

    struct Pool {
        uint256 lockPeriod;
        uint256 rewardRate;
        uint256 multiplierBps;
        uint256 poolSize;
        uint256 totalStaked;
        bool active;
    }

    struct Position {
        uint256 principalAmount;
        uint256 compoundedRewardAmount;
        uint256 rewardDebtAt;
        uint256 unlockAt;
        bool autoCompound;
        bool active;
    }

    IERC20 public immutable stakeAsset;
    IExaRewardToken public immutable rewardToken;
    uint256 public nextPoolId;
    uint256 public constant SECONDS_PER_YEAR = 31536000;
    bytes32 public constant STAKING_REWARD_TYPE = keccak256("staking_reward");

    mapping(uint256 => Pool) public pools;
    mapping(uint256 => mapping(uint256 => Position)) public positions;

    event PoolCreated(uint256 indexed poolId, uint256 lockPeriod, uint256 rewardRate, uint256 multiplierBps, uint256 poolSize);
    event PoolUpdated(uint256 indexed poolId, uint256 rewardRate, uint256 multiplierBps, uint256 poolSize, bool active);
    event Staked(uint256 indexed userId, uint256 indexed poolId, uint256 amount, uint256 unlockAt, bool autoCompound);
    event RewardClaimed(uint256 indexed userId, uint256 indexed poolId, uint256 reward);
    event RewardCompounded(uint256 indexed userId, uint256 indexed poolId, uint256 reward);
    event Unstaked(uint256 indexed userId, uint256 indexed poolId, uint256 amount, uint256 compoundedRewardReleased);

    constructor(address _stakeAsset, address _rewardToken, address admin) {
        require(_stakeAsset != address(0), "invalid stake asset");
        require(_rewardToken != address(0), "invalid reward token");
        require(admin != address(0), "invalid admin");

        stakeAsset = IERC20(_stakeAsset);
        rewardToken = IExaRewardToken(_rewardToken);

        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(POOL_ADMIN_ROLE, admin);
        _grantRole(OPERATOR_ROLE, admin);
    }

    function createPool(
        uint256 lockPeriod,
        uint256 rewardRate,
        uint256 multiplierBps,
        uint256 poolSize
    ) external onlyRole(POOL_ADMIN_ROLE) returns (uint256 poolId) {
        require(lockPeriod > 0, "invalid lock period");
        require(rewardRate > 0, "invalid reward rate");
        require(multiplierBps >= 10000, "invalid multiplier");
        require(poolSize > 0, "invalid pool size");

        poolId = ++nextPoolId;
        pools[poolId] = Pool({
            lockPeriod: lockPeriod,
            rewardRate: rewardRate,
            multiplierBps: multiplierBps,
            poolSize: poolSize,
            totalStaked: 0,
            active: true
        });

        emit PoolCreated(poolId, lockPeriod, rewardRate, multiplierBps, poolSize);
    }

    function updatePool(
        uint256 poolId,
        uint256 rewardRate,
        uint256 multiplierBps,
        uint256 poolSize,
        bool active
    ) external onlyRole(POOL_ADMIN_ROLE) {
        Pool storage pool = pools[poolId];
        require(pool.lockPeriod > 0, "pool missing");
        require(multiplierBps >= 10000, "invalid multiplier");
        require(poolSize >= pool.totalStaked, "pool smaller than total staked");

        pool.rewardRate = rewardRate;
        pool.multiplierBps = multiplierBps;
        pool.poolSize = poolSize;
        pool.active = active;

        emit PoolUpdated(poolId, rewardRate, multiplierBps, poolSize, active);
    }

    function stakeFor(uint256 userId, uint256 poolId, uint256 amount, bool autoCompound) external onlyRole(OPERATOR_ROLE) nonReentrant {
        require(amount > 0, "invalid amount");
        Pool storage pool = pools[poolId];
        require(pool.active, "pool inactive");
        require(pool.totalStaked + amount <= pool.poolSize, "pool capacity exceeded");

        Position storage position = positions[userId][poolId];
        _compoundIfEnabled(userId, poolId, position, pool);

        require(stakeAsset.transferFrom(msg.sender, address(this), amount), "stake transfer failed");

        position.principalAmount += amount;
        position.rewardDebtAt = block.timestamp;
        position.unlockAt = block.timestamp + pool.lockPeriod;
        position.autoCompound = autoCompound;
        position.active = true;

        pool.totalStaked += amount;
        emit Staked(userId, poolId, amount, position.unlockAt, autoCompound);
    }

    function claimRewardFor(uint256 userId, uint256 poolId) external onlyRole(OPERATOR_ROLE) nonReentrant returns (uint256 reward) {
        Position storage position = positions[userId][poolId];
        Pool storage pool = pools[poolId];
        require(position.active, "position inactive");

        reward = _pendingReward(position, pool);
        position.rewardDebtAt = block.timestamp;

        if (reward > 0) {
            rewardToken.distributeReward(msg.sender, reward, STAKING_REWARD_TYPE);
            emit RewardClaimed(userId, poolId, reward);
        }
    }

    function compoundRewardFor(uint256 userId, uint256 poolId) external onlyRole(OPERATOR_ROLE) nonReentrant returns (uint256 reward) {
        Position storage position = positions[userId][poolId];
        Pool storage pool = pools[poolId];
        require(position.active, "position inactive");

        reward = _pendingReward(position, pool);
        require(reward > 0, "no reward");

        position.compoundedRewardAmount += reward;
        position.rewardDebtAt = block.timestamp;
        position.autoCompound = true;

        emit RewardCompounded(userId, poolId, reward);
    }

    function unstakeFor(uint256 userId, uint256 poolId, uint256 amount) external onlyRole(OPERATOR_ROLE) nonReentrant returns (uint256 compoundedRelease) {
        Position storage position = positions[userId][poolId];
        Pool storage pool = pools[poolId];
        require(position.active, "position inactive");
        require(block.timestamp >= position.unlockAt, "still locked");
        require(amount > 0 && amount <= position.principalAmount, "invalid amount");

        uint256 reward = _pendingReward(position, pool);
        compoundedRelease = position.compoundedRewardAmount + reward;

        position.principalAmount -= amount;
        position.compoundedRewardAmount = 0;
        position.rewardDebtAt = block.timestamp;
        if (position.principalAmount == 0) {
            position.active = false;
        }

        pool.totalStaked -= amount;

        require(stakeAsset.transfer(msg.sender, amount), "unstake transfer failed");
        if (compoundedRelease > 0) {
            rewardToken.distributeReward(msg.sender, compoundedRelease, STAKING_REWARD_TYPE);
        }

        emit Unstaked(userId, poolId, amount, compoundedRelease);
    }

    function pendingReward(uint256 userId, uint256 poolId) external view returns (uint256) {
        return _pendingReward(positions[userId][poolId], pools[poolId]);
    }

    function effectiveStake(uint256 userId, uint256 poolId) external view returns (uint256) {
        Position memory position = positions[userId][poolId];
        return position.principalAmount + position.compoundedRewardAmount;
    }

    function _compoundIfEnabled(uint256 userId, uint256 poolId, Position storage position, Pool storage pool) internal {
        if (!position.active || !position.autoCompound) {
            return;
        }

        uint256 reward = _pendingReward(position, pool);
        if (reward > 0) {
            position.compoundedRewardAmount += reward;
            emit RewardCompounded(userId, poolId, reward);
        }
        position.rewardDebtAt = block.timestamp;
    }

    function _pendingReward(Position memory position, Pool memory pool) internal view returns (uint256) {
        if (!position.active || position.rewardDebtAt == 0) {
            return 0;
        }

        uint256 elapsed = block.timestamp - position.rewardDebtAt;
        if (elapsed == 0) {
            return 0;
        }

        uint256 effectiveAmount = position.principalAmount + position.compoundedRewardAmount;
        if (effectiveAmount == 0) {
            return 0;
        }

        return (((effectiveAmount * pool.rewardRate) / 10000) * pool.multiplierBps * elapsed) / (10000 * SECONDS_PER_YEAR);
    }
}
