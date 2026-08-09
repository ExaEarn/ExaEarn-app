import { useState } from "react";

function ListingModal({ item, onClose }) {
  const [price, setPrice] = useState(item.priceEth.toFixed(2));
  const [currency, setCurrency] = useState("ETH");
  const [isListing, setIsListing] = useState(false);

  const priceValue = Number(price);
  const invalid = Number.isNaN(priceValue) || priceValue <= 0;

  const handleList = async () => {
    if (isListing || invalid) {
      return;
    }
    setIsListing(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 1200));
      onClose();
    } finally {
      setIsListing(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 px-4">
      <div className="nft-modal w-full max-w-md rounded-2xl p-5 sm:p-6">
        <h3 className="font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)]">List NFT for Sale</h3>
        <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">Set price and currency for {item.name}.</p>

        <div className="mt-5 grid gap-4">
          <div>
            <label className="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-[var(--exa-text-secondary)]">Price</label>
            <div className="nft-input-wrap rounded-xl px-4 py-3">
              <input
                type="number"
                min="0"
                step="0.01"
                value={price}
                onChange={(event) => setPrice(event.target.value)}
                className="w-full bg-transparent text-sm text-[var(--exa-text-primary)] outline-none"
              />
            </div>
            {invalid ? <p className="mt-1 text-xs text-rose-300">Enter a valid price.</p> : null}
          </div>

          <div>
            <label className="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-[var(--exa-text-secondary)]">Currency</label>
            <div className="nft-input-wrap rounded-xl px-4 py-3">
              <select value={currency} onChange={(event) => setCurrency(event.target.value)} className="w-full bg-transparent text-sm text-[var(--exa-text-primary)] outline-none">
                <option value="ETH" className="bg-[var(--exa-surface)]">ETH</option>
                <option value="EXA" className="bg-[var(--exa-surface)]">EXA</option>
                <option value="USDT" className="bg-[var(--exa-surface)]">USDT</option>
              </select>
            </div>
          </div>
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
            onClick={handleList}
            disabled={invalid || isListing}
            className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-3 py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)] transition-all duration-300 hover:shadow-[var(--exa-shadow-gold)] disabled:opacity-60"
          >
            {isListing ? "Listing..." : "Confirm Listing"}
          </button>
        </div>
      </div>
    </div>
  );
}

export default ListingModal;
