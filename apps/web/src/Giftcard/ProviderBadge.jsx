function ProviderBadge({ icon, name }) {
  return (
    <div className="gift-provider inline-flex items-center gap-2 rounded-full border border-violet-300/20 bg-cosmic-900/60 px-3 py-2 text-sm text-violet-100/85">
      <span className="inline-flex h-6 w-6 items-center justify-center rounded-full border border-auric-300/45 bg-cosmic-800/80 text-auric-300">
        {icon}
      </span>
      <span>{name}</span>
    </div>
  );
}

export default ProviderBadge;
