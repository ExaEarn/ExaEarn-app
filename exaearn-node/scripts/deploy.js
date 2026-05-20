const hre = require('hardhat');
const fs = require('fs');
const path = require('path');

async function deployContract(name, args) {
  const Factory = await hre.ethers.getContractFactory(name);
  const contract = await Factory.deploy(...args);
  await contract.waitForDeployment();

  return {
    contract,
    address: await contract.getAddress(),
    args,
  };
}

function writeDeploymentRecord(networkName, deployer, contracts) {
  const deploymentsDir = path.join(__dirname, '..', 'deployments');
  fs.mkdirSync(deploymentsDir, { recursive: true });

  const record = {
    network: networkName,
    chainId: Number(hre.network.config.chainId || 0),
    deployer,
    deployedAt: new Date().toISOString(),
    contracts,
  };

  const filePath = path.join(deploymentsDir, `${networkName}.json`);
  fs.writeFileSync(filePath, `${JSON.stringify(record, null, 2)}\n`);
  console.log(`Deployment record saved: ${filePath}`);
}

async function main() {
  const [deployer] = await hre.ethers.getSigners();
  const admin = process.env.TOKEN_ADMIN_ADDRESS || deployer.address;
  const treasury = process.env.TOKEN_TREASURY_ADDRESS || deployer.address;
  const bridgeOperator = process.env.BRIDGE_OPERATOR_ADDRESS || deployer.address;
  const nftPlatformFeeBps = process.env.NFT_PLATFORM_FEE_BPS || '250';
  const stakingRewardRateBps = process.env.STAKING_REWARD_RATE_BPS || '1200';
  const stakingLockPeriod = process.env.STAKING_LOCK_PERIOD_SECONDS || '2592000';
  const stakingMultiplierBps = process.env.STAKING_MULTIPLIER_BPS || '10000';
  const stakingPoolSize = process.env.STAKING_POOL_SIZE || '1000000000000';

  console.log('Deploying contracts with account:', deployer.address);

  const deployment = {};

  deployment.ExaToken = await deployContract('ExaToken', [admin, treasury]);
  const exaToken = deployment.ExaToken.contract;

  deployment.WrappedXRP = await deployContract('WrappedXRP', [admin, bridgeOperator]);

  deployment.XRPStakingContract = await deployContract('XRPStakingContract', [
    admin,
    deployment.WrappedXRP.address,
    deployment.ExaToken.address,
  ]);

  const stakingAsset = process.env.STAKING_ASSET_TOKEN_ADDRESS || deployment.WrappedXRP.address;
  deployment.ExaStaking = await deployContract('ExaStaking', [
    stakingAsset,
    deployment.ExaToken.address,
    admin,
  ]);
  const staking = deployment.ExaStaking.contract;

  deployment.RewardDistributor = await deployContract('RewardDistributor', [deployment.ExaToken.address, admin]);

  deployment.AgriInvestment = await deployContract('AgriInvestment', [admin]);
  deployment.GameFiLottery = await deployContract('GameFiLottery', [admin]);
  deployment.ExaEarnFinancialNFT = await deployContract('ExaEarnFinancialNFT', [admin, treasury, nftPlatformFeeBps]);

  const contracts = Object.fromEntries(
    Object.entries(deployment).map(([name, item]) => [name, {
      address: item.address,
      constructorArgs: item.args,
    }])
  );

  const stakingRole = await exaToken.STAKING_CONTRACT_ROLE();
  const rewardRole = await exaToken.REWARD_DISTRIBUTOR_ROLE();

  await (await exaToken.grantRole(stakingRole, deployment.XRPStakingContract.address)).wait();
  await (await exaToken.grantRole(rewardRole, deployment.XRPStakingContract.address)).wait();
  await (await exaToken.grantRole(stakingRole, deployment.ExaStaking.address)).wait();
  await (await exaToken.grantRole(rewardRole, deployment.ExaStaking.address)).wait();
  await (await exaToken.grantRole(rewardRole, deployment.RewardDistributor.address)).wait();

  await (
    await staking.createPool(
      stakingLockPeriod,
      stakingRewardRateBps,
      stakingMultiplierBps,
      stakingPoolSize
    )
  ).wait();

  writeDeploymentRecord(hre.network.name, deployer.address, contracts);

  for (const [name, item] of Object.entries(contracts)) {
    console.log(`${name}:`, item.address);
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
