/**
 * WRAPPER TEMPLATE REFERENCE
 * 
 * Use this template to create new responsive page wrappers.
 * Estimated time to create a new wrapper: 5-10 minutes
 * 
 * Steps:
 * 1. Copy this entire file to: apps/web/src/pages/YourPage/ResponsiveYourPage.jsx
 * 2. Replace all "YourPage" with your actual page name
 * 3. Replace "YourComponent" with the actual page component
 * 4. Adjust the header, buttons, and layout as needed for your page
 * 5. Test at breakpoints: 320px, 768px, 1024px, 1920px
 * 6. Run: npm run build (should complete in 5-6 seconds, zero errors)
 */

import React, { useCallback } from 'react';
import { ChevronLeft } from 'lucide-react';
import AppShell from '../../layouts/AppShell';
import { PageContainer, PageHeader } from '../../layouts';
import { useResponsive } from '../../hooks/useResponsive';
import '../../styles/layouts.css';

/**
 * ResponsiveYourPage
 * 
 * Template wrapper for creating responsive pages.
 * 
 * @component
 * @prop {React.Component} YourComponent - The actual page component to render
 * @prop {Object} user - User object
 * @prop {string} portfolioValue - Portfolio value (for sidebar display)
 * @prop {string} portfolioCurrency - Portfolio currency code
 * @prop {boolean} portfolioLoading - Loading state
 * @prop {number} unreadNotifications - Unread notification count
 * @prop {Function} onBack - Callback when back button is pressed
 * @prop {Function} onNavigate - Navigation callback (page: string) => void
 * @prop {Function} onNotificationOpen - Notification open callback
 * @prop {boolean} showAiQuickLaunch - Show AI assistant quick launch
 * @prop {Function} onAiAssistantOpen - AI assistant callback
 * @prop {...props} All other props are passed to YourComponent
 */
function ResponsiveYourPage({
  YourComponent, // Import your actual component and pass it as prop
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
  // ⬇️ ADD YOUR PAGE-SPECIFIC PROPS HERE
  // Example: onOpenModal, onSelectItem, etc.
  ...pageProps
}) {
  // Get current viewport size
  const { isMobile, isTablet, isDesktop } = useResponsive();

  // ⬇️ ADD YOUR CUSTOM CALLBACKS HERE IF NEEDED
  // Example:
  // const handleCustomAction = useCallback(() => {
  //   doSomething();
  // }, [dependencies]);

  return (
    <AppShell
      // ⬇️ CHANGE "yourPage" to your actual page key (lowercase, camelCase)
      currentPage="yourPage"
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
      {/* Page content container with responsive padding */}
      <PageContainer variant="full" className="pt-4 md:pt-6">
        
        {/* Header with back button and title */}
        <div className="flex items-center gap-3 md:gap-4 mb-6 md:mb-8">
          {/* Back button */}
          <button
            onClick={onBack}
            className="p-2 hover:bg-slate-700/50 rounded-lg transition-colors"
            aria-label="Go back"
          >
            <ChevronLeft size={24} className="text-slate-400" />
          </button>

          {/* Page title and description */}
          <div className="flex-1">
            <h1 className={`font-bold text-white ${isMobile ? 'text-lg' : 'text-2xl'}`}>
              {/* ⬇️ CHANGE THIS TO YOUR PAGE TITLE */}
              Your Page Title
            </h1>
            <p className={`text-slate-400 ${isMobile ? 'text-xs' : 'text-sm'}`}>
              {/* ⬇️ CHANGE THIS TO YOUR PAGE DESCRIPTION */}
              {isMobile ? 'Short description' : 'Longer description of what this page does'}
            </p>
          </div>

          {/* Optional: Quick action buttons (desktop only) */}
          {/* Uncomment and customize if your page needs action buttons */}
          {/* 
          {!isMobile && (
            <div className="flex items-center gap-2">
              <button
                onClick={() => {}}
                className="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 rounded-lg transition-colors text-sm"
              >
                Action 1
              </button>
              <button
                onClick={() => {}}
                className="px-4 py-2 bg-green-500/20 hover:bg-green-500/30 text-green-400 rounded-lg transition-colors text-sm"
              >
                Action 2
              </button>
            </div>
          )}
          */}
        </div>

        {/* Main content area - render your page component */}
        <div>
          {YourComponent && (
            <YourComponent
              // Pass through all page-specific props
              {...pageProps}
              // Include navigation callbacks your component might need
              onBack={onBack}
              onNavigate={onNavigate}
              // ⬇️ ADD ANY CUSTOM CALLBACKS HERE
              // Example: onCustomAction={handleCustomAction}
            />
          )}
        </div>

      </PageContainer>
    </AppShell>
  );
}

export default ResponsiveYourPage;

/**
 * INTEGRATION CHECKLIST
 * 
 * Before committing:
 * ☐ Renamed "YourPage" to your actual page name (e.g., "ProfilePage")
 * ☐ Updated page title and description
 * ☐ Added page-specific props if needed
 * ☐ Added custom callbacks if needed
 * ☐ Updated currentPage key to match your page name
 * ☐ Tested at 320px (mobile)
 * ☐ Tested at 768px (tablet)
 * ☐ Tested at 1024px (desktop)
 * ☐ Tested at 1920px (ultra-wide)
 * ☐ Back button works
 * ☐ No console errors
 * ☐ No TypeScript errors
 * ☐ npm run build succeeds
 * ☐ All buttons are clickable
 * ☐ Layout doesn't overlap navigation
 * ☐ Safe areas respected on mobile
 * ☐ Text is readable at all sizes
 * ☐ Images scale properly
 */

/**
 * COMMON PATTERNS - Add these to your wrapper as needed
 * 
 * PATTERN 1: Responsive Action Buttons
 * ─────────────────────────────────────
 * <div className={isMobile ? 'flex flex-col gap-2' : 'flex gap-3'}>
 *   <button>Action 1</button>
 *   <button>Action 2</button>
 * </div>
 * 
 * PATTERN 2: Conditional Rendering by Breakpoint
 * ───────────────────────────────────────────────
 * {!isMobile && <DesktopOnlyComponent />}
 * {isMobile && <MobileOnlyComponent />}
 * {isTablet && <TabletOptimizedComponent />}
 * 
 * PATTERN 3: Responsive Grid Layout
 * ──────────────────────────────────
 * import { ResponsiveGrid } from '../../layouts';
 * 
 * <ResponsiveGrid variant="auto" gap="lg">
 *   {items.map(item => <Card key={item.id}>{item}</Card>)}
 * </ResponsiveGrid>
 * 
 * PATTERN 4: Table Column Visibility
 * ──────────────────────────────────
 * import { getVisibleColumns } from '../../hooks/useResponsive';
 * 
 * const columns = getVisibleColumns('assets', isDesktop ? 'lg' : 'md');
 * <table>
 *   {columns.includes('name') && <th>Name</th>}
 *   {columns.includes('price') && <th>Price</th>}
 * </table>
 * 
 * PATTERN 5: Safe Area Padding (for notches)
 * ────────────────────────────────────────
 * import { withSafeArea } from '../../hooks/useResponsive';
 * 
 * const padding = withSafeArea('px-4 py-6');
 * <div className={padding}>Content</div>
 * 
 * PATTERN 6: Responsive Typography
 * ─────────────────────────────────
 * <h1 className={isMobile ? 'text-lg' : isTablet ? 'text-xl' : 'text-2xl'}>
 *   Responsive heading
 * </h1>
 * 
 * PATTERN 7: Responsive Form Inputs
 * ────────────────────────────────
 * <input
 *   className="w-full md:w-1/2 lg:w-1/3"
 *   placeholder={isMobile ? 'Short' : 'Longer placeholder'}
 * />
 * 
 * PATTERN 8: Mobile-First Media Queries in CSS
 * ──────────────────────────────────────────
 * /* Mobile first (no media query needed) */
 * .my-component { padding: 1rem; }
 * 
 * /* Then add breakpoint-specific styles */
 * @media (min-width: 768px) { .my-component { padding: 2rem; } }
 * @media (min-width: 1024px) { .my-component { padding: 3rem; } }
 */

/**
 * TESTING CHECKLIST
 * 
 * Viewport Sizes to Test:
 * ✓ 320px   - Small mobile (iPhone SE)
 * ✓ 480px   - Mobile (iPhone 12)
 * ✓ 768px   - Tablet (iPad)
 * ✓ 1024px  - Desktop (MacBook)
 * ✓ 1366px  - Desktop (standard monitor)
 * ✓ 1920px  - Ultra-wide (gaming monitor)
 * 
 * Functionality to Test:
 * ✓ Navigation (back button, page transitions)
 * ✓ AppShell (sidebar on desktop, bottom-nav on mobile)
 * ✓ Headers (responsive text sizing)
 * ✓ Buttons (all clickable, properly sized)
 * ✓ Forms (inputs visible and usable)
 * ✓ Tables (columns show/hide correctly)
 * ✓ Images (scale proportionally)
 * ✓ Safe areas (notches respected)
 * ✓ Scrolling (no horizontal scroll)
 * ✓ Touch targets (44×44px minimum on mobile)
 * 
 * Browser Testing:
 * ✓ Chrome/Edge (Windows, Mac, Android)
 * ✓ Safari (Mac, iOS)
 * ✓ Firefox (Windows, Mac, Linux)
 * 
 * Performance:
 * ✓ Build completes in < 10 seconds
 * ✓ No console errors or warnings
 * ✓ No TypeScript errors
 * ✓ Page loads in < 3 seconds
 */

/**
 * DEPLOYMENT CHECKLIST
 * 
 * Before merging to main:
 * ☐ All tests pass
 * ☐ npm run build succeeds
 * ☐ No console errors in DevTools
 * ☐ Responsive at all breakpoints
 * ☐ Accessibility meets WCAG AA
 * ☐ Documentation updated
 * ☐ Code review approved
 * ☐ Performance metrics acceptable
 * ☐ Tested on actual mobile device
 * ☐ No regressions in existing functionality
 */
