/**
 * ResponsiveTradePage.jsx
 * 
 * Responsive wrapper for the trading terminal.
 * Handles complex responsive layout needs for:
 * - Order book and charts (side-by-side on desktop, stacked on mobile)
 * - Order entry forms (full width on mobile, sidebar on desktop)
 * - Market data and statistics
 * 
 * This is a more complex example showing how to handle adaptive layouts.
 */

import React, { useCallback, useMemo } from 'react';
import { ChevronLeft, MoreVertical } from 'lucide-react';
import AppShell from '../../layouts/AppShell';
import { PageContainer } from '../../layouts';
import { useResponsive } from '../../hooks/useResponsive';
import '../../styles/layouts.css';

/**
 * ResponsiveTradePage
 * 
 * Wraps the Trade component with responsive layout management.
 * 
 * @component
 * @prop {React.Component} TradeComponent - The Trade page component
 * @prop {Object} user - User object
 * @prop {string} portfolioValue - Portfolio value
 * @prop {string} portfolioCurrency - Portfolio currency
 * @prop {number} unreadNotifications - Unread notification count
 * @prop {Function} onBack - Back callback
 * @prop {Function} onNavigate - Navigation callback
 * @prop {...props} TradeComponent props
 */
function ResponsiveTradePage({
  TradeComponent,
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
  // Trade-specific props
  onOpenConvert,
  onOpenFutures,
  onOpenMargin,
  onOpenOptions,
  onOpenTradFi,
  ...tradeProps
}) {
  const { isMobile, isTablet } = useResponsive();

  // Navigation handlers
  const handleOpenFutures = useCallback(() => onOpenFutures?.(), [onOpenFutures]);
  const handleOpenMargin = useCallback(() => onOpenMargin?.(), [onOpenMargin]);
  const handleOpenOptions = useCallback(() => onOpenOptions?.(), [onOpenOptions]);
  const handleOpenConvert = useCallback(() => onOpenConvert?.(), [onOpenConvert]);

  // Quick nav button styles
  const quickNavButtonClass = useMemo(() => (
    'px-3 md:px-4 py-2 rounded-lg transition-colors text-xs md:text-sm font-medium'
  ), []);

  return (
    <AppShell
      currentPage="trade"
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
      <PageContainer full className="pt-4 md:pt-6">
        {/* Header with navigation */}
        <div className="flex items-center justify-between mb-4 md:mb-6">
          <div className="flex items-center gap-2 md:gap-4">
            <button
              onClick={onBack}
              className="p-2 hover:bg-slate-700/50 rounded-lg transition-colors"
              aria-label="Go back"
            >
              <ChevronLeft size={24} className="text-slate-400" />
            </button>
            <div>
              <h1 className={`font-bold text-white ${isMobile ? 'text-lg' : 'text-2xl'}`}>
                {isMobile ? 'Trade' : 'Trading Terminal'}
              </h1>
              <p className={`text-slate-400 hidden ${isTablet ? 'sm:block' : 'md:block'} text-xs md:text-sm`}>
                Trade spot, futures, and derivatives
              </p>
            </div>
          </div>

          {/* Quick navigation - responsive visibility */}
          {!isMobile && (
            <div className="flex items-center gap-1 md:gap-2 flex-wrap justify-end">
              <button
                onClick={handleOpenConvert}
                className={`${quickNavButtonClass} bg-blue-500/20 hover:bg-blue-500/30 text-blue-400`}
              >
                Convert
              </button>
              <button
                onClick={handleOpenMargin}
                className={`${quickNavButtonClass} bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400`}
              >
                Margin
              </button>
              <button
                onClick={handleOpenFutures}
                className={`${quickNavButtonClass} bg-red-500/20 hover:bg-red-500/30 text-red-400`}
              >
                Futures
              </button>
              <button
                onClick={handleOpenOptions}
                className={`${quickNavButtonClass} bg-purple-500/20 hover:bg-purple-500/30 text-purple-400`}
              >
                Options
              </button>
            </div>
          )}

          {isMobile && (
            <button
              className="p-2 hover:bg-slate-700/50 rounded-lg transition-colors"
              aria-label="More options"
            >
              <MoreVertical size={20} className="text-slate-400" />
            </button>
          )}
        </div>

        {/* Main trading content */}
        <div className={isMobile ? '' : ''}>
          {TradeComponent && (
            <TradeComponent
              {...tradeProps}
              onOpenConvert={handleOpenConvert}
              onOpenFutures={handleOpenFutures}
              onOpenMargin={handleOpenMargin}
              onOpenOptions={handleOpenOptions}
              onOpenTradFi={onOpenTradFi}
              onBack={onBack}
            />
          )}
        </div>
      </PageContainer>
    </AppShell>
  );
}

export default ResponsiveTradePage;
