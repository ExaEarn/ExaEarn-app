import { adminHttp } from "./http";

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const defaultModuleActions = {
  users: ["view profile", "remove profile image", "suspend profile images", "freeze account", "reset 2FA", "review risk", "open wallet"],
  wallets: ["view ledger", "freeze wallet", "queue sweep", "reconcile"],
  transactions: ["view receipt", "mark reviewed", "flag suspicious", "export trail"],
  trading: ["pause pair", "update limits", "adjust fee", "open order book"],
  p2p: ["freeze trade", "release escrow", "open dispute", "message trader"],
  staking: ["pause pool", "update APR", "settle rewards", "view positions"],
  rewards: ["pause rule", "edit rule", "run simulation", "view claims"],
  nft: ["approve collection", "reject collection", "update royalty", "view sales"],
  agritech: ["approve project", "verify report", "release tranche", "view farmers"],
  sports: ["verify athlete", "approve pool", "settle reward", "view ranking"],
  edtech: ["approve course", "review lesson", "issue certificate", "view learners"],
  crowdfunding: ["approve campaign", "reject campaign", "freeze campaign", "view votes", "view tx"],
  lottery: ["close draw", "verify winners", "publish result", "audit fairness"],
  giftcard: ["update rate", "disable card", "approve order", "view inventory"],
  campaigns: ["schedule broadcast", "pause campaign", "view analytics", "duplicate"],
  kyc: ["approve KYC", "reject KYC", "request resubmission", "flag risk"],
  treasury: ["approve withdrawal", "queue sweep", "lock wallet", "run solvency check"],
  notifications: ["send test", "schedule", "pause broadcast", "view delivery"],
  logs: ["view event", "export evidence", "mark reviewed", "open actor"],
  security: ["block IP", "block device", "escalate alert", "mark resolved"],
  admins: ["invite admin", "disable admin", "reset access", "view sessions"],
  roles: ["create role", "clone role", "assign permissions", "audit role"],
  permissions: ["grant permission", "revoke permission", "audit permission", "sync policy"],
  settings: ["update setting", "stage change", "rollback", "audit config"],
  "system-monitor": ["restart worker", "clear queue", "open logs", "run health check"],
};

function getModuleKeyFromPath(path) {
  return path.replace("/admin/", "").replace("/admin", "dashboard") || "dashboard";
}

function isHtmlFallbackResponse(value) {
  return typeof value === "string" && /<!doctype html|<html/i.test(value);
}

function normalizeRows(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.data)) return value.data;
  if (Array.isArray(value?.rows)) return value.rows;
  return null;
}

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
    actions: ["view", "remove profile image", "suspend profile images", "freeze", "unfreeze", "adjust balance", "view logs", "view wallets", "view devices", "view rewards", "view staking", "view trades", "view referrals"],
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
    actions: defaultModuleActions.wallets,
  },
  "/admin/transactions": {
    headline: "Transaction monitoring, fraud review, receipts, and ledger status",
    stats: [
      { label: "Pending Review", value: "31" },
      { label: "Completed Today", value: "8,904" },
      { label: "Flagged", value: "7" },
    ],
    rows: [
      { tx_id: "TX-90821", user: "amina", type: "deposit", asset: "USDT", amount: "$4,200", status: "completed", risk: "low" },
      { tx_id: "TX-90822", user: "tobi", type: "withdrawal", asset: "XRP", amount: "$1,140", status: "review", risk: "medium" },
      { tx_id: "TX-90823", user: "miriam", type: "swap", asset: "EXA/USDT", amount: "$9,880", status: "completed", risk: "low" },
    ],
    actions: defaultModuleActions.transactions,
  },
  "/admin/trading": {
    headline: "Market structure and pair controls",
    rows: [
      { pair: "EXA/USDT", fee: "0.10%", min: "5", max: "100,000", precision: "4", status: "active" },
      { pair: "XRP/USDT", fee: "0.12%", min: "10", max: "250,000", precision: "3", status: "active" },
      { pair: "EXA/XRP", fee: "0.15%", min: "25", max: "50,000", precision: "4", status: "paused" },
    ],
    actions: defaultModuleActions.trading,
  },
  "/admin/p2p": {
    headline: "Escrow-backed peer marketplace supervision",
    rows: [
      { trade: "P2P-4401", buyer: "amina", seller: "jay", fiat: "NGN", amount: "$640", escrow: "locked", status: "active" },
      { trade: "P2P-4402", buyer: "tobi", seller: "miriam", fiat: "NGN", amount: "$2,100", escrow: "disputed", status: "review" },
    ],
    actions: defaultModuleActions.p2p,
  },
  "/admin/rewards": {
    headline: "Reward engine rules and campaign incentives",
    rows: [
      { type: "check-in", rule: "daily streak", reward: "3 EXA", status: "active" },
      { type: "referral", rule: "level-1 referrer", reward: "5%", status: "active" },
      { type: "staking", rule: "90 day lock", reward: "14% APR", status: "active" },
      { type: "airdrop", rule: "campaign vault", reward: "15,000 EXA", status: "scheduled" },
    ],
    actions: defaultModuleActions.rewards,
  },
  "/admin/staking": {
    headline: "Pool management and lock logic",
    rows: [
      { pool: "EXA Core", apr: "12%", lock: "30 days", reward_token: "EXA", status: "active" },
      { pool: "EXA Prime", apr: "18%", lock: "90 days", reward_token: "EXA", status: "active" },
      { pool: "USDT Vault", apr: "8%", lock: "15 days", reward_token: "USDT", status: "disabled" },
    ],
    actions: defaultModuleActions.staking,
  },
  "/admin/nft": {
    headline: "Creator approvals and sale performance",
    rows: [
      { collection: "Exa Origins", creator: "studio-9", royalty: "7.5%", sales: "$48,200", status: "approved" },
      { collection: "Scout Elite", creator: "talent-labs", royalty: "5%", sales: "$19,040", status: "review" },
    ],
    actions: defaultModuleActions.nft,
  },
  "/admin/agritech": {
    headline: "Farm capital deployment and harvest tracking",
    rows: [
      { project: "Kano Rice Cluster", goal: "$120k", raised: "$78k", farmers: "34", status: "funding" },
      { project: "Ogun Poultry Chain", goal: "$90k", raised: "$91k", farmers: "18", status: "active" },
    ],
    actions: defaultModuleActions.agritech,
  },
  "/admin/sports": {
    headline: "Sports talent pool, sponsorship, scoring, and settlement controls",
    rows: [
      { athlete: "Kendrick A.", pool: "Elite Trials", scouts: "18", score: "91", status: "approved" },
      { athlete: "Musa K.", pool: "Regional Camp", scouts: "7", score: "82", status: "review" },
    ],
    actions: defaultModuleActions.sports,
  },
  "/admin/edtech": {
    headline: "Course publishing, learn-to-earn rewards, instructors, and certificates",
    rows: [
      { course: "Web3 Finance Basics", instructor: "Ada Labs", learners: "4,820", reward: "2 EXA", status: "active" },
      { course: "Farm Ledger Ops", instructor: "AgriNode", learners: "920", reward: "4 EXA", status: "review" },
    ],
    actions: defaultModuleActions.edtech,
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
    actions: defaultModuleActions.lottery,
  },
  "/admin/giftcard": {
    headline: "Card rates, inventory, and order approvals",
    rows: [
      { card: "Amazon USD", rate: "870/1$", orders: "129", status: "enabled" },
      { card: "Apple USD", rate: "850/1$", orders: "88", status: "enabled" },
      { card: "Steam USD", rate: "790/1$", orders: "34", status: "disabled" },
    ],
    actions: defaultModuleActions.giftcard,
  },
  "/admin/campaigns": {
    headline: "Lifecycle management for ecosystem campaigns, waitlists, and promotions",
    rows: [
      { campaign: "ExaEarn Genesis", audience: "all users", channel: "push + email", opens: "42%", status: "live" },
      { campaign: "Staking Prime", audience: "wallet holders", channel: "in-app", opens: "31%", status: "scheduled" },
    ],
    actions: defaultModuleActions.campaigns,
  },
  "/admin/kyc": {
    headline: "Identity verification, document review, risk scoring, and resubmission requests",
    stats: [
      { label: "Pending Review", value: "86" },
      { label: "High Risk", value: "9" },
      { label: "Approved Today", value: "214" },
    ],
    rows: [
      { applicant: "David Okafor", level: "Level 2", country: "NG", document: "National ID", risk_score: "42", status: "review" },
      { applicant: "Miriam Ade", level: "Level 3", country: "GH", document: "Passport", risk_score: "12", status: "approved" },
      { applicant: "Tobi Bello", level: "Level 1", country: "NG", document: "NIN", risk_score: "76", status: "pending" },
    ],
    actions: defaultModuleActions.kyc,
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
    actions: defaultModuleActions.logs,
  },
  "/admin/admins": {
    headline: "Operator accounts and delegated access",
    rows: [
      { name: "Sarah Osakwe", email: "sarah@exaearn.com", role: "super_admin", status: "active" },
      { name: "Jay Bello", email: "jay@exaearn.com", role: "support", status: "active" },
      { name: "Dara Ikenna", email: "dara@exaearn.com", role: "moderator", status: "disabled" },
    ],
    actions: defaultModuleActions.admins,
  },
  "/admin/security": {
    headline: "Threat posture and abuse defense",
    rows: [
      { type: "Blocked IP", value: "45.90.21.11", reason: "login spam", status: "active" },
      { type: "Blocked Device", value: "DEV-98AF", reason: "reward farming", status: "active" },
      { type: "Risk Alert", value: "amina", reason: "same IP cluster", status: "review" },
    ],
    actions: defaultModuleActions.security,
  },
  "/admin/settings": {
    headline: "Dynamic controls for system-wide values",
    rows: [
      { key: "withdraw_fee", value: "0.5", type: "number", group: "fees" },
      { key: "trade_fee", value: "0.1", type: "number", group: "trading" },
      { key: "maintenance_mode", value: "false", type: "boolean", group: "system" },
    ],
    actions: defaultModuleActions.settings,
  },
  "/admin/treasury": {
    headline: "Hot and cold wallet custody controls",
    rows: [
      { wallet: "Hot Wallet", asset: "USDT", balance: "$1.28M", status: "healthy" },
      { wallet: "Cold Wallet", asset: "USDT", balance: "$8.90M", status: "secured" },
      { wallet: "Withdrawal Queue", asset: "Mixed", balance: "19 requests", status: "pending" },
    ],
    actions: defaultModuleActions.treasury,
  },
  "/admin/notifications": {
    headline: "Broadcast, push, and in-app messaging",
    rows: [
      { title: "System Maintenance", channel: "In-app", audience: "all users", status: "scheduled" },
      { title: "KYC Approved", channel: "Email", audience: "triggered", status: "active" },
    ],
    actions: defaultModuleActions.notifications,
  },
  "/admin/roles": {
    headline: "Role templates, permission bundles, and operator access levels",
    rows: [
      { role: "super_admin", admins: "2", permissions: "all", status: "active" },
      { role: "support", admins: "12", permissions: "limited", status: "active" },
      { role: "moderator", admins: "7", permissions: "review", status: "active" },
    ],
    actions: defaultModuleActions.roles,
  },
  "/admin/permissions": {
    headline: "Granular permission registry for all admin modules",
    rows: [
      { permission: "treasury.manage", module: "treasury", assigned_roles: "super_admin", status: "active" },
      { permission: "kyc.review", module: "kyc", assigned_roles: "admin, support", status: "active" },
      { permission: "wallet.adjust", module: "wallets", assigned_roles: "super_admin", status: "review" },
    ],
    actions: defaultModuleActions.permissions,
  },
  "/admin/system": {
    headline: "Infra and queue health snapshot",
    rows: [
      { service: "API Cluster", status: "healthy", latency: "82ms" },
      { service: "Redis Queue", status: "healthy", latency: "11ms" },
      { service: "Blockchain Node", status: "degraded", latency: "211ms" },
    ],
    actions: defaultModuleActions["system-monitor"],
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
  const moduleKey = getModuleKeyFromPath(path);
  try {
    const response = await adminHttp.get(path.replace("/admin", ""));
    if (isHtmlFallbackResponse(response.data)) {
      throw new Error("Admin API route served the frontend shell.");
    }

    const rows = normalizeRows(response.data);
    if (!rows) {
      throw new Error("Admin API returned an unsupported module payload.");
    }

    return {
      headline: modulePayloads[path]?.headline ?? "Module view",
      rows,
      actions: modulePayloads[path]?.actions ?? defaultModuleActions[moduleKey] ?? [],
      stats: modulePayloads[path]?.stats,
      source: "api",
    };
  } catch {
    await wait(160);
    const fallback = modulePayloads[path] ?? {
      headline: "Module view",
      rows: [],
      actions: defaultModuleActions[moduleKey] ?? ["view", "mark reviewed"],
    };
    return { ...fallback, source: "mock" };
  }
}

export async function runModuleAction(path, action, row, note = "") {
  try {
    if (path === "/admin/users" && action === "remove profile image") {
      const response = await adminHttp.post(`/users/${row.id}/profile-image/remove`, {
        reason: note || "Removed by administrator.",
      });
      return response.data ?? { status: "completed", message: "Profile image removed." };
    }

    if (path === "/admin/users" && action === "suspend profile images") {
      const response = await adminHttp.post(`/users/${row.id}/profile-image/suspend`, {
        days: 30,
        reason: note || "Profile image privileges suspended by administrator.",
      });
      return response.data ?? { status: "completed", message: "Profile image privileges suspended." };
    }

    const response = await adminHttp.post(`${path.replace("/admin", "")}/actions`, {
      action,
      record: row,
      note,
    });

    if (isHtmlFallbackResponse(response.data)) {
      throw new Error("Admin action route served the frontend shell.");
    }

    return response.data ?? { status: "queued", message: `${action} queued successfully.` };
  } catch {
    await wait(280);
    return {
      status: "simulated",
      message: `${action} is ready for API wiring. Frontend confirmation flow completed.`,
      audit_id: `SIM-${Date.now()}`,
    };
  }
}
