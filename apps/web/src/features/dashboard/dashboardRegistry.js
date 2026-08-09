export const dashboardExperienceRegistry = {
  crypto_exchange: { label: 'Crypto Exchange', description: 'Spot and futures trading, asset conversion, markets, orders, and P2P access.', route: 'trade', metric: 'open_orders', metricLabel: 'open orders' },
  exaai: { label: 'ExaAI', description: 'Subscriptions, strategies, capital allocations, sessions, and performance.', route: 'aiAssistant', metric: 'active_sessions', metricLabel: 'active sessions' },
  earn: { label: 'Earn', description: 'Supported staking products, active positions, rewards, and unstaking.', route: 'staking', metric: 'active_positions', metricLabel: 'active positions' },
  giftcards: { label: 'Gift Cards', description: 'Buy supported cards or submit cards and follow settlement status.', route: 'giftcard', metric: 'pending_orders', metricLabel: 'pending orders' },
  games: { label: 'Games', description: 'Play the live Flight game and review your recent bet activity.', route: 'game', metric: 'recent_bets', metricLabel: 'recent bets' },
  exaskills: { label: 'ExaSkills', description: 'Continue courses, track credentials, challenges, and opportunities.', route: 'edtech', metric: 'active_courses', metricLabel: 'courses in progress' },
  crowdfund: { label: 'Crowdfund', description: 'Discover and support community campaigns.', route: 'crowdfunding', availability: 'partial' },
  nft_marketplace: { label: 'NFT Marketplace', description: 'Discover collections and manage owned, minted, and listed assets.', route: 'nftMarketplace', metric: 'owned_assets', metricLabel: 'owned assets' },
  agritech: { label: 'Agritech', description: 'Explore agricultural projects and follow your project participation.', route: 'agriculture', metric: 'active_projects', metricLabel: 'active projects' },
};
export function composeDashboard(preferences, state = {}) {
  if (preferences?.mode !== 'personalized' || !preferences.selected_interests?.length) return [];
  const primary = preferences.primary_interest;
  return [...preferences.selected_interests].filter((key) => dashboardExperienceRegistry[key]).sort((a, b) => a === primary ? -1 : b === primary ? 1 : 0).map((key) => ({ key, ...dashboardExperienceRegistry[key], state: state[key] || {}, primary: key === primary }));
}
