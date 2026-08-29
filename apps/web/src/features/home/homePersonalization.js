export const homeIntents = {
  trade_invest: { label: "Trade & invest", description: "Markets, buying, trading and intelligent tools.", modules: ["buy_trade", "markets", "smart", "rewards", "services", "promotion"] },
  earn_grow: { label: "Earn & grow", description: "Earn products, rewards and long-term opportunities.", modules: ["services", "rewards", "markets", "buy_trade", "smart", "promotion"] },
  pay_spend: { label: "Pay & spend", description: "Funding, transfers, ExaPay and ExaCard access.", modules: ["services", "buy_trade", "rewards", "markets", "smart", "promotion"] },
  learn_build: { label: "Learn & build", description: "Education, developer tools and guided discovery.", modules: ["smart", "services", "rewards", "markets", "buy_trade", "promotion"] },
  explore_opportunities: { label: "Explore opportunities", description: "Discover the wider ExaEarn ecosystem.", modules: ["services", "promotion", "rewards", "markets", "smart", "buy_trade"] },
  play_earn: { label: "Play & earn", description: "Games, rewards and eligible earning experiences.", modules: ["rewards", "services", "promotion", "markets", "buy_trade", "smart"] },
};

const legacyIntentMap = {
  crypto_exchange: "trade_invest",
  exaai: "trade_invest",
  earn: "earn_grow",
  p2p_payments: "pay_spend",
  giftcards: "pay_spend",
  exaskills: "learn_build",
  education: "learn_build",
  agritech: "explore_opportunities",
  crowdfund: "explore_opportunities",
  nft_marketplace: "explore_opportunities",
  games: "play_earn",
  gaming: "play_earn",
};

export const balancedHomeModules = ["rewards", "buy_trade", "markets", "smart", "services", "promotion"];

export function normalizeIntent(value) {
  if (homeIntents[value]) return value;
  return legacyIntentMap[value] || null;
}

export function normalizeHomePreferences(preferences = {}) {
  const selected = [...new Set((preferences.selected_interests || []).map(normalizeIntent).filter(Boolean))];
  const requestedPrimary = normalizeIntent(preferences.primary_interest);
  const primary = selected.includes(requestedPrimary) ? requestedPrimary : selected[0] || null;
  return {
    ...preferences,
    mode: selected.length ? "personalized" : "all",
    selected_interests: selected,
    primary_interest: primary,
    selected_mode: preferences.selected_mode === "pro" ? "pro" : "lite",
  };
}

export function resolveHomeConfiguration(preferences = {}) {
  const normalized = normalizeHomePreferences(preferences);
  if (!normalized.primary_interest) {
    return { personalized: false, primaryIntent: null, selectedIntents: [], moduleOrder: balancedHomeModules, experienceMode: normalized.selected_mode };
  }

  const ordered = [];
  [normalized.primary_interest, ...normalized.selected_interests.filter((item) => item !== normalized.primary_interest)]
    .forEach((intent) => homeIntents[intent].modules.forEach((module) => {
      if (!ordered.includes(module)) ordered.push(module);
    }));
  balancedHomeModules.forEach((module) => { if (!ordered.includes(module)) ordered.push(module); });

  return {
    personalized: true,
    primaryIntent: normalized.primary_interest,
    selectedIntents: normalized.selected_interests,
    moduleOrder: ordered,
    experienceMode: normalized.selected_mode,
    primaryGoal: normalized.primary_goal || null,
    interests: normalized.interests || [],
  };
}
