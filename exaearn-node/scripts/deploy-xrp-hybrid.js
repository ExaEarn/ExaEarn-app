const hre = require('hardhat');

async function main() {
  const [deployer] = await hre.ethers.getSigners();

  const admin = process.env.TOKEN_ADMIN_ADDRESS || deployer.address;
  const treasury = process.env.TOKEN_TREASURY_ADDRESS || deployer.address;
  const bridgeOperator = process.env.BRIDGE_OPERATOR_ADDRESS || deployer.address;

  console.log('Deploying XRP hybrid stack with account:', deployer.address);

  const ExaToken = await hre.ethers.getContractFactory('ExaToken');
  const exaToken = await ExaToken.deploy(admin, treasury);
  await exaToken.waitForDeployment();

  const WrappedXRP = await hre.ethers.getContractFactory('WrappedXRP');
  const wrappedXrp = await WrappedXRP.deploy(admin, bridgeOperator);
  await wrappedXrp.waitForDeployment();

  const XRPStakingContract = await hre.ethers.getContractFactory('XRPStakingContract');
  const xrpStaking = await XRPStakingContract.deploy(admin, await wrappedXrp.getAddress(), await exaToken.getAddress());
  await xrpStaking.waitForDeployment();

  const rewardRole = await exaToken.REWARD_DISTRIBUTOR_ROLE();
  await (await exaToken.grantRole(rewardRole, await xrpStaking.getAddress())).wait();

  console.log('ExaToken:', await exaToken.getAddress());
  console.log('WrappedXRP:', await wrappedXrp.getAddress());
  console.log('XRPStakingContract:', await xrpStaking.getAddress());
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

