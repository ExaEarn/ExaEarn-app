# ExaEarn Responsive Architecture Implementation Guide

## Overview

ExaEarn now has a comprehensive responsive architecture system that provides:

- **Mobile-first design** that works on all screen sizes from 320px to 3840px+
- **Desktop sidebar navigation** for 1024px+ screens
- **Mobile bottom navigation** for <768px screens
- **Tablet intermediate layouts** for 768px-1023px screens
- **Automatic content constraints** and responsive spacing
- **Reusable layout primitives** for consistent UI across pages

## Key Components

### AppShell
The main layout container that handles responsive navigation and content area.

**Location:** `apps/web/src/layouts/AppShell.jsx`

**Props:**
- `children` - Page content
- `currentPage` - Active page identifier
- `onPageChange` - Navigation callback
- `user` - Current user object
- `portfolioValue` - Portfolio balance
- `portfolioCurrency` - Balance currency
- `unreadNotifications` - Notification count
- `onNotificationOpen` - Notification callback
- `onProfileOpen` - Profile menu callback

### ResponsiveNav
Navigation component with four variants for different breakpoints.

**Location:** `apps/web/src/layouts/ResponsiveNav.jsx`

**Variants:**
- `sidebar` - Desktop left sidebar navigation (collapsible)
- `header` - Desktop top header with controls
- `mobile-header` - Mobile top bar with logo/notifications
- `bottom-mobile` - Mobile bottom navigation bar

### Layout Utilities
Reusable components for page structure.

**Location:** `apps/web/src/layouts/index.js`

**Available Components:**
- `PageContainer` - Standardized page wrapper with max-width
- `PageHeader` - Page title section with optional actions
- `PageSection` - Grouped content section
- `ResponsiveGrid` - Auto-adapting grid system
- `Card` - Reusable card component
- `SplitPane` - Two-column layout (desktop) / stacked (mobile)
- `ContentCard` - Card with header/content/footer

## Responsive Hooks

### useResponsive()
Detect current viewport and provide responsive helpers.

```javascript
import { useResponsive } from "@/hooks/useResponsive";

function MyComponent() {
  const { isMobile, isTablet, isDesktop, isLargeDesktop, width } = useResponsive();
  
  if (isMobile) {
    return <MobileLayout />;
  }
  
  if (isDesktop) {
    return <DesktopLayout />;
  }
}
```

### useMediaQuery()
Custom media query hook for specific breakpoints.

```javascript
import { useMediaQuery } from "@/hooks/useResponsive";

function MyComponent() {
  const isWide = useMediaQuery("(min-width: 1280px)");
  const isDark = useMediaQuery("(prefers-color-scheme: dark)");
}
```

## CSS System

### Breakpoints
```
xs   - 320px+   (Small mobile)
sm   - 480px+   (Mobile)
md   - 768px+   (Tablet)
lg   - 1024px+  (Desktop)
xl   - 1280px+  (Large desktop)
2xl  - 1536px+  (Extra large)
3xl  - 1920px+  (Ultra wide)
```

### Layout CSS Variables
```css
/* Navigation dimensions */
--sidebar-width: 260px;
--sidebar-width-collapsed: 80px;
--header-height: 64px;
--mobile-header-height: 56px;
--mobile-bottom-nav-height: 74px;

/* Content widths */
--content-max-width: 1440px;
--content-compact-max: 1280px;
--trading-max-width: 1600px;

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

### CSS Classes
```css
/* Container utilities */
.page-container           /* Standard max-width container */
.page-container-compact   /* Narrower constraint (1280px) */
.page-container-full      /* Full width */

/* Grid system */
.responsive-grid-2       /* 2 columns on desktop, 1 on mobile */
.responsive-grid-3       /* 3 columns on desktop, 2 on tablet, 1 on mobile */
.responsive-grid-4       /* 4 columns on desktop, 2 on tablet, 1 on mobile */
.responsive-grid-auto    /* Auto-fit with min-width */

/* Visibility */
.hide-mobile              /* Hidden on mobile (<768px) */
.hide-tablet              /* Hidden on tablet (768-1023px) */
.hide-desktop             /* Hidden on desktop (>1024px) */
```

## Integration Patterns

### Pattern 1: Page with AppShell (Future Integration)
```javascript
import AppShell from "@/layouts/AppShell";
import { PageContainer, PageHeader } from "@/layouts";

export default function MyPage({ onNavigate, user, ... }) {
  return (
    <AppShell
      currentPage={currentPage}
      onPageChange={onNavigate}
      user={user}
      // ... other props
    >
      <PageContainer>
        <PageHeader title="My Page" />
        {/* Page content */}
      </PageContainer>
    </AppShell>
  );
}
```

### Pattern 2: Responsive Grid
```javascript
import { ResponsiveGrid } from "@/layouts";
import { useResponsive } from "@/hooks/useResponsive";

export default function AssetList({ assets }) {
  const viewport = useResponsive();
  
  return (
    <ResponsiveGrid columns="3" gap="lg">
      {assets.map(asset => (
        <AssetCard key={asset.id} asset={asset} />
      ))}
    </ResponsiveGrid>
  );
}
```

### Pattern 3: Split Pane Layout
```javascript
import { SplitPane } from "@/layouts";

export default function TradingPage() {
  return (
    <SplitPane 
      ratio="60-40"
      left={<ChartPanel />}
      right={<OrderPanel />}
    />
  );
}
```

### Pattern 4: Responsive Columns
```javascript
import { getVisibleColumns } from "@/hooks/useResponsive";
import { useResponsive } from "@/hooks/useResponsive";

export default function MarketTable() {
  const viewport = useResponsive();
  const visibleColumns = getVisibleColumns("markets", viewport);
  
  return (
    <table>
      <thead>
        <tr>
          {visibleColumns.map(col => (
            <th key={col}>{formatColumnName(col)}</th>
          ))}
        </tr>
      </thead>
      {/* Table rows */}
    </table>
  );
}
```

## Mobile-First Development

1. **Design mobile first**: Start with mobile layout, then enhance for tablet/desktop
2. **Use CSS media queries**: 
   ```css
   /* Mobile defaults */
   .component { width: 100%; }
   
   /* Tablet and up */
   @media (min-width: 768px) {
     .component { width: 50%; }
   }
   
   /* Desktop */
   @media (min-width: 1024px) {
     .component { width: 33%; }
   }
   ```

3. **Avoid fixed widths**: Use `max-width`, `flex`, and `grid` instead
4. **Respect safe areas**: Use `env(safe-area-inset-*)` for notches
5. **Test across breakpoints**: 
   - 320px (iPhone SE)
   - 375px (iPhone)
   - 390px (iPhone 14+)
   - 430px (Android)
   - 768px (iPad)
   - 1024px (Tablet landscape)
   - 1280px (Small laptop)
   - 1440px (Standard desktop)
   - 1920px (Large desktop)

## Content Constraints

- **Standard pages**: 1440px max-width centered
- **Trading terminals**: 1600px max-width (use available space)
- **Forms/Auth**: 640px max-width centered
- **Compact dashboards**: 1280px max-width

## Spacing Guidelines

| Breakpoint   | Gutter | Padding | Gap  |
|-------------|--------|---------|------|
| Mobile     | 12px   | 16px    | 12px |
| Tablet     | 20px   | 20px    | 16px |
| Desktop    | 24px   | 24px    | 20px |
| Large      | 32px   | 32px    | 24px |

## Typography Scaling

Use `clamp()` for fluid typography:

```css
h1 { font-size: clamp(1.35rem, 2vw, 2rem); }
h2 { font-size: clamp(1.1rem, 1.8vw, 1.5rem); }
body { font-size: clamp(0.9rem, 1vw, 1rem); }
```

## Accessibility Requirements

- Minimum touch target: 44×44px
- Maintain keyboard navigation
- Preserve focus visible states
- Semantic HTML structure
- Adequate color contrast
- Screen reader labels on interactive elements

## Performance Considerations

1. **No duplicate DOM**: Avoid showing/hiding entire page copies
2. **Use CSS for layout changes**: Not JavaScript
3. **Optimize images**: Use responsive images with srcset
4. **Lazy load charts**: They are expensive to render
5. **Virtual scrolling**: For large tables/lists on mobile

## Future Enhancements

- [ ] Desktop sidebar on all applicable pages
- [ ] Tablet-specific optimizations
- [ ] Container queries for component-level responsiveness
- [ ] Animated layout transitions
- [ ] Responsive typography scale improvements
- [ ] Mobile app-like navigation patterns
- [ ] Gesture support for trading controls
- [ ] Dark mode responsive adjustments

## Resources

- **CSS Layout**: `apps/web/src/styles/layouts.css`
- **Tailwind Config**: `apps/web/tailwind.config.js`
- **Hooks**: `apps/web/src/hooks/useResponsive.js`
- **Components**: `apps/web/src/layouts/`

## Questions or Issues?

Refer to the audit document in `/memories/repo/exaearn-responsive-audit.md` for implementation details.
