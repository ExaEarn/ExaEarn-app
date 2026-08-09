function Tabs({ items, activeTab, onChange }) {
  return (
    <div className="nft-tabs inline-flex w-full flex-wrap gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-2">
      {items.map((item) => {
        const isActive = item.id === activeTab;
        return (
          <button
            key={item.id}
            type="button"
            onClick={() => onChange(item.id)}
            className={`rounded-xl px-4 py-2 text-sm font-semibold transition-all duration-300 ${
              isActive
                ? "bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)]"
                : "bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] hover:bg-[var(--exa-surface-hover)] hover:text-[var(--exa-text-primary)]"
            }`}
          >
            {item.label}
          </button>
        );
      })}
    </div>
  );
}

export default Tabs;
