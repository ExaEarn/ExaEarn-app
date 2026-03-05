function NFTCard({ item, actionLabel, onAction, showOwner = false }) {
  return (
    <article className="nft-item-card group rounded-2xl p-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-cosmic-glow">
      <div className="relative overflow-hidden rounded-xl border border-violet-300/20 bg-cosmic-900/65">
        <img src={item.image} alt={item.name} className="h-44 w-full object-cover transition-transform duration-500 group-hover:scale-105" />
        <span className="absolute left-3 top-3 rounded-full border border-auric-300/40 bg-cosmic-900/70 px-2.5 py-1 text-xs font-semibold text-auric-300">
          {item.category}
        </span>
      </div>

      <div className="mt-4">
        <h3 className="text-base font-semibold text-violet-50">{item.name}</h3>
        <p className="mt-1 text-xs text-violet-100/65">{item.collection}</p>
        {showOwner ? <p className="mt-1 text-xs text-violet-100/65">Owner: {item.owner}</p> : null}
        <div className="mt-4 flex items-center justify-between gap-3">
          <div>
            <p className="text-xs text-violet-100/60">Price</p>
            <p className="text-lg font-semibold text-auric-300">{item.priceEth.toFixed(2)} ETH</p>
          </div>
          <button
            type="button"
            onClick={() => onAction(item)}
            className="rounded-xl border border-auric-300/75 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-3 py-2 text-sm font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.03] hover:shadow-button-glow active:scale-[0.98]"
          >
            {actionLabel}
          </button>
        </div>
      </div>
    </article>
  );
}

export default NFTCard;
