/**
 * Responsive Design System Utilities
 * These utilities help pages work with the responsive layout system.
 */

import { useEffect, useState } from "react";

/**
 * useResponsive Hook
 * Detects current breakpoint and provides responsive helpers
 */
export function useResponsive() {
  const [viewport, setViewport] = useState({
    isMobile: typeof window !== "undefined" && window.innerWidth < 768,
    isTablet: typeof window !== "undefined" && window.innerWidth >= 768 && window.innerWidth < 1024,
    isDesktop: typeof window !== "undefined" && window.innerWidth >= 1024,
    isLargeDesktop: typeof window !== "undefined" && window.innerWidth >= 1280,
    width: typeof window !== "undefined" ? window.innerWidth : 0,
  });

  useEffect(() => {
    const handleResize = () => {
      const width = window.innerWidth;
      setViewport({
        isMobile: width < 768,
        isTablet: width >= 768 && width < 1024,
        isDesktop: width >= 1024,
        isLargeDesktop: width >= 1280,
        width,
      });
    };

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  return viewport;
}

/**
 * useMediaQuery Hook
 * Generic media query hook for custom breakpoints
 */
export function useMediaQuery(query) {
  const [matches, setMatches] = useState(
    typeof window !== "undefined" ? window.matchMedia(query).matches : false
  );

  useEffect(() => {
    const mediaQuery = window.matchMedia(query);
    const handler = (e) => setMatches(e.matches);

    mediaQuery.addEventListener("change", handler);
    return () => mediaQuery.removeEventListener("change", handler);
  }, [query]);

  return matches;
}

/**
 * ResponsiveGrid Presets
 * Common grid configurations for different use cases
 */
export const GridPresets = {
  // Product cards, asset rows, feature cards
  cards: {
    desktop: "grid-template-columns: repeat(3, minmax(0, 1fr))",
    tablet: "grid-template-columns: repeat(2, minmax(0, 1fr))",
    mobile: "grid-template-columns: 1fr",
  },
  
  // Two-column layouts
  twoColumn: {
    desktop: "grid-template-columns: 1fr 1fr",
    tablet: "grid-template-columns: 1fr 1fr",
    mobile: "grid-template-columns: 1fr",
  },
  
  // Split information (60/40)
  splitInfo: {
    desktop: "grid-template-columns: 1.5fr 1fr",
    tablet: "grid-template-columns: 1fr 1fr",
    mobile: "grid-template-columns: 1fr",
  },
  
  // Auto-fit responsive grid
  autoFit: {
    desktop: "grid-template-columns: repeat(auto-fit, minmax(280px, 1fr))",
    tablet: "grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))",
    mobile: "grid-template-columns: repeat(auto-fit, minmax(100%, 1fr))",
  },
};

/**
 * ContentConstraint Helper
 * Returns appropriate max-width class for different page types
 */
export function getContentConstraint(pageType = "standard") {
  const constraints = {
    standard: "max-w-content",
    compact: "max-w-content-compact",
    trading: "max-w-trading",
    form: "max-w-form",
    full: "w-full",
  };

  return constraints[pageType] || constraints.standard;
}

/**
 * ResponsiveColumnConfig
 * Define columns based on breakpoint
 */
export const ResponsiveColumnConfig = {
  markets: {
    desktop: ["pair", "price", "24h", "high", "low", "volume", "action"],
    tablet: ["pair", "price", "24h", "action"],
    mobile: ["pair", "price", "24h"],
  },

  assets: {
    desktop: ["asset", "total", "available", "orders", "earn", "price", "24h", "action"],
    tablet: ["asset", "total", "available", "action"],
    mobile: ["asset", "total", "action"],
  },

  trades: {
    desktop: ["pair", "side", "price", "amount", "total", "time", "status"],
    tablet: ["pair", "side", "price", "total", "status"],
    mobile: ["pair", "price", "total"],
  },
};

/**
 * getVisibleColumns
 * Returns which columns should be visible based on viewport
 */
export function getVisibleColumns(columnType, viewport) {
  const config = ResponsiveColumnConfig[columnType];
  if (!config) return [];

  if (viewport.isDesktop) return config.desktop;
  if (viewport.isTablet) return config.tablet;
  return config.mobile;
}

/**
 * clampFont
 * Creates responsive font-size using clamp()
 * Useful for headings and important text
 */
export function clampFont(minRem, preferredVw, maxRem) {
  return `clamp(${minRem}rem, ${preferredVw}vw, ${maxRem}rem)`;
}

/**
 * Safe Area Padding
 * Wraps padding to include safe area insets
 */
export function withSafeArea(top = true, right = true, bottom = true, left = true) {
  const paddings = [];
  if (top) paddings.push("padding-top: env(safe-area-inset-top)");
  if (right) paddings.push("padding-right: env(safe-area-inset-right)");
  if (bottom) paddings.push("padding-bottom: env(safe-area-inset-bottom)");
  if (left) paddings.push("padding-left: env(safe-area-inset-left)");
  return paddings.join("; ");
}

/**
 * getResponsiveValue
 * Get a value based on current viewport
 */
export function getResponsiveValue(mobile, tablet, desktop) {
  if (typeof window === "undefined") return mobile;
  
  const width = window.innerWidth;
  if (width < 768) return mobile;
  if (width < 1024) return tablet;
  return desktop;
}

/**
 * Responsive Spacing Constants
 */
export const ResponsiveSpacing = {
  mobile: {
    gutter: "12px",
    padding: "16px",
    gap: "12px",
  },
  tablet: {
    gutter: "20px",
    padding: "20px",
    gap: "16px",
  },
  desktop: {
    gutter: "24px",
    padding: "24px",
    gap: "20px",
  },
  largeDesktop: {
    gutter: "32px",
    padding: "32px",
    gap: "24px",
  },
};

/**
 * Responsive Typography Scale
 */
export const ResponsiveTypography = {
  h1: {
    mobile: "clamp(1.35rem, 2vw, 1.75rem)",
    desktop: "2rem",
  },
  h2: {
    mobile: "clamp(1.1rem, 1.8vw, 1.5rem)",
    desktop: "1.5rem",
  },
  h3: {
    mobile: "clamp(0.95rem, 1.5vw, 1.25rem)",
    desktop: "1.25rem",
  },
  body: {
    mobile: "clamp(0.9rem, 1vw, 1rem)",
    desktop: "1rem",
  },
  caption: {
    mobile: "clamp(0.75rem, 0.9vw, 0.875rem)",
    desktop: "0.875rem",
  },
};
