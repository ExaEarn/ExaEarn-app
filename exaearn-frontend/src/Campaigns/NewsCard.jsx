function NewsCard({ item }) {
  return (
    <article className="campaign-card overflow-hidden">
      <div className="relative overflow-hidden rounded-2xl border border-violet-300/20 bg-cosmic-900/50">
        <img src={item.image} alt={item.title} className="h-44 w-full object-cover transition-transform duration-500 hover:scale-105" />
        <div className="absolute left-3 top-3 flex items-center gap-2">
          <span className="rounded-full border border-auric-300/60 bg-cosmic-900/80 px-2.5 py-1 text-xs font-semibold text-auric-300">
            {item.category}
          </span>
          {item.featured ? (
            <span className="rounded-full border border-emerald-300/50 bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-200">
              Featured
            </span>
          ) : null}
        </div>
      </div>

      <div className="mt-4">
        <p className="text-xs font-medium uppercase tracking-[0.12em] text-violet-200/70">{item.date}</p>
        <h3 className="mt-2 text-lg font-semibold text-violet-50">{item.title}</h3>
        <p className="mt-2 text-sm leading-relaxed text-violet-100/75">{item.summary}</p>
      </div>
    </article>
  );
}

export default NewsCard;
