import React, { useState } from "react";
import AppShell from "./AppShell";

/**
 * AuthenticatedAppWrapper
 * Provides AppShell layout for authenticated users.
 * Wraps the page component with responsive navigation and layout.
 */
export default function AuthenticatedAppWrapper({
  children,
  currentPage,
  onPageChange,
  user,
  portfolioValue,
  portfolioCurrency,
  portfolioLoading,
  unreadNotifications = 0,
  onNotificationOpen,
  onProfileOpen,
  showAiQuickLaunch = false,
  onAiAssistantOpen,
}) {
  return (
    <AppShell
      currentPage={currentPage}
      onPageChange={onPageChange}
      isAuthenticated={true}
      user={user}
      portfolioValue={portfolioValue}
      portfolioCurrency={portfolioCurrency}
      portfolioLoading={portfolioLoading}
      unreadNotifications={unreadNotifications}
      onNotificationOpen={onNotificationOpen}
      onProfileOpen={onProfileOpen}
      showAiQuickLaunch={showAiQuickLaunch}
      onAiAssistantOpen={onAiAssistantOpen}
    >
      {children}
    </AppShell>
  );
}
