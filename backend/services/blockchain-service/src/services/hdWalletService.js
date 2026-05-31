const crypto = require('crypto');
const { ethers } = require('ethers');
const config = require('../config');
const logger = require('../utils/logger');
const optionalModule = require('../utils/optionalModule');

class HdWalletService {
  deriveDepositAddress({ userId, currency, network }) {
    const normalizedNetwork = String(network || config.getNetworkForCurrency(currency) || '').toLowerCase();
    const family = config.getChainFamily(normalizedNetwork);

    if (!family) {
      throw new Error(`Unsupported network: ${normalizedNetwork}`);
    }

    switch (normalizedNetwork) {
      case 'bitcoin':
        return this.deriveBitcoinAddress({ userId, currency, network: normalizedNetwork });
      case 'tron':
        return this.deriveTronAddress({ userId, currency, network: normalizedNetwork });
      case 'solana':
        return this.deriveSolanaAddress({ userId, currency, network: normalizedNetwork });
      case 'ton':
        return this.deriveTonAddress({ userId, currency, network: normalizedNetwork });
      default:
        break;
    }

    switch (family) {
      case 'evm':
        return this.deriveEvmAddress({ userId, currency, network: normalizedNetwork });
      case 'tagged':
        return this.deriveTaggedAddress({ userId, currency, network: normalizedNetwork });
      default:
        throw new Error(`Unsupported chain family: ${family}`);
    }
  }

  deriveEvmAddress({ userId, currency, network }) {
    const index = this.getAddressIndex({ userId, currency, network });
    const derivationPath = this.getBip44Path({ network, index });
    const wallet = ethers.HDNodeWallet.fromPhrase(
      this.getMnemonic(),
      config.hdWallet.passphrase,
      derivationPath
    );

    logger.info('Derived EVM deposit address', {
      userId,
      currency,
      network,
      derivationPath,
      address: wallet.address,
    });

    return this.baseResponse({
      address: wallet.address,
      network,
      derivationPath,
      index,
      family: 'evm',
    });
  }

  deriveBitcoinAddress({ userId, currency, network }) {
    const bip39 = optionalModule('bip39', 'pnpm add bip39');
    const bip32Factory = optionalModule('bip32', 'pnpm add bip32 tiny-secp256k1');
    const ecc = optionalModule('tiny-secp256k1', 'pnpm add tiny-secp256k1');
    const bitcoin = optionalModule('bitcoinjs-lib', 'pnpm add bitcoinjs-lib');
    const bip32 = bip32Factory.BIP32Factory ? bip32Factory.BIP32Factory(ecc) : bip32Factory.default(ecc);

    const index = this.getAddressIndex({ userId, currency, network });
    const derivationPath = this.getBip44Path({ network, index });
    const seed = bip39.mnemonicToSeedSync(this.getMnemonic(), config.hdWallet.passphrase);
    const root = bip32.fromSeed(seed);
    const child = root.derivePath(derivationPath);
    const btcNetwork = config.bitcoin.network === 'testnet' ? bitcoin.networks.testnet : bitcoin.networks.bitcoin;
    const payment = bitcoin.payments.p2wpkh({
      pubkey: Buffer.from(child.publicKey),
      network: btcNetwork,
    });

    if (!payment.address) {
      throw new Error('Failed to derive Bitcoin deposit address');
    }

    logger.info('Derived Bitcoin deposit address', {
      userId,
      currency,
      network,
      derivationPath,
      address: payment.address,
    });

    return this.baseResponse({
      address: payment.address,
      network,
      derivationPath,
      index,
      family: 'utxo',
      extraMetadata: {
        script_type: 'p2wpkh',
      },
    });
  }

  deriveTaggedAddress({ userId, currency, network }) {
    const xrplAddress = config.xrpl.hotWallet.address;
    if (!xrplAddress) {
      throw new Error('XRPL_HOT_WALLET_ADDRESS not configured');
    }

    const index = this.getAddressIndex({ userId, currency, network });
    const destinationTag = this.getDestinationTag({ userId, currency, network });

    logger.info('Derived tagged deposit address', {
      userId,
      currency,
      network,
      address: xrplAddress,
      destinationTag,
    });

    return {
      address: xrplAddress,
      network,
      metadata: {
        address_type: 'destination_tag',
        derivation_path: null,
        address_index: index,
        chain_family: 'tagged',
        coin_type: config.getCoinType(network),
        destination_tag: destinationTag,
      },
    };
  }

  deriveTronAddress({ userId, currency, network }) {
    const TronWebModule = optionalModule('tronweb', 'pnpm add tronweb');
    const index = this.getAddressIndex({ userId, currency, network });
    const derivationPath = this.getBip44Path({ network, index });
    const wallet = ethers.HDNodeWallet.fromPhrase(
      this.getMnemonic(),
      config.hdWallet.passphrase,
      derivationPath
    );
    const TronWeb = TronWebModule.TronWeb || TronWebModule.default || TronWebModule;
    const address = TronWeb.address.fromPrivateKey(wallet.privateKey.replace(/^0x/, ''));

    logger.info('Derived Tron deposit address', {
      userId,
      currency,
      network,
      derivationPath,
      address,
    });

    return this.baseResponse({
      address,
      network,
      derivationPath,
      index,
      family: 'account',
    });
  }

  deriveSolanaAddress({ userId, currency, network }) {
    const bip39 = optionalModule('bip39', 'pnpm add bip39');
    const { derivePath } = optionalModule('ed25519-hd-key', 'pnpm add ed25519-hd-key');
    const { Keypair } = optionalModule('@solana/web3.js', 'pnpm add @solana/web3.js');

    const index = this.getAddressIndex({ userId, currency, network });
    const derivationPath = this.getBip44Path({ network, index });
    const seed = bip39.mnemonicToSeedSync(this.getMnemonic(), config.hdWallet.passphrase);
    const derived = derivePath(derivationPath, seed.toString('hex'));
    const keypair = Keypair.fromSeed(derived.key.slice(0, 32));
    const address = keypair.publicKey.toBase58();

    logger.info('Derived Solana deposit address', {
      userId,
      currency,
      network,
      derivationPath,
      address,
    });

    return this.baseResponse({
      address,
      network,
      derivationPath,
      index,
      family: 'account',
    });
  }

  deriveTonAddress({ userId, currency, network }) {
    const bip39 = optionalModule('bip39', 'pnpm add bip39');
    const { derivePath } = optionalModule('ed25519-hd-key', 'pnpm add ed25519-hd-key');
    const nacl = optionalModule('tweetnacl', 'pnpm add tweetnacl');
    const ton = optionalModule('@ton/ton', 'pnpm add @ton/ton @ton/crypto');

    const index = this.getAddressIndex({ userId, currency, network });
    const derivationPath = this.getBip44Path({ network, index });
    const seed = bip39.mnemonicToSeedSync(this.getMnemonic(), config.hdWallet.passphrase);
    const derived = derivePath(derivationPath, seed.toString('hex'));
    const keyPair = nacl.sign.keyPair.fromSeed(derived.key.slice(0, 32));
    const wallet = ton.WalletContractV4.create({
      workchain: 0,
      publicKey: Buffer.from(keyPair.publicKey),
    });
    const address = wallet.address.toString();

    logger.info('Derived TON deposit address', {
      userId,
      currency,
      network,
      derivationPath,
      address,
    });

    return this.baseResponse({
      address,
      network,
      derivationPath,
      index,
      family: 'account',
    });
  }

  getMnemonic() {
    const mnemonic = config.hdWallet.mnemonic;
    if (!mnemonic) {
      throw new Error('HD_WALLET_MNEMONIC not configured');
    }

    return mnemonic;
  }

  getBip44Path({ network, index }) {
    const account = config.hdWallet.account;
    const change = config.hdWallet.change;
    const coinType = config.getCoinType(network);
    return `m/44'/${coinType}'/${account}'/${change}/${index}`;
  }

  baseResponse({ address, network, derivationPath, index, family, extraMetadata = {} }) {
    return {
      address,
      network,
      metadata: {
        address_type: config.hdWallet.addressType,
        derivation_path: derivationPath,
        address_index: index,
        chain_family: family,
        coin_type: config.getCoinType(network),
        ...extraMetadata,
      },
    };
  }

  getAddressIndex({ userId, currency, network }) {
    const buffer = crypto
      .createHash('sha256')
      .update(`${config.hdWallet.addressType}:${network}:${String(currency).toUpperCase()}:${userId}`)
      .digest();

    const rawIndex = buffer.readUInt32BE(0);
    return config.hdWallet.startIndex + (rawIndex % 0x7fffffff);
  }

  getDestinationTag({ userId, currency, network }) {
    const buffer = crypto
      .createHash('sha256')
      .update(`tag:${network}:${String(currency).toUpperCase()}:${userId}`)
      .digest();

    return buffer.readUInt32BE(0);
  }
}

module.exports = new HdWalletService();
