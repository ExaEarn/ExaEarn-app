/**
 * ResponsiveMarketPage.jsx
 * 
 * Responsive wrapper for the market/crypto markets pages.
 * This component wraps the existing Market component with the responsive layout system.
 * 
 * Demonstrates:
 * - Responsive navigation integration
 * - Adaptive content area sizing
 * - Proper safe area handling
 * - Mobile-first layout patterns
 */

import React, { useCallback } from 'react';
import { ChevronLeft, MoreVertical } from 'lucide-react';
import AppShell from '../../layouts/AppShell';
import { PageContainer } from '../../layouts';
import { useResponsive } from '../../hooks/useResponsive';
import '../../styles/layouts.css';

/**
 * ResponsiveMarketPage
 * 
 * Wraps the Market component with responsive layout and navigation.
 * 
 * @component
 * @prop {React.Component} MarketComponent - The actual Market page component to render
 * @prop {Object} user - User object
 * @prop {string} portfolioValue - Portfolio value for sidebar
 * @prop {string} portfolioCurrency - Portfolio currency
 * @prop {number} unreadNotifications - Unread notification count
 * @prop {Function} onBack - Callback when back button pressed
 * @prop {Function} onNavigate - Navigation callback
 * @prop {Function} onNotificationOpen - Notification callback
 * @prop {boolean} showAiQuickLaunch - Show AI assistant button
 * @prop {Function} onAiAssistantOpen - AI assistant callback
 * @prop {...props} MarketComponent props - All props for the Market component
 */
function ResponsiveMarketPage({
  MarketComponent,
  user,
  portfolioValue,
  portfolioCurrency,
  portfolioLoading,
  unreadNotifications,
  onBack,
  onNavigate,
  onNotificationOpen,
  showAiQuickLaunch,
  onAiAssistantOpen,
  // Market-specific props
  onOpenTrade,
  onOpenFutures,
  onOpenP2P,
  onOpenCrypto,
  ...marketProps
}) {
  const { isMobile } = useResponsive();

  const handleNavigateToTrade = useCallback(() => {
    onOpenTrade?.();
  }, [onOpenTrade]);

  const handleNavigateToFutures = useCallback(() => {
    onOpenFutures?.();
  }, [onOpenFutures]);

  const handleNavigateToP2P = useCallback(() => {
    onOpenP2P?.();
  }, [onOpenP2P]);

  const handleNavigateToCrypto = useCallback(() => {
    onOpenCrypto?.();
  }, [onOpenCrypto]);

  return (
    <AppShell
      currentPage="market"
      onPageChange={onNavigate}
      user={user}
      portfolioValue={portfolioValue}
      portfolioCurrency={portfolioCurrency}
      portfolioLoading={portfolioLoading}
      unreadNotifications={unreadNotifications}
      onNotificationOpen={onNotificationOpen}
      onProfileOpen={() => onNavigate?.('profile')}
      showAiQuickLaunch={showAiQuickLaunch}
      onAiAssistantOpen={onAiAssistantOpen}
    >
      {/* Page header with responsive back button */}
      <PageContainer full className="pt-4 md:pt-6">
        <div className="flex items-center justify-between mb-6 md:mb-8">
          <div className="flex items-center gap-4">
            <button
              onClick={onBack}
              className="p-2 hover:bg-slate-700/50 rounded-lg transition-colors"
              aria-label="Go back"
            >
              <ChevronLeft size={24} className="text-slate-400" />
            </button>
            <div>
              <h1 className={`font-bold text-white ${isMobile ? 'text-lg' : 'text-2xl'}`}>
                Markets
              </h1>
              <p className={`text-slate-400 ${isMobile ? 'text-xs' : 'text-sm'}`}>
                {isMobile ? 'View & trade' : 'View market data and place trades'}
              </p>
            </div>
          </div>

          {/* Quick action buttons - responsive visibility */}
          {!isMobile && (
            <div className="flex items-center gap-2">
              <button
                onClick={handleNavigateToP2P}
                className="px-4 py-2 bg-exa-gold/20 hover:bg-exa-gold/30 text-exa-gold rounded-lg transition-colors text-sm"
              >
                P2P
              </button>
              <button
                onClick={handleNavigateToFutures}
                className="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 rounded-lg transition-colors text-sm"
              >
                Futures
              </button>
              <button
                onClick={handleNavigateToTrade}
                className="px-4 py-2 bg-green-500/20 hover:bg-green-500/30 text-green-400 rounded-lg transition-colors text-sm"
              >
                Trade
              </button>
            </div>
          )}

          {isMobile && (
            <button
              onClick={() => {}}
              className="p-2 hover:bg-slate-700/50 rounded-lg transition-colors"
              aria-label="More options"
            >
              <MoreVertical size={20} className="text-slate-400" />
            </button>
          )}
        </div>

        {/* Main content */}
        <div className={`${isMobile ? '' : 'max-w-content'}`}>
          {MarketComponent && (
            <MarketComponent
              {...marketProps}
              onOpenTrade={handleNavigateToTrade}
              onOpenFutures={handleNavigateToFutures}
              onOpenP2P={handleNavigateToP2P}
              onOpenCrypto={handleNavigateToCrypto}
              onBack={onBack}
            />
          )}
        </div>
      </PageContainer>
    </AppShell>
  );
}

export default ResponsiveMarketPage;
