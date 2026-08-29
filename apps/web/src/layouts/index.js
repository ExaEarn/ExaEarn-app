import React from "react";

/**
 * PageContainer
 * Provides standardized, responsive content container for pages.
 * Automatically handles max-width, margins, and padding.
 */
export function PageContainer({ children, compact = false, full = false, className = "" }) {
  const containerClass = `page-container ${
    full ? "page-container-full" : compact ? "page-container-compact" : ""
  } ${className}`;

  return React.createElement("div", { className: containerClass }, children);
}

/**
 * PageHeader
 * Standardized page heading section with title, subtitle, and actions.
 */
export function PageHeader({ title, subtitle, actions, className = "" }) {
  return React.createElement(
    "div",
    { className: `page-header ${className}` },
    React.createElement(
      "div",
      { className: "page-header-top" },
      React.createElement(
        "div",
        null,
        title ? React.createElement("h1", { className: "page-title" }, title) : null,
        subtitle ? React.createElement("p", { className: "page-subtitle" }, subtitle) : null,
      ),
      actions ? React.createElement("div", { className: "page-header-actions" }, actions) : null,
    ),
  );
}

/**
 * PageSection
 * Grouped content section with optional title and background.
 */
export function PageSection({ title, children, className = "", elevated = false }) {
  return React.createElement(
    "section",
    { className: `page-section ${elevated ? "page-section-elevated" : ""} ${className}` },
    title ? React.createElement("h2", { className: "page-section-title" }, title) : null,
    children,
  );
}

/**
 * ResponsiveGrid
 * Smart grid that adapts columns based on breakpoints.
 */
export function ResponsiveGrid({
  children,
  columns = "auto",
  gap = "lg",
  className = "",
  minItemWidth = "280px",
}) {
  const gridClass = `responsive-grid responsive-grid-${columns}`;
  const gapClass = `gap-${gap}`;

  return React.createElement(
    "div",
    {
      className: `${gridClass} ${gapClass} ${className}`,
      style: columns === "auto" ? { "--min-item-width": minItemWidth } : {},
    },
    children,
  );
}

/**
 * Card
 * Standardized card component with consistent styling and responsiveness.
 */
export function Card({ children, className = "", interactive = false, onClick, elevated = false }) {
  const cardClass = `card ${interactive ? "card-interactive" : ""} ${elevated ? "card-elevated" : ""} ${className}`;

  return React.createElement("div", { className: cardClass, onClick, role: onClick ? "button" : undefined }, children);
}

/**
 * SplitPane
 * Desktop: Two columns | Tablet: 2 rows | Mobile: Stacked
 */
export function SplitPane({ left, right, ratio = "50-50", className = "" }) {
  return React.createElement(
    "div",
    { className: `split-pane split-pane-${ratio} ${className}` },
    React.createElement("div", { className: "split-pane-left" }, left),
    React.createElement("div", { className: "split-pane-right" }, right),
  );
}

/**
 * ContentCard
 * Wrapper for card content with consistent padding and spacing.
 */
export function ContentCard({ children, header, footer, className = "" }) {
  return React.createElement(
    Card,
    { className },
    header ? React.createElement("div", { className: "card-header" }, header) : null,
    React.createElement("div", { className: "card-content" }, children),
    footer ? React.createElement("div", { className: "card-footer" }, footer) : null,
  );
}
