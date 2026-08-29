/**
 * ResponsiveAssetsPage.jsx
 * 
 * Responsive wrapper for the assets/portfolio page.
 * Demonstrates responsive handling of:
 * - Asset lists and tables (column visibility based on viewport)
 * - Action buttons (stacked on mobile, inline on desktop)
 * - Portfolio summary cards (responsive grid)
 */

import React, { useCallback } from 'react';
import { ChevronLeft, Plus, Send, ArrowUp, Repeat2 } from 'lucide-react';
import AppShell from '../../layouts/AppShell';
import { PageContainer } from '../../layouts';
import { useResponsive } from '../../hooks/useResponsive';
import '../../styles/layouts.css';

/**
 * ResponsiveAssetsPage
 * 
 * Wraps the Assets component with responsive layout and quick actions.
 * 
 * @component
 * @prop {React.Component} AssetsComponent - The Assets page component
 * @prop {Object} user - User object
 * @prop {string} portfolioValue - Portfolio value
 * @prop {string} portfolioCurrency - Portfolio currency
 * @prop {number} unreadNotifications - Unread notification count
 * @prop {Function} onBack - Back navigation callback
 * @prop {Function} onNavigate - Main navigation callback
 * @prop {Function} onOpenSend - Navigate to Send page
 * @prop {Function} onOpenAddFunds - Navigate to Add Funds page
 * @prop {Function} onOpenSwap - Navigate to Swap page
 * @prop {Function} onOpenWithdraw - Navigate to Withdraw page
 * @prop {...props} AssetsComponent props
 */
function ResponsiveAssetsPage({
  AssetsComponent,
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
  // Asset-specific props
  onOpenSend,
  onOpenAddFunds,
  onOpenSwap,
  onOpenWithdraw,
  ...assetsProps
}) {
  const { isMobile } = useResponsive();

  // Action handlers
  const handleAddFunds = useCallback(() => onOpenAddFunds?.(), [onOpenAddFunds]);
  const handleSend = useCallback(() => onOpenSend?.(), [onOpenSend]);
  const handleSwap = useCallback(() => onOpenSwap?.(), [onOpenSwap]);
  const handleWithdraw = useCallback(() => onOpenWithdraw?.(), [onOpenWithdraw]);

  // Action button base styles
  const actionButtonClass = 'flex items-center justify-center gap-2 px-4 py-2.5 md:px-3 md:py-2 rounded-lg transition-colors font-medium text-sm md:text-xs';

  const quickActionButtons = (
    <div className={`flex gap-2 flex-wrap ${isMobile ? 'flex-col' : 'flex-row'}`}>
      <button
        onClick={handleAddFunds}
        className={`${actionButtonClass} bg-green-500/20 hover:bg-green-500/30 text-green-400 flex-1 md:flex-none`}
      >
        <Plus size={18} className="md:hidden" />
        <Plus size={16} className="hidden md:block" />
        <span>{isMobile ? 'Add Funds' : 'Deposit'}</span>
      </button>
      <button
        onClick={handleSend}
        className={`${actionButtonClass} bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 flex-1 md:flex-none`}
      >
        <Send size={18} className="md:hidden" />
        <Send size={16} className="hidden md:block" />
        <span>{isMobile ? 'Send' : 'Send'}</span>
      </button>
      <button
        onClick={handleSwap}
        className={`${actionButtonClass} bg-purple-500/20 hover:bg-purple-500/30 text-purple-400 flex-1 md:flex-none`}
      >
        <Repeat2 size={18} className="md:hidden" />
        <Repeat2 size={16} className="hidden md:block" />
        <span>{isMobile ? 'Swap' : 'Convert'}</span>
      </button>
      <button
        onClick={handleWithdraw}
        className={`${actionButtonClass} bg-orange-500/20 hover:bg-orange-500/30 text-orange-400 flex-1 md:flex-none`}
      >
        <ArrowUp size={18} className="md:hidden" />
        <ArrowUp size={16} className="hidden md:block" />
        <span>{isMobile ? 'Withdraw' : 'Withdraw'}</span>
      </button>
    </div>
  );

  return (
    <AppShell
      currentPage="assets"
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
        {/* Header */}
        <div className="mb-6 md:mb-8">
          <div className="flex items-center gap-3 md:gap-4 mb-4 md:mb-6">
            <button
              onClick={onBack}
              className="p-2 hover:bg-slate-700/50 rounded-lg transition-colors"
              aria-label="Go back"
            >
              <ChevronLeft size={24} className="text-slate-400" />
            </button>
            <div className="flex-1">
              <h1 className={`font-bold text-white ${isMobile ? 'text-lg' : 'text-2xl'}`}>
                Assets
              </h1>
              <p className={`text-slate-400 ${isMobile ? 'text-xs' : 'text-sm'}`}>
                {isMobile ? 'Manage your holdings' : 'View and manage your cryptocurrency holdings'}
              </p>
            </div>
          </div>

          {quickActionButtons}
        </div>

        {/* Main content - Assets list and portfolio breakdown */}
        <div>
          {AssetsComponent && (
            <AssetsComponent
              {...assetsProps}
              onOpenSend={handleSend}
              onOpenAddFunds={handleAddFunds}
              onOpenSwap={handleSwap}
              onOpenWithdraw={handleWithdraw}
              onBack={onBack}
            />
          )}
        </div>
      </PageContainer>
    </AppShell>
  );
}

export default ResponsiveAssetsPage;
