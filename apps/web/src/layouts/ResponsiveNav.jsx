import React, { useState } from "react";
import {
  Home,
  TrendingUp,
  Zap,
  Handshake,
  Coins,
  Wallet,
  MoreHorizontal,
  Bell,
  User,
  Settings,
  LogOut,
  Menu,
  X,
  Sparkles,
} from "lucide-react";
import exaearnLogo from "../assets/images/exaearn-logo.png";

/**
 * ResponsiveNav Component
 * Adapts navigation UI based on breakpoint variant:
 * - sidebar: Desktop left sidebar navigation
 * - header: Desktop top header with controls
 * - mobile-header: Mobile top bar with logo/notifications
 * - bottom-mobile: Mobile bottom navigation bar
 */
export default function ResponsiveNav({
  currentPage,
  onPageChange,
  variant = "mobile-header",
  user,
  isCollapsed = false,
  onToggleCollapse,
  onNotificationOpen,
  onProfileOpen,
  portfolioValue,
  portfolioCurrency,
  portfolioLoading,
  unreadNotifications = 0,
  showAiQuickLaunch = false,
  onAiAssistantOpen,
}) {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navItems = [
    { id: "home", label: "Home", icon: Home, route: "home" },
    { id: "market", label: "Markets", icon: TrendingUp, route: "market" },
    { id: "trade", label: "Trade", icon: Zap, route: "trade" },
    { id: "p2p", label: "P2P", icon: Handshake, route: "p2pMarketplace" },
    { id: "assets", label: "Assets", icon: Wallet, route: "assets" },
  ];

  const secondaryItems = [
    { id: "earn", label: "Earn", icon: Coins, route: "staking" },
    { id: "more", label: "More", icon: MoreHorizontal, route: "more" },
  ];

  const handleNavClick = (route) => {
    onPageChange(route);
    setMobileMenuOpen(false);
  };

  // Desktop Sidebar Navigation
  if (variant === "sidebar") {
    return (
      <nav className={`nav-sidebar ${isCollapsed ? 'nav-sidebar-collapsed' : ''}`}>
        <div className="nav-sidebar-header">
          <div className="nav-logo">
            <img src={exaearnLogo} alt="ExaEarn" className="nav-logo-img" />
            {!isCollapsed && <span>ExaEarn</span>}
          </div>
          {onToggleCollapse && (
            <button className="nav-collapse-toggle" onClick={onToggleCollapse} aria-label="Toggle sidebar">
              <Menu size={20} />
            </button>
          )}
        </div>

        <div className="nav-sidebar-main">
          <div className="nav-section">
            {!isCollapsed && <span className="nav-section-label">Main</span>}
            <div className="nav-items">
              {navItems.map((item) => (
                <button
                  key={item.id}
                  className={`nav-item ${currentPage === item.route ? 'nav-item-active' : ''}`}
                  onClick={() => handleNavClick(item.route)}
                  title={isCollapsed ? item.label : undefined}
                  aria-label={item.label}
                  aria-current={currentPage === item.route ? "page" : undefined}
                >
                  <item.icon size={20} />
                  {!isCollapsed && <span>{item.label}</span>}
                </button>
              ))}
            </div>
          </div>

          <div className="nav-section">
            {!isCollapsed && <span className="nav-section-label">Services</span>}
            <div className="nav-items">
              {secondaryItems.map((item) => (
                <button
                  key={item.id}
                  className={`nav-item ${currentPage === item.route ? 'nav-item-active' : ''}`}
                  onClick={() => handleNavClick(item.route)}
                  title={isCollapsed ? item.label : undefined}
                  aria-label={item.label}
                  aria-current={currentPage === item.route ? "page" : undefined}
                >
                  <item.icon size={20} />
                  {!isCollapsed && <span>{item.label}</span>}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Portfolio Summary for Desktop */}
        {!isCollapsed && user && (
          <div className="nav-sidebar-footer">
            <div className="nav-portfolio">
              <span className="nav-portfolio-label">Portfolio</span>
              <span className="nav-portfolio-value">
                {portfolioLoading ? "..." : `${portfolioCurrency} ${portfolioValue}`}
              </span>
            </div>
            <hr className="nav-divider" />
            <button className="nav-account-link" onClick={onProfileOpen} aria-label="Account settings">
              <User size={18} />
              <span>Account</span>
            </button>
            <button className="nav-account-link" aria-label="Settings">
              <Settings size={18} />
              <span>Settings</span>
            </button>
          </div>
        )}
      </nav>
    );
  }

  // Desktop Header
  if (variant === "header") {
    return (
      <header className="nav-header">
        <div className="nav-header-content">
          <div className="nav-header-left">
            {showAiQuickLaunch && onAiAssistantOpen && (
              <button
                className="nav-ai-quick-launch"
                onClick={onAiAssistantOpen}
                aria-label="Open AI Assistant"
                title="Open AI Assistant"
              >
                <Sparkles size={18} />
                <span>AI Assistant</span>
              </button>
            )}
          </div>

          <div className="nav-header-right">
            <button
              className="nav-header-button nav-notification-button"
              onClick={onNotificationOpen}
              aria-label={`Notifications (${unreadNotifications} unread)`}
              title="Notifications"
            >
              <Bell size={20} />
              {unreadNotifications > 0 && (
                <span className="nav-notification-badge">{unreadNotifications > 9 ? "9+" : unreadNotifications}</span>
              )}
            </button>

            <button
              className="nav-header-button"
              onClick={onProfileOpen}
              aria-label="Account menu"
              title={user?.username || "Account"}
            >
              <User size={20} />
            </button>
          </div>
        </div>
      </header>
    );
  }

  // Mobile Header
  if (variant === "mobile-header") {
    return (
      <header className="nav-mobile-header">
        <div className="nav-mobile-header-content">
          <div className="nav-mobile-logo">
            <img src={exaearnLogo} alt="ExaEarn" />
          </div>

          <div className="nav-mobile-controls">
            <button
              className="nav-mobile-button"
              onClick={onNotificationOpen}
              aria-label={`Notifications (${unreadNotifications} unread)`}
            >
              <Bell size={20} />
              {unreadNotifications > 0 && (
                <span className="nav-notification-badge">{unreadNotifications > 9 ? "9+" : unreadNotifications}</span>
              )}
            </button>

            <button
              className="nav-mobile-button"
              onClick={onProfileOpen}
              aria-label="Account menu"
            >
              <User size={20} />
            </button>
          </div>
        </div>
      </header>
    );
  }

  // Mobile Bottom Navigation
  if (variant === "bottom-mobile") {
    return (
      <nav className="nav-bottom-mobile">
        <div className="nav-bottom-mobile-content">
          {navItems.map((item) => (
            <button
              key={item.id}
              className={`nav-bottom-item ${currentPage === item.route ? 'nav-bottom-item-active' : ''}`}
              onClick={() => handleNavClick(item.route)}
              aria-label={item.label}
              aria-current={currentPage === item.route ? "page" : undefined}
            >
              <item.icon size={24} />
              <span className="nav-bottom-label">{item.label}</span>
            </button>
          ))}
          <button
            className={`nav-bottom-item ${currentPage === "more" ? 'nav-bottom-item-active' : ''}`}
            onClick={() => handleNavClick("more")}
            aria-label="More options"
            aria-current={currentPage === "more" ? "page" : undefined}
          >
            <MoreHorizontal size={24} />
            <span className="nav-bottom-label">More</span>
          </button>
        </div>
      </nav>
    );
  }

  return null;
}
