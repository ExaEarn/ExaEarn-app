import { useState } from "react";

function PurchaseModal({ item, onClose }) {
  const [isLoading, setIsLoading] = useState(false);
  const fee = item.priceEth * 0.025;
  const total = item.priceEth + fee;

  const handleConfirm = async () => {
    if (isLoading) {
      return;
    }
    setIsLoading(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 1300));
      onClose();
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 px-4">
      <div className="nft-modal w-full max-w-md rounded-2xl p-5 sm:p-6">
        <h3 className="font-['Sora'] text-2xl font-semibold text-violet-50">Confirm Purchase</h3>
        <img src={item.image} alt={item.name} className="mt-4 h-44 w-full rounded-xl border border-violet-300/25 object-cover" />
        <p className="mt-4 text-base font-semibold text-violet-50">{item.name}</p>
        <div className="mt-4 space-y-2">
          <Row label="NFT Price" value={`${item.priceEth.toFixed(2)} ETH`} />
          <Row label="Marketplace Fee" value={`${fee.toFixed(3)} ETH`} />
          <Row label="Total" value={`${total.toFixed(3)} ETH`} emphasize />
        </div>
        <div className="mt-5 grid grid-cols-2 gap-3">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-violet-300/30 bg-cosmic-900/70 px-3 py-2.5 text-sm font-semibold text-violet-50 transition hover:border-violet-200/60"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleConfirm}
            disabled={isLoading}
            className="rounded-xl border border-auric-300/80 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-3 py-2.5 text-sm font-semibold text-cosmic-900 transition-all duration-300 hover:shadow-button-glow disabled:opacity-60"
          >
            {isLoading ? "Processing..." : "Confirm Purchase"}
          </button>
        </div>
      </div>
    </div>
  );
}

function Row({ label, value, emphasize = false }) {
  return (
    <div className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/55 px-3 py-2.5">
      <span className="text-xs text-violet-100/70">{label}</span>
      <span className={`text-sm font-semibold ${emphasize ? "text-auric-300" : "text-violet-50"}`}>{value}</span>
    </div>
  );
}

export default PurchaseModal;
