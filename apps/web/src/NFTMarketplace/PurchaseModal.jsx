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
        <h3 className="font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)]">Confirm Purchase</h3>
        <img src={item.image} alt={item.name} className="mt-4 h-44 w-full rounded-xl border border-[var(--exa-border)] object-cover" />
        <p className="mt-4 text-base font-semibold text-[var(--exa-text-primary)]">{item.name}</p>
        <div className="mt-4 space-y-2">
          <Row label="NFT Price" value={`${item.priceEth.toFixed(2)} ETH`} />
          <Row label="Marketplace Fee" value={`${fee.toFixed(3)} ETH`} />
          <Row label="Total" value={`${total.toFixed(3)} ETH`} emphasize />
        </div>
        <div className="mt-5 grid grid-cols-2 gap-3">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 py-2.5 text-sm font-semibold text-[var(--exa-text-primary)] transition hover:border-[var(--exa-border-active)]"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleConfirm}
            disabled={isLoading}
            className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-3 py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)] transition-all duration-300 hover:shadow-[var(--exa-shadow-gold)] disabled:opacity-60"
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
    <div className="flex items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2.5">
      <span className="text-xs text-[var(--exa-text-secondary)]">{label}</span>
      <span className={`text-sm font-semibold ${emphasize ? "text-[var(--exa-gold-light)]" : "text-[var(--exa-text-primary)]"}`}>{value}</span>
    </div>
  );
}

export default PurchaseModal;
