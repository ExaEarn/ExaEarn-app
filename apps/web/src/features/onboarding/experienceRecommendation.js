export const experienceOptions = [
  { id: "new", label: "New to crypto", description: "Keep the essentials clear and easy to reach." },
  { id: "intermediate", label: "Some experience", description: "Balance simple actions with market discovery." },
  { id: "experienced", label: "Experienced trader", description: "Prioritize markets and advanced trading tools." },
];

export const goalOptions = [
  { id: "buy_trade", label: "Buy and trade crypto", description: "Markets, buying and trading tools." },
  { id: "send_pay", label: "Send and pay", description: "Transfers, payments and card access." },
  { id: "grow_assets", label: "Grow my assets", description: "Earn, staking and rewards." },
  { id: "trade_smarter", label: "Trade with smarter tools", description: "Advanced markets, ExaAI and automation." },
  { id: "p2p", label: "Use P2P", description: "Buy and sell through supported payment methods." },
  { id: "explore", label: "Explore ExaEarn", description: "Discover the wider ExaEarn ecosystem." },
];

export const interestOptions = [
  { id: "trading", label: "Trading" }, { id: "exaai", label: "ExaAI" },
  { id: "earn", label: "Earn & staking" }, { id: "payments", label: "Payments" },
  { id: "p2p", label: "P2P" }, { id: "exacard", label: "ExaCard" },
  { id: "copy_trading", label: "Copy trading" }, { id: "learning", label: "Learning" },
  { id: "rewards", label: "Rewards" }, { id: "ecosystem", label: "More products" },
];

const inferredByGoal = { buy_trade: ["trading"], trade_smarter: ["trading", "exaai"], send_pay: ["payments"], grow_assets: ["earn"], p2p: ["p2p"], explore: ["ecosystem"] };
const legacyIntentByGoal = { buy_trade: "trade_invest", trade_smarter: "trade_invest", send_pay: "pay_spend", p2p: "pay_spend", grow_assets: "earn_grow", explore: "explore_opportunities" };

export function inferInterests(goal) { return inferredByGoal[goal] || ["ecosystem"]; }

export function recommendExperienceMode(experience, goal, interests = []) {
  let lite = 0; let pro = 0;
  if (experience === "experienced") pro += 2;
  if (experience === "new") lite += 2;
  if (goal === "trade_smarter") pro += 4;
  if (goal === "buy_trade") pro += 1;
  if (["send_pay", "grow_assets", "p2p", "explore"].includes(goal)) lite += 3;
  interests.forEach((interest) => {
    if (["exaai", "copy_trading"].includes(interest)) pro += 2;
    if (["payments", "p2p", "exacard", "earn", "rewards", "learning", "ecosystem"].includes(interest)) lite += 1;
  });
  if (interests.includes("trading")) pro += 1;
  return pro > lite ? "pro" : "lite";
}

export function buildDashboardPreferences({ experience, goal, interests, selectedMode }) {
  const resolvedInterests = interests.length ? interests.slice(0, 3) : inferInterests(goal);
  const recommendedMode = recommendExperienceMode(experience, goal, resolvedInterests);
  const legacyIntent = legacyIntentByGoal[goal] || "explore_opportunities";
  return { mode: "personalized", primary_interest: legacyIntent, selected_interests: [legacyIntent], hidden_widgets: [], widget_order: [], experience_level: experience, primary_goal: goal, interests: resolvedInterests, recommended_mode: recommendedMode, selected_mode: selectedMode || recommendedMode, onboarding_version: 4, onboarding_completed: true };
}
