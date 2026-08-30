import { DashboardPage } from "../pages/DashboardPage";
import { ModulePage } from "../pages/ModulePage";
import { ContentManagerPage } from "../pages/ContentManagerPage";
import { FlightGameOperationsPage } from "../pages/FlightGameOperationsPage";
import { NotificationOperationsPage } from "../pages/NotificationOperationsPage";
import { SupportOperationsPage } from "../pages/SupportOperationsPage";
import { CrowdfundingOperationsPage } from "../pages/CrowdfundingOperationsPage";
import { DeveloperProductionAccessPage } from "../pages/DeveloperProductionAccessPage";

export const routeRegistry = {
  "/admin": { key: "dashboard", element: DashboardPage },
  "/admin/dashboard": { key: "dashboard", element: DashboardPage },
  "/admin/users": { key: "users", element: ModulePage },
  "/admin/wallets": { key: "wallets", element: ModulePage },
  "/admin/transactions": { key: "transactions", element: ModulePage },
  "/admin/trading": { key: "trading", element: ModulePage },
  "/admin/exaai": { key: "exaai", element: ModulePage },
  "/admin/listing-center": { key: "listing-center", element: ModulePage },
  "/admin/institutional": { key: "institutional", element: ModulePage },
  "/admin/developer-production": { key: "developer-production", element: DeveloperProductionAccessPage },
  "/admin/otc": { key: "otc", element: ModulePage },
  "/admin/market-makers": { key: "market-makers", element: ModulePage },
  "/admin/margin": { key: "margin", element: ModulePage },
  "/admin/p2p": { key: "p2p", element: ModulePage },
  "/admin/staking": { key: "staking", element: ModulePage },
  "/admin/rewards": { key: "rewards", element: ModulePage },
  "/admin/nft": { key: "nft", element: ModulePage },
  "/admin/agritech": { key: "agritech", element: ModulePage },
  "/admin/edtech": { key: "edtech", element: ModulePage },
  "/admin/crowdfunding": { key: "crowdfunding", element: CrowdfundingOperationsPage },
  "/admin/games-flight": { key: "games-flight", element: FlightGameOperationsPage },
  "/admin/giftcard": { key: "giftcard", element: ModulePage },
  "/admin/exacard": { key: "exacard", element: ModulePage },
  "/admin/exapay": { key: "exapay", element: ModulePage },
  "/admin/campaigns": { key: "campaigns", element: ContentManagerPage },
  "/admin/kyc": { key: "kyc", element: ModulePage },
  "/admin/treasury": { key: "treasury", element: ModulePage },
  "/admin/notifications": { key: "notifications", element: NotificationOperationsPage },
  "/admin/support": { key: "support", element: SupportOperationsPage },
  "/admin/logs": { key: "logs", element: ModulePage },
  "/admin/security": { key: "security", element: ModulePage },
  "/admin/admins": { key: "admins", element: ModulePage },
  "/admin/roles": { key: "roles", element: ModulePage },
  "/admin/permissions": { key: "permissions", element: ModulePage },
  "/admin/settings": { key: "settings", element: ModulePage },
  "/admin/system-monitor": { key: "system-monitor", element: ModulePage },
};
