import { useMemo, useState } from "react";
import newsData from "../data/news.json";
import NewsCard from "./NewsCard";

function Campaigns({ onBack }) {
  const categories = useMemo(() => ["All", ...new Set(newsData.map((item) => item.category))], []);
  const [activeCategory, setActiveCategory] = useState("All");

  const filteredAndSorted = useMemo(() => {
    return newsData
      .filter((item) => (activeCategory === "All" ? true : item.category === activeCategory))
      .sort((a, b) => {
        if (a.featured !== b.featured) {
          return a.featured ? -1 : 1;
        }
        return new Date(b.date).getTime() - new Date(a.date).getTime();
      });
  }, [activeCategory]);

  return (
    <div className="min-h-screen text-white exa-bg app-shell">
      <div className="container w-full max-w-sm px-3 pt-4 pb-6 mx-auto sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-4xl xl:max-w-5xl">
        <div className="p-4 shadow-xl glass-card rounded-3xl sm:p-5">
          <header className="flex flex-wrap items-center justify-between gap-3 mb-5 campaign-card">
            <div>
              <h1 className="font-['Sora'] text-3xl font-semibold text-violet-50 sm:text-4xl">Run Campaigns & News</h1>
              <p className="mt-2 text-sm text-violet-100/75 sm:text-base">Stay updated with ecosystem milestones, partnerships, and campaign performance.</p>
            </div>
            {onBack ? (
              <button
                type="button"
                onClick={onBack}
                className="btn-outline"
              >
                Back Home
              </button>
            ) : null}
          </header>

          <section className="mb-5 campaign-card">
            <div className="flex flex-wrap gap-2">
              {categories.map((category) => {
                const active = category === activeCategory;
                return (
                  <button
                    key={category}
                    type="button"
                    onClick={() => setActiveCategory(category)}
                    className={`rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] transition-all duration-300 ${
                      active
                        ? "border-auric-300/70 bg-linear-to-r from-auric-300 via-auric-400 to-auric-500 text-cosmic-900 shadow-button-glow"
                        : "border-violet-300/30 bg-cosmic-900/45 text-violet-100/80 hover:border-auric-300/60 hover:text-auric-200"
                    }`}
                  >
                    {category}
                  </button>
                );
              })}
            </div>
          </section>

          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {filteredAndSorted.map((item) => (
              <NewsCard key={item.id} item={item} />
            ))}
          </section>
        </div>
      </div>
    </div>
  );
}

export default Campaigns;
