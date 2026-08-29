# Phase 2 Integration Guide - Responsive Page Wrappers

**Status:** Phase 2 In Progress  
**Created:** 2024  
**Target:** Integrate responsive layout system into high-traffic pages  

## Overview

Phase 2 extends Phase 1's foundation by creating responsive wrappers for existing pages. These wrappers integrate the `AppShell` component, providing automatic navigation, proper layout adaptation, and safe area handling across all breakpoints.

## Architecture

```
App.jsx (unchanged)
  ├─ Auth pages (no changes)
  ├─ Home → ResponsiveDashboard → AppShell
  ├─ Market → ResponsiveMarketPage → AppShell
  ├─ Trade → ResponsiveTradePage → AppShell
  ├─ Assets → ResponsiveAssetsPage → AppShell
  └─ [30+ other pages] (gradual migration)
```

## Key Components Created

### 1. ResponsiveDashboard
**Location:** `apps/web/src/features/dashboard/ResponsiveDashboard.jsx`  
**Purpose:** Home/dashboard page with responsive layout  
**Usage:** Wrap UniversalHome component with AppShell

```jsx
<ResponsiveDashboard
  user={user}
  portfolioValue={portfolioValue}
  portfolioCurrency={portfolioCurrency}
  // ... other props
  onNavigate={setCurrentPage}
/>
```

**Features:**
- ✅ Desktop sidebar navigation
- ✅ Mobile bottom navigation
- ✅ Responsive header (mobile/tablet/desktop variants)
- ✅ Safe area support for notches
- ✅ Notification badge integration
- ✅ Portfolio display
- ✅ AI Assistant quick launch

### 2. ResponsiveMarketPage
**Location:** `apps/web/src/pages/market/ResponsiveMarketPage.jsx`  
**Purpose:** Markets/CryptoMarkets pages with responsive layout  
**Usage:** Wraps the existing Market component

```jsx
<ResponsiveMarketPage
  MarketComponent={Market}
  user={user}
  onBack={() => setCurrentPage("home")}
  onOpenTrade={() => setCurrentPage("trade")}
  onOpenFutures={() => setCurrentPage("futures")}
  onOpenP2P={() => openP2PPage()}
  onOpenCrypto={() => setCurrentPage("cryptoMarkets")}
  onNavigate={setCurrentPage}
/>
```

**Features:**
- ✅ Responsive back button
- ✅ Quick navigation buttons (desktop only)
- ✅ Mobile-first layout
- ✅ Automatic sidebar/bottom-nav switching

### 3. ResponsiveTradePage
**Location:** `apps/web/src/pages/trade/ResponsiveTradePage.jsx`  
**Purpose:** Trading terminal with adaptive layout  
**Usage:** Wraps the existing Trade component

```jsx
<ResponsiveTradePage
  TradeComponent={Trade}
  user={user}
  onBack={() => setCurrentPage("home")}
  onOpenFutures={() => setCurrentPage("futures")}
  onOpenMargin={() => setCurrentPage("margin")}
  onOpenOptions={() => setCurrentPage("options")}
  // ... other props
  onNavigate={setCurrentPage}
/>
```

**Features:**
- ✅ Complex responsive layout for charts and orderbook
- ✅ Stacked layout on mobile, split-pane on desktop
- ✅ Quick navigation for derivative trading
- ✅ Responsive action buttons

### 4. ResponsiveAssetsPage
**Location:** `apps/web/src/pages/Assets/ResponsiveAssetsPage.jsx`  
**Purpose:** Assets/portfolio page with adaptive actions  
**Usage:** Wraps the existing Assets component

```jsx
<ResponsiveAssetsPage
  AssetsComponent={Assets}
  user={user}
  onBack={() => setCurrentPage("home")}
  onOpenSend={() => setCurrentPage("send")}
  onOpenAddFunds={() => setCurrentPage("addFunds")}
  onOpenSwap={() => setCurrentPage("swap")}
  onOpenWithdraw={() => setCurrentPage("withdraw")}
  onNavigate={setCurrentPage}
/>
```

**Features:**
- ✅ Responsive action button layout (stacked on mobile)
- ✅ Column visibility for asset lists
- ✅ Portfolio summary cards
- ✅ Quick access to transfer functions

## Integration Steps

### Step 1: Create Responsive Wrapper
Create a new file in the page directory:

```
pages/
  ├─ YourPage/
  │   ├─ index.jsx (or YourPage.jsx) - EXISTING
  │   └─ ResponsiveYourPage.jsx      - NEW
  └─ ...
```

**Template:**
```jsx
import React from 'react';
import { ChevronLeft } from 'lucide-react';
import AppShell from '../../layouts/AppShell';
import { PageContainer, PageHeader } from '../../layouts';
import { useResponsive } from '../../hooks/useResponsive';
import '../../styles/layouts.css';

function ResponsiveYourPage({
  YourPageComponent,
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
  // Your page-specific props
  ...pageProps
}) {
  const { isMobile, isTablet, isDesktop } = useResponsive();

  return (
    <AppShell
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
      <PageContainer variant="full">
        {/* Header */}
        <div className="flex items-center gap-3 mb-6">
          <button onClick={onBack} className="p-2 hover:bg-slate-700/50 rounded-lg">
            <ChevronLeft size={24} />
          </button>
          <div>
            <h1 className="text-2xl font-bold">Your Page</h1>
            <p className="text-sm text-slate-400">Description</p>
          </div>
        </div>

        {/* Content */}
        {YourPageComponent && (
          <YourPageComponent {...pageProps} onBack={onBack} />
        )}
      </PageContainer>
    </AppShell>
  );
}

export default ResponsiveYourPage;
```

### Step 2: Update App.jsx (Eventually)
Currently, App.jsx is not modified. Future phase will wrap authenticated pages with responsive wrappers. For now, the pages can be integrated gradually as they're converted.

### Step 3: Test at Breakpoints
After creating the wrapper, test at these breakpoints:
- **Mobile:** 390px, 480px (test bottom navigation, stacked layouts)
- **Tablet:** 768px (test intermediate layouts, safe areas)
- **Desktop:** 1024px, 1366px, 1920px (test full layouts, sidebar)

### Step 4: Optimize Layout
Use responsive utilities to optimize the layout:

```jsx
import { useResponsive, getVisibleColumns, ResponsiveGrid } from '../../hooks/useResponsive';

// Hide/show elements based on breakpoint
{!isMobile && <DesktopOnlyElement />}
{isMobile && <MobileOnlyElement />}

// Use responsive grid for items
<ResponsiveGrid variant="auto" gap="lg">
  {items.map(item => <Card key={item.id}>{item}</Card>)}
</ResponsiveGrid>

// Get visible columns for tables
const columns = getVisibleColumns('assets', 'lg');
```

## Common Patterns

### Pattern 1: Responsive Actions
Stack actions vertically on mobile, horizontally on desktop:

```jsx
<div className={isMobile ? 'flex flex-col gap-2' : 'flex gap-3'}>
  <button>Action 1</button>
  <button>Action 2</button>
</div>
```

### Pattern 2: Responsive Headers
Different header styles for different breakpoints:

```jsx
{isMobile && <h1 className="text-lg">Title</h1>}
{isTablet && <h1 className="text-xl">Title</h1>}
{isDesktop && <h1 className="text-2xl">Title</h1>}
```

### Pattern 3: Responsive Content Width
Constrain content on desktop, full width on mobile:

```jsx
<PageContainer variant={isDesktop ? 'content' : 'full'}>
  {/* Content */}
</PageContainer>
```

### Pattern 4: Table Column Visibility
Show different columns based on viewport:

```jsx
const visibleColumns = getVisibleColumns('markets', viewport);
<table>
  <thead>
    <tr>
      {visibleColumns.includes('name') && <th>Name</th>}
      {visibleColumns.includes('price') && <th>Price</th>}
      {visibleColumns.includes('change24h') && <th>24h Change</th>}
      {/* Desktop only */}
      {isDesktop && <th>Volume</th>}
    </tr>
  </thead>
</table>
```

## Responsive Utilities Available

### useResponsive Hook
```jsx
const { 
  isMobile,      // < 768px
  isTablet,      // 768px - 1023px
  isDesktop,     // >= 1024px
  isLargeDesktop, // >= 1280px
  width          // Current viewport width
} = useResponsive();
```

### getVisibleColumns Function
Returns column visibility map for tables based on viewport:
```jsx
const columns = getVisibleColumns('assets', 'lg');
// Returns: ['name', 'balance', 'price', 'change24h', 'volume']
```

### ResponsiveGrid Component
Auto-adapting grid system:
```jsx
<ResponsiveGrid variant="auto" gap="lg">
  {/* Items automatically wrap based on viewport */}
</ResponsiveGrid>
```

### PageContainer Component
Responsive page wrapper:
```jsx
<PageContainer variant="full" | "content" | "content-compact" | "trading" | "form">
  {/* Content */}
</PageContainer>
```

## CSS Custom Properties

Available in `layouts.css`:

```css
/* Breakpoints */
--breakpoint-xs: 320px;
--breakpoint-sm: 480px;
--breakpoint-md: 768px;
--breakpoint-lg: 1024px;
--breakpoint-xl: 1280px;
--breakpoint-2xl: 1536px;
--breakpoint-3xl: 1920px;

/* Navigation dimensions */
--sidebar-width: 260px;
--sidebar-width-collapsed: 80px;
--header-height: 64px;
--header-height-mobile: 56px;
--nav-bottom-mobile: 74px;
--safe-area-top: env(safe-area-inset-top);
--safe-area-bottom: env(safe-area-inset-bottom);

/* Content widths */
--content-max-width: 1440px;
--content-max-width-compact: 1280px;
--trading-max-width: 1600px;
--form-max-width: 640px;

/* Spacing scale */
--space-xs: 4px;
--space-sm: 8px;
--space-md: 12px;
--space-lg: 16px;
--space-xl: 20px;
--space-2xl: 24px;
--space-3xl: 32px;
--space-4xl: 40px;
```

## Performance Tips

1. **Memoize callbacks** - Use `useCallback` to prevent unnecessary re-renders
2. **Lazy load components** - Keep existing lazy loading patterns
3. **Optimize re-renders** - Use `useMemo` for expensive calculations
4. **Test at 3G throttle** - Ensure responsive site loads quickly on slow networks
5. **Use CSS media queries** - Prefer CSS over JS for responsive design

## Accessibility Checklist

- [ ] All interactive elements are keyboard accessible
- [ ] Focus indicators visible on all buttons
- [ ] ARIA labels on navigation buttons
- [ ] Color contrast meets WCAG AA
- [ ] Mobile touch targets 44×44px minimum
- [ ] Safe area respected on all breakpoints
- [ ] Screen reader announcements for dynamic content

## Migration Priority

**Phase 2a (High Priority):**
1. ✅ ResponsiveDashboard - Home page
2. ✅ ResponsiveMarketPage - Markets
3. ✅ ResponsiveTradePage - Trading
4. ✅ ResponsiveAssetsPage - Portfolio

**Phase 2b (Medium Priority):**
- Profile/Settings pages
- Transactions/Rewards pages
- P2P Marketplace
- Staking Dashboard

**Phase 2c (Lower Priority):**
- Ecosystem pages (Agriculture, EdTech, Crowdfunding, etc.)
- Specialized pages (Games, NFT, ExaScout, etc.)
- Support/Help pages

## Testing Checklist

For each page wrapper, verify:

- [ ] Renders without errors at all breakpoints
- [ ] Navigation (back button, page transitions) works
- [ ] AppShell displays correctly (sidebar on desktop, bottom-nav on mobile)
- [ ] Safe areas respected (notches, home indicators)
- [ ] Content doesn't overlap navigation
- [ ] All buttons and links are clickable
- [ ] Forms remain usable on mobile
- [ ] Tables show/hide columns appropriately
- [ ] Images scale properly
- [ ] Performance is good (< 3 second load)

## Troubleshooting

### Issue: AppShell not showing
**Solution:** Make sure `AppShell` wraps your entire content, and `PageContainer` provides padding.

### Issue: Bottom nav overlaps content on mobile
**Solution:** Add bottom padding to `PageContainer`: `pb-24 md:pb-0`

### Issue: Sidebar too wide on tablet
**Solution:** AppShell automatically handles this. Check CSS breakpoints in `layouts.css`.

### Issue: Content jumps between breakpoints
**Solution:** Use `scrollbar-gutter: stable` (already in CSS) to prevent layout shift.

## Next Steps

1. ✅ Create responsive wrappers for all high-priority pages
2. Create modal system with responsive positioning
3. Create responsive form components
4. Update existing shared UI components for responsiveness
5. Full integration testing across devices
6. Performance optimization and polishing

## Resources

- **Architecture Guide:** `RESPONSIVE_ARCHITECTURE.md`
- **Quick Start:** `RESPONSIVE_QUICK_START.md`
- **Example Implementation:** `ResponsiveDashboardExample.jsx`
- **Hooks & Utilities:** `hooks/useResponsive.js`
- **Layout Components:** `layouts/index.js`
- **CSS System:** `styles/layouts.css`
- **Tailwind Config:** `tailwind.config.js`

## Support

For questions or issues with Phase 2 integration:
1. Check this guide's troubleshooting section
2. Review the example implementation
3. Check hook documentation in `useResponsive.js`
4. Review existing responsive wrappers for patterns
5. Check CSS custom properties in `layouts.css`

---

**Status:** Phase 2 Progress - 4/30+ pages created  
**Next:** Create wrappers for remaining pages and integration testing
