# Quick Start Guide: Implementing Responsive Pages

## 5-Minute Integration Guide

This guide shows you how to make any existing ExaEarn page responsive using the new system.

## Step 1: Import Required Components

```javascript
import { PageContainer, PageHeader, ResponsiveGrid, SplitPane } from "@/layouts";
import { useResponsive, getVisibleColumns } from "@/hooks/useResponsive";
```

## Step 2: Add Responsive Hook to Your Page

```javascript
export default function MyPage() {
  const viewport = useResponsive();
  
  // Now you have:
  // - viewport.isMobile (< 768px)
  // - viewport.isTablet (768-1023px)
  // - viewport.isDesktop (>= 1024px)
  // - viewport.width (actual width in px)
}
```

## Step 3: Wrap Content in PageContainer

```javascript
return (
  <PageContainer>
    <PageHeader title="My Page" />
    {/* Your content here */}
  </PageContainer>
);
```

## Step 4: Make Your Grid Responsive

### Before (Fixed 4 columns)
```javascript
<div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)" }}>
  {items.map(item => <Card item={item} />)}
</div>
```

### After (Responsive)
```javascript
<ResponsiveGrid columns="4" gap="lg">
  {items.map(item => <Card item={item} />)}
</ResponsiveGrid>
```

The grid automatically becomes:
- 4 columns on desktop
- 2 columns on tablet
- 1 column on mobile

## Step 5: Adapt Table Columns for Mobile

### Before (Always shows all columns)
```javascript
<table>
  <thead>
    <tr>
      <th>Pair</th>
      <th>Price</th>
      <th>24h Change</th>
      <th>Volume</th>
      <th>Market Cap</th>
      <th>Action</th>
    </tr>
  </thead>
</table>
```

### After (Shows only important columns on mobile)
```javascript
const viewport = useResponsive();
const columns = getVisibleColumns("markets", viewport);

<table>
  <thead>
    <tr>
      {columns.includes("pair") && <th>Pair</th>}
      {columns.includes("price") && <th>Price</th>}
      {columns.includes("24h") && <th>24h Change</th>}
      {columns.includes("volume") && <th>Volume</th>}
      {columns.includes("action") && <th>Action</th>}
    </tr>
  </thead>
</table>
```

Mobile shows: Pair, Price, 24h Change
Tablet shows: Pair, Price, 24h Change, Action
Desktop shows: All columns

## Step 6: Create Responsive Layouts

### Split Pane (2-column on desktop, stacked on mobile)
```javascript
return (
  <SplitPane ratio="60-40" left={<LeftContent />} right={<RightContent />} />
);
```

### Conditional Rendering for Complex Layouts
```javascript
if (viewport.isDesktop) {
  return (
    <div className="grid grid-cols-3 gap-4">
      <Section1 />
      <Section2 />
      <Section3 />
    </div>
  );
}

if (viewport.isTablet) {
  return (
    <div className="grid grid-cols-2 gap-4">
      <Section1 />
      <Section2 />
    </div>
  );
}

return (
  <>
    <Section1 />
    <Section2 />
    <Section3 />
  </>
);
```

## Responsive CSS Patterns

### Pattern 1: Mobile-First Media Queries
```css
/* Mobile first (default) */
.card {
  width: 100%;
  padding: 12px;
}

/* Tablet and up */
@media (min-width: 768px) {
  .card {
    padding: 16px;
  }
}

/* Desktop and up */
@media (min-width: 1024px) {
  .card {
    padding: 20px;
  }
}
```

### Pattern 2: Responsive Font Size
```css
.title {
  font-size: clamp(1.25rem, 2vw, 1.75rem);
  /* Mobile: 1.25rem, Desktop: 1.75rem, scales between */
}
```

### Pattern 3: Responsive Grid
```css
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  /* Auto-fits columns based on available space */
}
```

## Common Tasks

### Hide/Show Elements
```html
<!-- Hidden on mobile, shown on tablet+ -->
<div class="hide-mobile">
  Desktop content
</div>

<!-- Hidden on tablet+, shown on mobile -->
<div class="show-on-mobile">
  Mobile content
</div>
```

### Responsive Margins/Padding
```css
padding: var(--space-lg);  /* 16px on mobile */
@media (min-width: 768px) {
  padding: var(--space-xl); /* 20px on tablet */
}
@media (min-width: 1024px) {
  padding: var(--space-2xl); /* 24px on desktop */
}
```

### Responsive Font Sizes
```css
font-size: var(--text-body-mobile); /* Mobile size */
@media (min-width: 1024px) {
  font-size: var(--text-body-desktop); /* Desktop size */
}
```

## Real-World Example: Converting a Markets Page

### Step 1: Check Current Page Structure
```javascript
export default function Market() {
  return (
    <div className="market-container">
      <div className="market-header">...</div>
      <div className="market-table">...</div>
      <div className="market-sidebar">...</div>
    </div>
  );
}
```

### Step 2: Add Responsive Hook
```javascript
import { useResponsive, getVisibleColumns } from "@/hooks/useResponsive";

export default function Market() {
  const viewport = useResponsive();
  // ...
}
```

### Step 3: Update Layout
```javascript
return (
  <PageContainer>
    <PageHeader title="Markets" />
    
    {viewport.isDesktop ? (
      // 2-column layout on desktop
      <SplitPane 
        ratio="70-30"
        left={<MarketTable viewport={viewport} />}
        right={<Sidebar />}
      />
    ) : (
      // Stacked on mobile/tablet
      <>
        <MarketTable viewport={viewport} />
        <Sidebar />
      </>
    )}
  </PageContainer>
);
```

### Step 4: Make Table Responsive
```javascript
function MarketTable({ viewport }) {
  const columns = getVisibleColumns("markets", viewport);
  
  return (
    <table>
      <thead>
        <tr>
          {columns.map(col => <th key={col}>{formatCol(col)}</th>)}
        </tr>
      </thead>
      <tbody>
        {markets.map(market => (
          <tr key={market.id}>
            {columns.includes("pair") && <td>{market.pair}</td>}
            {columns.includes("price") && <td>${market.price}</td>}
            {/* etc... */}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
```

## Testing Your Responsive Page

Use Chrome DevTools to test at these key breakpoints:

1. **Mobile: 390×844** (iPhone 14)
   - Single column layout
   - Bottom navigation visible
   - Simplified tables
   
2. **Tablet: 768×1024** (iPad)
   - 2-column layouts
   - Wider forms
   - More table columns
   
3. **Desktop: 1366×768** (Laptop)
   - Multi-column layout
   - Sidebar navigation
   - Full table width
   
4. **Large: 1920×1080** (Desktop)
   - Centered content
   - All features visible
   - Maximum width constraints

## Common Mistakes to Avoid

❌ **DON'T:**
- Use fixed widths: `width: 600px`
- Hardcode column counts: `grid-template-columns: repeat(4, 1fr)`
- Forget about mobile: Start desktop-first
- Set vh only: Use `100dvh` for mobile
- Hide large content on mobile: Adapt, don't hide

✅ **DO:**
- Use max-width: `max-width: 1440px`
- Use auto-fit: `repeat(auto-fit, minmax(250px, 1fr))`
- Start mobile-first: Mobile CSS first, then media queries
- Use dvh: `min-height: 100dvh`
- Adapt content: Show fewer columns, not less content

## Getting Help

- **RESPONSIVE_ARCHITECTURE.md** - Full documentation
- **ResponsiveDashboardExample.jsx** - Complete working example
- **useResponsive.js** - All available utilities
- **layouts.css** - All available utilities and classes

## Next Steps

1. Pick a page in your area (Markets, Trade, Assets, etc.)
2. Follow this guide to make it responsive
3. Test on mobile, tablet, and desktop
4. Use the example component as reference
5. Ask questions if stuck!

You've got this! The responsive system makes it much easier than it used to be.
