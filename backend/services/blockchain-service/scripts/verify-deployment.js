const hre = require('hardhat');
const fs = require('fs');
const path = require('path');

async function main() {
  const filePath = path.join(__dirname, '..', 'deployments', `${hre.network.name}.json`);

  if (!fs.existsSync(filePath)) {
    throw new Error(`Missing deployment record: ${filePath}`);
  }

  const deployment = JSON.parse(fs.readFileSync(filePath, 'utf8'));
  const contracts = deployment.contracts || {};

  for (const [name, details] of Object.entries(contracts)) {
    const address = details.address;
    const constructorArguments = details.constructorArgs || [];

    if (!address) {
      console.warn(`Skipping ${name}: missing address`);
      continue;
    }

    console.log(`Verifying ${name} at ${address}`);

    try {
      await hre.run('verify:verify', {
        address,
        constructorArguments,
      });
    } catch (error) {
      const message = String(error?.message || error);
      if (message.toLowerCase().includes('already verified')) {
        console.log(`${name} is already verified.`);
        continue;
      }

      throw error;
    }
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
