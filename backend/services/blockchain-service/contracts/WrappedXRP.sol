// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/AccessControl.sol";

contract WrappedXRP is ERC20, AccessControl {
    bytes32 public constant BRIDGE_ROLE = keccak256("BRIDGE_ROLE");

    event WrappedXRPMinted(address indexed operator, address indexed to, uint256 amount);
    event WrappedXRPBurned(address indexed operator, address indexed from, uint256 amount);

    constructor(address admin, address bridgeOperator) ERC20("Wrapped XRP", "wXRP") {
        require(admin != address(0), "invalid admin");
        require(bridgeOperator != address(0), "invalid bridge operator");

        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(BRIDGE_ROLE, bridgeOperator);
    }

    function decimals() public pure override returns (uint8) {
        return 6;
    }

    function mint(address to, uint256 amount) external onlyRole(BRIDGE_ROLE) {
        require(to != address(0), "invalid recipient");
        require(amount > 0, "invalid amount");
        _mint(to, amount);
        emit WrappedXRPMinted(msg.sender, to, amount);
    }

    function burn(address from, uint256 amount) external onlyRole(BRIDGE_ROLE) {
        require(from != address(0), "invalid source");
        require(amount > 0, "invalid amount");
        _burn(from, amount);
        emit WrappedXRPBurned(msg.sender, from, amount);
    }
}
