import { useState, useEffect } from 'react';
import { X, ArrowUpDown } from 'lucide-react';

function TransferModal({ isOpen, onClose, onTransfer }) {
  const [fromWallet, setFromWallet] = useState('funding');
  const [toWallet, setToWallet] = useState('spot');
  const [asset, setAsset] = useState('USDT');
  const [amount, setAmount] = useState('');
  const [availableBalance, setAvailableBalance] = useState('0');

  const wallets = ['funding', 'spot', 'futures'];
  const assets = ['USDT', 'BTC', 'XRP'];

  useEffect(() => {
    if (fromWallet === toWallet) {
      setToWallet(wallets.find(w => w !== fromWallet));
    }
    // Fetch available balance for fromWallet and asset
    // For now, mock
    setAvailableBalance('1000.00');
  }, [fromWallet, toWallet, asset]);

  const handleSwap = () => {
    const temp = fromWallet;
    setFromWallet(toWallet);
    setToWallet(temp);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    onTransfer({ from_wallet: fromWallet, to_wallet: toWallet, asset, amount });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-2xl border border-violet-300/20 bg-[#110a20]/95 p-6 shadow-2xl">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold text-white">Transfer Funds</h2>
          <button onClick={onClose} className="text-violet-300 hover:text-white">
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-violet-200 mb-1">From Wallet</label>
            <select
              value={fromWallet}
              onChange={(e) => setFromWallet(e.target.value)}
              className="w-full rounded-lg border border-violet-300/20 bg-violet-500/10 px-3 py-2 text-white focus:border-amber-300/50 focus:outline-none"
            >
              {wallets.map(wallet => (
                <option key={wallet} value={wallet} disabled={wallet === toWallet}>
                  {wallet.charAt(0).toUpperCase() + wallet.slice(1)} Wallet
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center justify-center">
            <button
              type="button"
              onClick={handleSwap}
              className="rounded-full border border-violet-300/20 bg-violet-500/10 p-2 text-violet-300 hover:text-amber-200"
            >
              <ArrowUpDown className="h-4 w-4" />
            </button>
          </div>

          <div>
            <label className="block text-sm font-medium text-violet-200 mb-1">To Wallet</label>
            <select
              value={toWallet}
              onChange={(e) => setToWallet(e.target.value)}
              className="w-full rounded-lg border border-violet-300/20 bg-violet-500/10 px-3 py-2 text-white focus:border-amber-300/50 focus:outline-none"
            >
              {wallets.map(wallet => (
                <option key={wallet} value={wallet} disabled={wallet === fromWallet}>
                  {wallet.charAt(0).toUpperCase() + wallet.slice(1)} Wallet
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-violet-200 mb-1">Asset</label>
            <select
              value={asset}
              onChange={(e) => setAsset(e.target.value)}
              className="w-full rounded-lg border border-violet-300/20 bg-violet-500/10 px-3 py-2 text-white focus:border-amber-300/50 focus:outline-none"
            >
              {assets.map(a => <option key={a} value={a}>{a}</option>)}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-violet-200 mb-1">Amount</label>
            <input
              type="number"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="0.00"
              className="w-full rounded-lg border border-violet-300/20 bg-violet-500/10 px-3 py-2 text-white focus:border-amber-300/50 focus:outline-none"
              step="0.01"
            />
            <p className="mt-1 text-xs text-violet-300">Available: {availableBalance} {asset}</p>
          </div>

          <button
            type="submit"
            className="w-full rounded-lg bg-amber-500 px-4 py-2 font-semibold text-black hover:bg-amber-400 focus:outline-none"
          >
            Transfer Now
          </button>
        </form>
      </div>
    </div>
  );
}

export default TransferModal;