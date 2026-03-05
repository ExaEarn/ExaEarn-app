function UtilityCard({ icon, title, description }) {
  return (
    <article className="token-card group rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-cosmic-glow">
      <div className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-auric-400/40 bg-cosmic-900/65 text-auric-400 transition-colors duration-300 group-hover:border-auric-300/70 group-hover:text-auric-300">
        {icon}
      </div>
      <h3 className="mt-4 text-lg font-semibold text-violet-50">{title}</h3>
      <p className="mt-2 text-sm leading-relaxed text-violet-100/70">{description}</p>
    </article>
  );
}

export default UtilityCard;
