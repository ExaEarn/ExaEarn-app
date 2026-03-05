function Tabs({ items, activeTab, onChange }) {
  return (
    <div className="nft-tabs inline-flex w-full flex-wrap gap-2 rounded-2xl border border-violet-300/20 bg-cosmic-900/45 p-2">
      {items.map((item) => {
        const isActive = item.id === activeTab;
        return (
          <button
            key={item.id}
            type="button"
            onClick={() => onChange(item.id)}
            className={`rounded-xl px-4 py-2 text-sm font-semibold transition-all duration-300 ${
              isActive
                ? "bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 text-cosmic-900 shadow-button-glow"
                : "bg-cosmic-900/40 text-violet-100/80 hover:bg-cosmic-900/70 hover:text-violet-50"
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
