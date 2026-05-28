import { Play } from "lucide-react";

function HighlightGallery({ items }) {
  return (
    <section className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Match Highlights</p>
          <h2 className="mt-1 text-lg font-semibold text-violet-50">Verified Performance Clips</h2>
        </div>
        <button className="btn-outline text-xs">Watch Highlights</button>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        {items.map((item) => (
          <article key={item.id} className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-3">
            <div className="flex h-24 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500/40 via-fuchsia-500/10 to-transparent text-auric-300">
              <Play className="h-6 w-6" aria-hidden="true" />
            </div>
            <div className="mt-3">
              <p className="text-sm font-semibold text-violet-50">{item.title}</p>
              <div className="mt-2 grid gap-2 text-xs text-violet-100/70">
                <div className="flex items-center justify-between">
                  <span>Performance</span>
                  <span className="font-semibold text-auric-300">{item.rating}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span>Views</span>
                  <span className="font-semibold text-violet-100">{item.views}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span>Scout Interest</span>
                  <span className="font-semibold text-violet-100">{item.scoutInterest}</span>
                </div>
              </div>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

export default HighlightGallery;
