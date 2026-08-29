import React, { useState, useEffect } from "react";
import ResponsiveNav from "./ResponsiveNav";
import "../styles/layouts.css";

/**
 * AppShell Component
 * Provides responsive application container and navigation structure.
 * Handles all screen sizes: mobile (320+), tablet (768+), desktop (1024+)
 */
export default function AppShell({
  children,
  currentPage,
  onPageChange,
  isAuthenticated,
  user,
  portfolioValue,
  portfolioCurrency,
  portfolioLoading,
  unreadNotifications,
  onNotificationOpen,
  onProfileOpen,
  showAiQuickLaunch,
  onAiAssistantOpen,
}) {
  const [isMobile, setIsMobile] = useState(window.innerWidth < 768);
  const [isTablet, setIsTablet] = useState(window.innerWidth < 1024 && window.innerWidth >= 768);
  const [isDesktop, setIsDesktop] = useState(window.innerWidth >= 1024);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  useEffect(() => {
    const handleResize = () => {
      const width = window.innerWidth;
      setIsMobile(width < 768);
      setIsTablet(width >= 768 && width < 1024);
      setIsDesktop(width >= 1024);
      
      // Auto-collapse sidebar on tablet/smaller desktop
      if (width < 1280) {
        setSidebarCollapsed(true);
      }
    };

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  return (
    <div className={`app-shell ${isDesktop && sidebarCollapsed ? "sidebar-collapsed" : ""}`}>
      {/* Desktop/Tablet Sidebar Navigation */}
      {isDesktop && (
        <ResponsiveNav
          currentPage={currentPage}
          onPageChange={onPageChange}
          variant="sidebar"
          user={user}
          isCollapsed={sidebarCollapsed}
          onToggleCollapse={() => setSidebarCollapsed(!sidebarCollapsed)}
          onNotificationOpen={onNotificationOpen}
          onProfileOpen={onProfileOpen}
          portfolioValue={portfolioValue}
          portfolioCurrency={portfolioCurrency}
          portfolioLoading={portfolioLoading}
          unreadNotifications={unreadNotifications}
        />
      )}

      {/* Main Content Area */}
      <div className={`app-main ${isDesktop ? "app-main-desktop" : "app-main-mobile"}`}>
        {/* Desktop Header */}
        {isDesktop && (
          <ResponsiveNav
            currentPage={currentPage}
            onPageChange={onPageChange}
            variant="header"
            user={user}
            onNotificationOpen={onNotificationOpen}
            onProfileOpen={onProfileOpen}
            portfolioValue={portfolioValue}
            portfolioCurrency={portfolioCurrency}
            portfolioLoading={portfolioLoading}
            unreadNotifications={unreadNotifications}
            showAiQuickLaunch={showAiQuickLaunch}
            onAiAssistantOpen={onAiAssistantOpen}
          />
        )}

        {/* Mobile Header */}
        {isMobile && (
          <ResponsiveNav
            currentPage={currentPage}
            onPageChange={onPageChange}
            variant="mobile-header"
            user={user}
            onNotificationOpen={onNotificationOpen}
            onProfileOpen={onProfileOpen}
            portfolioValue={portfolioValue}
            portfolioCurrency={portfolioCurrency}
            portfolioLoading={portfolioLoading}
            unreadNotifications={unreadNotifications}
          />
        )}

        {/* Page Content Container */}
        <main className="app-content-container">
          <div className="app-content">
            {children}
          </div>
        </main>

        {/* Mobile Bottom Navigation */}
        {isMobile && (
          <ResponsiveNav
            currentPage={currentPage}
            onPageChange={onPageChange}
            variant="bottom-mobile"
            user={user}
          />
        )}
      </div>
    </div>
  );
}
