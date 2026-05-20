const { expect } = require('chai');
const { ethers } = require('hardhat');

describe('XRP Hybrid Staking System', function () {
  async function deployFixture() {
    const [admin, bridgeOperator, user] = await ethers.getSigners();

    const ExaToken = await ethers.getContractFactory('ExaToken');
    const exaToken = await ExaToken.deploy(admin.address, admin.address);
    await exaToken.waitForDeployment();

    const WrappedXRP = await ethers.getContractFactory('WrappedXRP');
    const wXrp = await WrappedXRP.deploy(admin.address, bridgeOperator.address);
    await wXrp.waitForDeployment();

    const XRPStakingContract = await ethers.getContractFactory('XRPStakingContract');
    const staking = await XRPStakingContract.deploy(admin.address, await wXrp.getAddress(), await exaToken.getAddress());
    await staking.waitForDeployment();

    const rewardRole = await exaToken.REWARD_DISTRIBUTOR_ROLE();
    await (await exaToken.connect(admin).grantRole(rewardRole, await staking.getAddress())).wait();
    await (await exaToken.connect(admin).burn(ethers.parseEther('1000000'))).wait();

    return { exaToken, wXrp, staking, admin, bridgeOperator, user };
  }

  it('enforces lock duration before unstake', async function () {
    const { wXrp, staking, bridgeOperator, user } = await deployFixture();
    const amount = ethers.parseUnits('100', 6);

    await (await wXrp.connect(bridgeOperator).mint(user.address, amount)).wait();
    await (await wXrp.connect(user).approve(await staking.getAddress(), amount)).wait();

    await (await staking.connect(user).stake(amount, 7 * 24 * 60 * 60)).wait();
    await expect(staking.connect(user).unstake(0)).to.be.revertedWith('Stake still locked');
  });

  it('accrues and claims reward using per-second formula', async function () {
    const { exaToken, wXrp, staking, bridgeOperator, user } = await deployFixture();
    const amount = ethers.parseUnits('100', 6);

    await (await wXrp.connect(bridgeOperator).mint(user.address, amount)).wait();
    await (await wXrp.connect(user).approve(await staking.getAddress(), amount)).wait();
    await (await staking.connect(user).stake(amount, 30 * 24 * 60 * 60)).wait();

    await ethers.provider.send('evm_increaseTime', [24 * 60 * 60]);
    await ethers.provider.send('evm_mine');

    const before = await exaToken.balanceOf(user.address);
    await (await staking.connect(user).claimRewards(0)).wait();
    const after = await exaToken.balanceOf(user.address);

    expect(after).to.be.gt(before);
  });

  it('maintains mint/burn pathway for wXRP bridge operator', async function () {
    const { wXrp, bridgeOperator, user } = await deployFixture();
    const amount = ethers.parseUnits('250', 6);

    await (await wXrp.connect(bridgeOperator).mint(user.address, amount)).wait();
    expect(await wXrp.balanceOf(user.address)).to.equal(amount);

    await (await wXrp.connect(bridgeOperator).burn(user.address, ethers.parseUnits('50', 6))).wait();
    expect(await wXrp.balanceOf(user.address)).to.equal(ethers.parseUnits('200', 6));
  });
});

