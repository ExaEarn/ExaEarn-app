// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/token/ERC20/extensions/ERC20Burnable.sol";
import "@openzeppelin/contracts/token/ERC20/extensions/ERC20Pausable.sol";
import "@openzeppelin/contracts/access/AccessControl.sol";

contract ExaToken is ERC20, ERC20Burnable, ERC20Pausable, AccessControl {
    bytes32 public constant MINTER_ROLE = keccak256("MINTER_ROLE");
    bytes32 public constant REWARD_DISTRIBUTOR_ROLE = keccak256("REWARD_DISTRIBUTOR_ROLE");
    bytes32 public constant STAKING_CONTRACT_ROLE = keccak256("STAKING_CONTRACT_ROLE");
    bytes32 public constant PAUSER_ROLE = keccak256("PAUSER_ROLE");

    uint256 public constant MAX_SUPPLY = 1_000_000_000 * 10 ** 18;
    uint256 public constant INITIAL_TREASURY_SUPPLY = 900_000_000 * 10 ** 18;

    event TokenMinted(address indexed operator, address indexed to, uint256 amount);
    event TokenBurned(address indexed operator, address indexed from, uint256 amount);
    event RewardDistributed(address indexed operator, address indexed to, uint256 amount, bytes32 indexed rewardType);
    event StakingLocked(address indexed stakingContract, address indexed user, uint256 amount);

    constructor(address admin, address treasury) ERC20("ExaToken", "EXA") {
        require(admin != address(0), "invalid admin");
        require(treasury != address(0), "invalid treasury");

        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(MINTER_ROLE, admin);
        _grantRole(REWARD_DISTRIBUTOR_ROLE, admin);
        _grantRole(PAUSER_ROLE, admin);

        _mint(treasury, INITIAL_TREASURY_SUPPLY);
    }

    function mint(address to, uint256 amount) external onlyRole(MINTER_ROLE) {
        require(totalSupply() + amount <= MAX_SUPPLY, "cap exceeded");
        _mint(to, amount);
        emit TokenMinted(msg.sender, to, amount);
    }

    function distributeReward(address to, uint256 amount, bytes32 rewardType) external onlyRole(REWARD_DISTRIBUTOR_ROLE) {
        require(totalSupply() + amount <= MAX_SUPPLY, "cap exceeded");
        _mint(to, amount);
        emit RewardDistributed(msg.sender, to, amount, rewardType);
    }

    function burn(uint256 value) public override {
        super.burn(value);
        emit TokenBurned(msg.sender, msg.sender, value);
    }

    function burnFrom(address account, uint256 value) public override {
        super.burnFrom(account, value);
        emit TokenBurned(msg.sender, account, value);
    }

    function pause() external onlyRole(PAUSER_ROLE) {
        _pause();
    }

    function unpause() external onlyRole(PAUSER_ROLE) {
        _unpause();
    }

    function notifyStakingLock(address user, uint256 amount) external onlyRole(STAKING_CONTRACT_ROLE) {
        emit StakingLocked(msg.sender, user, amount);
    }

    function _update(address from, address to, uint256 value) internal override(ERC20, ERC20Pausable) {
        super._update(from, to, value);
    }
}
