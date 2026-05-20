const { expect } = require('chai');
const { ethers } = require('hardhat');

describe('ExaEarn smart contracts', function () {
  async function deployFixture() {
    const [admin, treasury, user, operator] = await ethers.getSigners();

    const ExaToken = await ethers.getContractFactory('ExaToken');
    const exaToken = await ExaToken.deploy(admin.address, treasury.address);
    await exaToken.waitForDeployment();

    const ExaStaking = await ethers.getContractFactory('ExaStaking');
    const staking = await ExaStaking.deploy(await exaToken.getAddress(), await exaToken.getAddress(), admin.address);
    await staking.waitForDeployment();

    const RewardDistributor = await ethers.getContractFactory('RewardDistributor');
    const distributor = await RewardDistributor.deploy(await exaToken.getAddress(), admin.address);
    await distributor.waitForDeployment();

    await exaToken.connect(admin).grantRole(await exaToken.STAKING_CONTRACT_ROLE(), await staking.getAddress());
    await exaToken.connect(admin).grantRole(await exaToken.REWARD_DISTRIBUTOR_ROLE(), await distributor.getAddress());
    await exaToken.connect(admin).grantRole(await exaToken.REWARD_DISTRIBUTOR_ROLE(), await staking.getAddress());
    await staking.connect(admin).grantRole(await staking.OPERATOR_ROLE(), user.address);
    await staking.connect(admin).grantRole(await staking.POOL_ADMIN_ROLE(), user.address);
    await exaToken.connect(treasury).transfer(user.address, ethers.parseEther('1000'));

    return { exaToken, staking, distributor, admin, treasury, user, operator };
  }

  it('deploys with capped supply assigned to treasury', async function () {
    const { exaToken, treasury } = await deployFixture();
    expect(await exaToken.MAX_SUPPLY()).to.equal(ethers.parseEther('1000000000'));
    expect(await exaToken.totalSupply()).to.equal(ethers.parseEther('900000000'));
    expect(await exaToken.balanceOf(treasury.address)).to.equal(ethers.parseEther('899999000'));
  });

  it('restricts minting to authorized roles', async function () {
    const { exaToken, operator, user } = await deployFixture();
    await expect(exaToken.connect(operator).mint(user.address, ethers.parseEther('1'))).to.be.reverted;
  });

  it('supports burn and burnFrom', async function () {
    const { exaToken, user, treasury } = await deployFixture();
    await exaToken.connect(user).burn(ethers.parseEther('10'));
    expect(await exaToken.balanceOf(user.address)).to.equal(ethers.parseEther('990'));

    await exaToken.connect(treasury).approve(user.address, ethers.parseEther('25'));
    await exaToken.connect(user).burnFrom(treasury.address, ethers.parseEther('25'));
    expect(await exaToken.balanceOf(treasury.address)).to.equal(ethers.parseEther('899998975'));
  });

  it('allows staking lock and reward claims through staking contract', async function () {
    const { exaToken, staking, user } = await deployFixture();
    const poolId = await staking.connect(user).createPool.staticCall(
      7 * 24 * 60 * 60,
      1200,
      10000,
      ethers.parseEther('10000')
    );
    await staking.connect(user).createPool(7 * 24 * 60 * 60, 1200, 10000, ethers.parseEther('10000'));

    await exaToken.connect(user).approve(await staking.getAddress(), ethers.parseEther('100'));
    await staking.connect(user).stakeFor(1, poolId, ethers.parseEther('100'), false);

    const position = await staking.positions(1, poolId);
    expect(position.principalAmount).to.equal(ethers.parseEther('100'));

    await ethers.provider.send('evm_increaseTime', [24 * 60 * 60]);
    await ethers.provider.send('evm_mine');

    const before = await exaToken.balanceOf(user.address);
    await staking.connect(user).claimRewardFor(1, poolId);
    expect(await exaToken.balanceOf(user.address)).to.be.gt(before);
  });

  it('allows authorized reward distribution', async function () {
    const { exaToken, distributor, admin, user } = await deployFixture();
    const rewardType = ethers.keccak256(ethers.toUtf8Bytes('education_reward'));
    const campaignId = ethers.keccak256(ethers.toUtf8Bytes('cohort_001'));

    await distributor.connect(admin).distribute(user.address, ethers.parseEther('5'), rewardType, campaignId);
    expect(await exaToken.balanceOf(user.address)).to.equal(ethers.parseEther('1005'));
  });
});
