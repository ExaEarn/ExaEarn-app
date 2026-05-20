// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";

contract GameFiLottery is AccessControl, ReentrancyGuard {
    bytes32 public constant GAME_MANAGER_ROLE = keccak256("GAME_MANAGER_ROLE");

    struct LotteryRound {
        uint256 id;
        uint256 entryFee;
        uint256 maxPlayers;
        uint256 playerCount;
        uint256 jackpot;
        uint256 drawAt;
        bool drawn;
        address winner;
    }

    struct BettingPool {
        uint256 id;
        string eventName;
        string[] options;
        uint256 lockAt;
        bool resolved;
        string winningOption;
        uint256 totalPool;
    }

    uint256 public nextRoundId = 1;
    uint256 public nextPoolId = 1;

    mapping(uint256 => LotteryRound) public lotteryRounds;
    mapping(uint256 => address[]) public lotteryPlayers;
    mapping(uint256 => mapping(address => uint256)) public lotteryEntriesByWallet;

    mapping(uint256 => BettingPool) public bettingPools;
    mapping(uint256 => mapping(bytes32 => uint256)) public poolOptionTotals;
    mapping(uint256 => mapping(address => mapping(bytes32 => uint256))) public poolBets;
    mapping(uint256 => mapping(address => bool)) public claimedBets;

    event LotteryRoundCreated(uint256 indexed roundId, uint256 entryFee, uint256 maxPlayers, uint256 drawAt);
    event LotteryEntered(uint256 indexed roundId, address indexed player, uint256 amount, uint256 jackpot);
    event LotteryWinnerSelected(uint256 indexed roundId, address indexed winner, uint256 jackpot);

    event BettingPoolCreated(uint256 indexed poolId, string eventName, uint256 lockAt);
    event BetPlaced(uint256 indexed poolId, address indexed player, string option, uint256 amount);
    event BettingPoolResolved(uint256 indexed poolId, string winningOption, uint256 totalPool);
    event BettingRewardClaimed(uint256 indexed poolId, address indexed player, uint256 reward);

    constructor(address admin) {
        require(admin != address(0), "invalid admin");

        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(GAME_MANAGER_ROLE, admin);
    }

    function createLotteryRound(uint256 entryFee, uint256 maxPlayers, uint256 drawAt)
        external
        onlyRole(GAME_MANAGER_ROLE)
        returns (uint256 roundId)
    {
        require(entryFee > 0, "invalid fee");
        require(maxPlayers > 1 || drawAt > block.timestamp, "invalid trigger");

        roundId = nextRoundId++;
        lotteryRounds[roundId] = LotteryRound({
            id: roundId,
            entryFee: entryFee,
            maxPlayers: maxPlayers,
            playerCount: 0,
            jackpot: 0,
            drawAt: drawAt,
            drawn: false,
            winner: address(0)
        });

        emit LotteryRoundCreated(roundId, entryFee, maxPlayers, drawAt);
    }

    function enterLottery(uint256 roundId) external payable nonReentrant {
        LotteryRound storage round = lotteryRounds[roundId];
        require(round.id > 0, "round missing");
        require(!round.drawn, "round drawn");
        require(msg.value == round.entryFee, "invalid fee");
        require(round.maxPlayers == 0 || round.playerCount < round.maxPlayers, "round full");

        lotteryPlayers[roundId].push(msg.sender);
        lotteryEntriesByWallet[roundId][msg.sender] += 1;
        round.playerCount += 1;
        round.jackpot += msg.value;

        emit LotteryEntered(roundId, msg.sender, msg.value, round.jackpot);

        if (round.maxPlayers > 0 && round.playerCount >= round.maxPlayers) {
            _drawLottery(roundId);
        }
    }

    function drawLottery(uint256 roundId) external nonReentrant {
        LotteryRound storage round = lotteryRounds[roundId];
        require(round.id > 0, "round missing");
        require(!round.drawn, "already drawn");
        require(round.drawAt > 0 && block.timestamp >= round.drawAt, "too early");
        require(round.playerCount > 0, "no players");

        _drawLottery(roundId);
    }

    function createBettingPool(string calldata eventName, string[] calldata options, uint256 lockAt)
        external
        onlyRole(GAME_MANAGER_ROLE)
        returns (uint256 poolId)
    {
        require(bytes(eventName).length > 0, "invalid event");
        require(options.length >= 2, "invalid options");

        poolId = nextPoolId++;
        BettingPool storage pool = bettingPools[poolId];
        pool.id = poolId;
        pool.eventName = eventName;
        pool.lockAt = lockAt;

        for (uint256 i = 0; i < options.length; i++) {
            pool.options.push(options[i]);
        }

        emit BettingPoolCreated(poolId, eventName, lockAt);
    }

    function placeBet(uint256 poolId, string calldata option) external payable nonReentrant {
        BettingPool storage pool = bettingPools[poolId];
        require(pool.id > 0, "pool missing");
        require(!pool.resolved, "pool resolved");
        require(msg.value > 0, "invalid amount");
        require(pool.lockAt == 0 || block.timestamp < pool.lockAt, "pool locked");
        require(_optionExists(pool, option), "option missing");

        bytes32 optionHash = keccak256(bytes(option));
        poolBets[poolId][msg.sender][optionHash] += msg.value;
        poolOptionTotals[poolId][optionHash] += msg.value;
        pool.totalPool += msg.value;

        emit BetPlaced(poolId, msg.sender, option, msg.value);
    }

    function resolveBettingPool(uint256 poolId, string calldata winningOption) external onlyRole(GAME_MANAGER_ROLE) {
        BettingPool storage pool = bettingPools[poolId];
        require(pool.id > 0, "pool missing");
        require(!pool.resolved, "already resolved");
        require(_optionExists(pool, winningOption), "option missing");

        pool.resolved = true;
        pool.winningOption = winningOption;

        emit BettingPoolResolved(poolId, winningOption, pool.totalPool);
    }

    function claimBettingReward(uint256 poolId) external nonReentrant {
        BettingPool storage pool = bettingPools[poolId];
        require(pool.resolved, "pool unresolved");
        require(!claimedBets[poolId][msg.sender], "already claimed");

        bytes32 winningHash = keccak256(bytes(pool.winningOption));
        uint256 userStake = poolBets[poolId][msg.sender][winningHash];
        require(userStake > 0, "no reward");

        uint256 winnerTotal = poolOptionTotals[poolId][winningHash];
        uint256 reward = (pool.totalPool * userStake) / winnerTotal;
        claimedBets[poolId][msg.sender] = true;

        (bool success,) = payable(msg.sender).call{value: reward}("");
        require(success, "reward transfer failed");

        emit BettingRewardClaimed(poolId, msg.sender, reward);
    }

    function getLotteryPlayers(uint256 roundId) external view returns (address[] memory) {
        return lotteryPlayers[roundId];
    }

    function getBettingOptions(uint256 poolId) external view returns (string[] memory) {
        return bettingPools[poolId].options;
    }

    function _drawLottery(uint256 roundId) internal {
        LotteryRound storage round = lotteryRounds[roundId];
        uint256 randomness = uint256(keccak256(abi.encodePacked(block.prevrandao, block.timestamp, lotteryPlayers[roundId].length, round.jackpot)));
        uint256 index = randomness % lotteryPlayers[roundId].length;
        address winner = lotteryPlayers[roundId][index];

        round.drawn = true;
        round.winner = winner;
        uint256 payout = round.jackpot;
        round.jackpot = 0;

        (bool success,) = payable(winner).call{value: payout}("");
        require(success, "jackpot transfer failed");

        emit LotteryWinnerSelected(roundId, winner, payout);
    }

    function _optionExists(BettingPool storage pool, string calldata option) private view returns (bool) {
        bytes32 optionHash = keccak256(bytes(option));
        for (uint256 i = 0; i < pool.options.length; i++) {
            if (keccak256(bytes(pool.options[i])) == optionHash) {
                return true;
            }
        }

        return false;
    }
}
