import { adminHttp } from "./http";

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const mockUsers = [
  { id: 101, email: "amina@exaearn.com", username: "amina", balance: "$18,540", status: "active", kyc: "Level 2", created_at: "2026-03-10" },
  { id: 102, email: "tobi@exaearn.com", username: "tobi", balance: "$6,214", status: "frozen", kyc: "Level 1", created_at: "2026-03-14" },
  { id: 103, email: "miriam@exaearn.com", username: "miriam", balance: "$52,901", status: "active", kyc: "Level 3", created_at: "2026-03-18" },
  { id: 104, email: "david@exaearn.com", username: "david", balance: "$1,120", status: "review", kyc: "Pending", created_at: "2026-03-21" },
];

const modulePayloads = {
  "/admin/users": {
    headline: "User command center",
    rows: mockUsers,
    actions: ["view", "freeze", "unfreeze", "adjust balance", "view logs", "view wallets", "view devices", "view rewards", "view staking", "view trades", "view referrals"],
  },
  "/admin/wallets": {
    headline: "Wallet liquidity and address management",
    stats: [
      { label: "Hot Wallets", value: "12" },
      { label: "Frozen Wallets", value: "3" },
      { label: "Pending Sweeps", value: "27" },
    ],
    rows: [
      { asset: "USDT", owner: "amina", balance: "12,400", address: "0x91...a11", status: "active" },
      { asset: "EXA", owner: "miriam", balance: "84,320", address: "0x44...f88", status: "active" },
      { asset: "XRP", owner: "tobi", balance: "7,100", address: "rHf...q1m", status: "frozen" },
    ],
  },
  "/admin/trading": {
    headline: "Market structure and pair controls",
    rows: [
      { pair: "EXA/USDT", fee: "0.10%", min: "5", max: "100,000", precision: "4", status: "active" },
      { pair: "XRP/USDT", fee: "0.12%", min: "10", max: "250,000", precision: "3", status: "active" },
      { pair: "EXA/XRP", fee: "0.15%", min: "25", max: "50,000", precision: "4", status: "paused" },
    ],
  },
  "/admin/rewards": {
    headline: "Reward engine rules and campaign incentives",
    rows: [
      { type: "check-in", rule: "daily streak", reward: "3 EXA", status: "active" },
      { type: "referral", rule: "level-1 referrer", reward: "5%", status: "active" },
      { type: "staking", rule: "90 day lock", reward: "14% APR", status: "active" },
      { type: "airdrop", rule: "campaign vault", reward: "15,000 EXA", status: "scheduled" },
    ],
  },
  "/admin/staking": {
    headline: "Pool management and lock logic",
    rows: [
      { pool: "EXA Core", apr: "12%", lock: "30 days", reward_token: "EXA", status: "active" },
      { pool: "EXA Prime", apr: "18%", lock: "90 days", reward_token: "EXA", status: "active" },
      { pool: "USDT Vault", apr: "8%", lock: "15 days", reward_token: "USDT", status: "disabled" },
    ],
  },
  "/admin/nft": {
    headline: "Creator approvals and sale performance",
    rows: [
      { collection: "Exa Origins", creator: "studio-9", royalty: "7.5%", sales: "$48,200", status: "approved" },
      { collection: "Scout Elite", creator: "talent-labs", royalty: "5%", sales: "$19,040", status: "review" },
    ],
  },
  "/admin/agritech": {
    headline: "Farm capital deployment and harvest tracking",
    rows: [
      { project: "Kano Rice Cluster", goal: "$120k", raised: "$78k", farmers: "34", status: "funding" },
      { project: "Ogun Poultry Chain", goal: "$90k", raised: "$91k", farmers: "18", status: "active" },
    ],
  },
  "/admin/crowdfunding": {
    headline: "Escrow-backed campaigns, governance voting, vendor payouts, and refunds",
    actions: ["approve campaign", "reject campaign", "freeze campaign", "unfreeze campaign", "blacklist user", "view votes", "view tx"],
    stats: [
      { label: "Active Campaigns", value: "48" },
      { label: "Frozen Campaigns", value: "5" },
      { label: "Pending Requests", value: "37" },
    ],
    rows: [
      { campaign: "Solar Cold Room", manager: "0x74...5c9f", investors: "183", raised: "$210k", goal: "$250k", contract: "0x3f...9aa1", status: "approved" },
      { campaign: "Agri Drone Prototype", manager: "0x92...1e77", investors: "74", raised: "$92k", goal: "$120k", contract: "0x6b...02de", status: "review" },
      { campaign: "STEM Pod Network", manager: "0x14...ac92", investors: "211", raised: "$134k", goal: "$150k", contract: "0xa1...3f8c", status: "frozen" },
      { campaign: "Community Water Grid", manager: "0x53...be10", investors: "98", raised: "$61k", goal: "$100k", contract: "0x8c...77a0", status: "active" },
    ],
  },
  "/admin/lottery": {
    headline: "Draw governance and fairness operations",
    rows: [
      { draw: "EXA Mega Draw #41", ticket_price: "$2", prize: "$18k", winners: "14", status: "live" },
      { draw: "Weekend Bonus #17", ticket_price: "$1", prize: "$5k", winners: "8", status: "completed" },
    ],
  },
  "/admin/giftcard": {
    headline: "Card rates, inventory, and order approvals",
    rows: [
      { card: "Amazon USD", rate: "870/1$", orders: "129", status: "enabled" },
      { card: "Apple USD", rate: "850/1$", orders: "88", status: "enabled" },
      { card: "Steam USD", rate: "790/1$", orders: "34", status: "disabled" },
    ],
  },
  "/admin/logs": {
    headline: "Audit stream and operator activity",
    rows: [
      { actor: "admin.sarah", action: "approved withdrawal", type: "treasury", date: "2026-03-28 09:14" },
      { actor: "support.jay", action: "requested resubmit", type: "kyc", date: "2026-03-28 08:42" },
      { actor: "system", action: "rate limit triggered", type: "security", date: "2026-03-28 08:11" },
      { actor: "admin.sarah", action: "froze campaign cmp-114", type: "crowdfunding", date: "2026-03-28 07:55" },
      { actor: "system", action: "vendor payout finalized", type: "crowdfunding.tx", date: "2026-03-28 07:41" },
    ],
  },
  "/admin/admins": {
    headline: "Operator accounts and delegated access",
    rows: [
      { name: "Sarah Osakwe", email: "sarah@exaearn.com", role: "super_admin", status: "active" },
      { name: "Jay Bello", email: "jay@exaearn.com", role: "support", status: "active" },
      { name: "Dara Ikenna", email: "dara@exaearn.com", role: "moderator", status: "disabled" },
    ],
  },
  "/admin/security": {
    headline: "Threat posture and abuse defense",
    rows: [
      { type: "Blocked IP", value: "45.90.21.11", reason: "login spam", status: "active" },
      { type: "Blocked Device", value: "DEV-98AF", reason: "reward farming", status: "active" },
      { type: "Risk Alert", value: "amina", reason: "same IP cluster", status: "review" },
    ],
  },
  "/admin/settings": {
    headline: "Dynamic controls for system-wide values",
    rows: [
      { key: "withdraw_fee", value: "0.5", type: "number", group: "fees" },
      { key: "trade_fee", value: "0.1", type: "number", group: "trading" },
      { key: "maintenance_mode", value: "false", type: "boolean", group: "system" },
    ],
  },
  "/admin/treasury": {
    headline: "Hot and cold wallet custody controls",
    rows: [
      { wallet: "Hot Wallet", asset: "USDT", balance: "$1.28M", status: "healthy" },
      { wallet: "Cold Wallet", asset: "USDT", balance: "$8.90M", status: "secured" },
      { wallet: "Withdrawal Queue", asset: "Mixed", balance: "19 requests", status: "pending" },
    ],
  },
  "/admin/notifications": {
    headline: "Broadcast, push, and in-app messaging",
    rows: [
      { title: "System Maintenance", channel: "In-app", audience: "all users", status: "scheduled" },
      { title: "KYC Approved", channel: "Email", audience: "triggered", status: "active" },
    ],
  },
  "/admin/system": {
    headline: "Infra and queue health snapshot",
    rows: [
      { service: "API Cluster", status: "healthy", latency: "82ms" },
      { service: "Redis Queue", status: "healthy", latency: "11ms" },
      { service: "Blockchain Node", status: "degraded", latency: "211ms" },
    ],
  },
};

export async function fetchAdminBootstrap() {
  try {
    const [me, users, _LOGS, rewards, staking, nft, agri, trading, settings] = await Promise.all([
      adminHttp.get("/me"),
      adminHttp.get("/users"),
      adminHttp.get("/logs"),
      adminHttp.get("/rewards"),
      adminHttp.get("/staking"),
      adminHttp.get("/nft"),
      adminHttp.get("/agritech"),
      adminHttp.get("/trading"),
      adminHttp.get("/settings"),
    ]);

    const userRows = users.data?.data ?? users.data ?? [];
    const rewardRows = rewards.data?.data ?? rewards.data ?? [];
    const nftRows = nft.data?.data ?? nft.data ?? [];
    const agriRows = agri.data?.data ?? agri.data ?? [];
    const tradingRows = trading.data?.data ?? trading.data ?? [];

    return {
      admin: me.data?.data ?? me.data ?? { name: "Admin", email: "admin@exaearn.com", role: "admin" },
      stats: [
        { label: "Users Count", value: `${userRows.length || 0}`, change: "+live" },
        { label: "Active Users", value: `${userRows.filter?.((row) => row.status === "active").length || 0}`, change: "synced" },
        { label: "Total Deposits", value: settings.data?.totals?.deposits ?? "$0", change: "api" },
        { label: "Total Withdrawals", value: settings.data?.totals?.withdrawals ?? "$0", change: "api" },
        { label: "Total Trades", value: `${tradingRows.length || 0}`, change: "api" },
        { label: "Total Rewards", value: `${rewardRows.length || 0}`, change: "api" },
        { label: "Total Staking", value: `${staking.data?.data?.length || staking.data?.length || 0}`, change: "api" },
        { label: "Total NFT Sales", value: `${nftRows.length || 0}`, change: "api" },
        { label: "Total Agri Investment", value: `${agriRows.length || 0}`, change: "api" },
        { label: "Total Lottery Volume", value: settings.data?.totals?.lottery ?? "$0", change: "api" },
        { label: "Total Giftcard Volume", value: settings.data?.totals?.giftcard ?? "$0", change: "api" },
      ],
      charts: {
        userGrowth: userRows.slice(0, 7).map((_, index) => ({ name: `D${index + 1}`, value: (index + 1) * 8 })),
        tradingVolume: tradingRows.slice(0, 7).map((_, index) => ({ name: `D${index + 1}`, value: (index + 1) * 6 })),
        rewards: rewardRows.slice(0, 7).map((_, index) => ({ name: `D${index + 1}`, value: (index + 1) * 4 })),
        revenue: nftRows.slice(0, 7).map((_, index) => ({ name: `D${index + 1}`, value: (index + 1) * 5 })),
      },
      serverStatus: [
        { service: "Laravel API", status: "online" },
        { service: "Queue Workers", status: "online" },
        { service: "Redis", status: "online" },
        { service: "Database", status: "online" },
        { service: "WebSocket", status: "online" },
        { service: "Blockchain Service", status: "online" },
      ],
      permissionsByRole: settings.data?.permissionsByRole ?? {
        super_admin: ["*"],
        admin: ["dashboard.view", "users.view", "wallets.view"],
        moderator: ["dashboard.view", "users.view"],
        support: ["dashboard.view"],
      },
    };
  } catch {
    await wait(220);
  }

  return {
    admin: {
      name: "Sarah Osakwe",
      email: "sarah@exaearn.com",
      role: "super_admin",
    },
    stats: [
      { label: "Users Count", value: "128,440", change: "+8.2%" },
      { label: "Active Users", value: "64,181", change: "+4.7%" },
      { label: "Total Deposits", value: "$24.8M", change: "+11.4%" },
      { label: "Total Withdrawals", value: "$9.2M", change: "+2.1%" },
      { label: "Total Trades", value: "$71.6M", change: "+13.9%" },
      { label: "Total Rewards", value: "$2.4M", change: "+6.3%" },
      { label: "Total Staking", value: "$18.1M", change: "+9.5%" },
      { label: "Total NFT Sales", value: "$1.3M", change: "+4.0%" },
      { label: "Total Agri Investment", value: "$7.9M", change: "+3.4%" },
      { label: "Total Lottery Volume", value: "$940k", change: "+7.1%" },
      { label: "Total Giftcard Volume", value: "$1.7M", change: "+5.9%" },
    ],
    charts: {
      userGrowth: [
        { name: "Mon", value: 18 }, { name: "Tue", value: 24 }, { name: "Wed", value: 29 }, { name: "Thu", value: 34 }, { name: "Fri", value: 44 }, { name: "Sat", value: 53 }, { name: "Sun", value: 61 },
      ],
      tradingVolume: [
        { name: "Mon", value: 12 }, { name: "Tue", value: 20 }, { name: "Wed", value: 18 }, { name: "Thu", value: 27 }, { name: "Fri", value: 32 }, { name: "Sat", value: 29 }, { name: "Sun", value: 37 },
      ],
      rewards: [
        { name: "Mon", value: 8 }, { name: "Tue", value: 13 }, { name: "Wed", value: 11 }, { name: "Thu", value: 15 }, { name: "Fri", value: 18 }, { name: "Sat", value: 22 }, { name: "Sun", value: 21 },
      ],
      revenue: [
        { name: "Mon", value: 14 }, { name: "Tue", value: 18 }, { name: "Wed", value: 16 }, { name: "Thu", value: 20 }, { name: "Fri", value: 27 }, { name: "Sat", value: 31 }, { name: "Sun", value: 35 },
      ],
    },
    serverStatus: [
      { service: "Laravel API", status: "online" },
      { service: "Queue Workers", status: "online" },
      { service: "Redis", status: "online" },
      { service: "Database", status: "online" },
      { service: "WebSocket", status: "warning" },
      { service: "Blockchain Service", status: "online" },
    ],
    permissionsByRole: {
      super_admin: ["*"],
      admin: [
        "dashboard.view", "users.view", "wallets.view", "transactions.view", "trade.manage", "staking.manage", "reward.manage",
        "nft.manage", "agri.manage", "sports.manage", "edtech.manage", "crowdfunding.manage", "lottery.manage", "giftcard.manage",
        "campaign.manage", "kyc.review", "notifications.send", "logs.view", "security.view", "settings.manage", "system.view",
      ],
      moderator: [
        "dashboard.view", "users.view", "transactions.view", "trade.manage", "reward.manage", "staking.manage",
        "nft.manage", "agri.manage", "sports.manage", "edtech.manage", "crowdfunding.manage", "lottery.manage",
        "giftcard.manage", "campaign.manage", "kyc.review", "logs.view", "security.view", "system.view",
      ],
      support: ["dashboard.view", "users.view", "transactions.view", "kyc.review", "logs.view", "notifications.send"],
    },
  };
}

export async function fetchModuleData(path) {
  try {
    const response = await adminHttp.get(path.replace("/admin", ""));
    return {
      headline: modulePayloads[path]?.headline ?? "Module view",
      rows: response.data?.data ?? response.data ?? [],
      actions: modulePayloads[path]?.actions ?? [],
      stats: modulePayloads[path]?.stats,
    };
  } catch {
    await wait(160);
    return modulePayloads[path] ?? { headline: "Module view", rows: [] };
  }
}
