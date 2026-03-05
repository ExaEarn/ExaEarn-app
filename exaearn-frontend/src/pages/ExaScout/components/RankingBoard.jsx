function RankingBoard({ leaderboard }) {
  return (
    <section className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card">
      <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Ranking Board</p>
      <h2 className="mt-1 text-lg font-semibold text-violet-50">DeFi Talent Leaderboards</h2>

      <div className="mt-4 space-y-4">
        <div>
          <p className="text-xs font-semibold text-violet-100/60">Top Rated Players</p>
          <div className="mt-2 space-y-2">
            {leaderboard.topRated.map((player, index) => (
              <div key={player.name} className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs">
                <span className="text-violet-100/80">
                  #{index + 1} {player.name}
                </span>
                <span className="font-semibold text-auric-300">{player.rating}</span>
              </div>
            ))}
          </div>
        </div>

        <div>
          <p className="text-xs font-semibold text-violet-100/60">Trending Talents</p>
          <div className="mt-2 space-y-2">
            {leaderboard.trending.map((player) => (
              <div key={player.name} className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs">
                <span className="text-violet-100/80">{player.name}</span>
                <span className="font-semibold text-auric-300">{player.score}</span>
              </div>
            ))}
          </div>
        </div>

        <div>
          <p className="text-xs font-semibold text-violet-100/60">Most Viewed Profiles</p>
          <div className="mt-2 space-y-2">
            {leaderboard.mostViewed.map((player) => (
              <div key={player.name} className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs">
                <span className="text-violet-100/80">{player.name}</span>
                <span className="font-semibold text-auric-300">{player.views}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

export default RankingBoard;
