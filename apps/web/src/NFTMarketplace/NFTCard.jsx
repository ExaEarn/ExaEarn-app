function NFTCard({ item, actionLabel, onAction, showOwner = false }) {
  return (
    <article className="nft-item-card group rounded-2xl p-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-[var(--exa-shadow-soft)]">
      <div className="relative overflow-hidden rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)]">
        <img src={item.image} alt={item.name} className="h-44 w-full object-cover transition-transform duration-500 group-hover:scale-105" />
        <span className="absolute left-3 top-3 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface)] px-2.5 py-1 text-xs font-semibold text-[var(--exa-gold-light)]">
          {item.category}
        </span>
      </div>

      <div className="mt-4">
        <h3 className="text-base font-semibold text-[var(--exa-text-primary)]">{item.name}</h3>
        <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{item.collection}</p>
        {showOwner ? <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Owner: {item.owner}</p> : null}
        <div className="mt-4 flex items-center justify-between gap-3">
          <div>
            <p className="text-xs text-[var(--exa-text-muted)]">Price</p>
            <p className="text-lg font-semibold text-[var(--exa-gold-light)]">{item.priceEth.toFixed(2)} ETH</p>
          </div>
          <button
            type="button"
            onClick={() => onAction(item)}
            className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-3 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)] transition-all duration-300 hover:scale-[1.03] hover:shadow-[var(--exa-shadow-gold)] active:scale-[0.98]"
          >
            {actionLabel}
          </button>
        </div>
      </div>
    </article>
  );
}

export default NFTCard;
