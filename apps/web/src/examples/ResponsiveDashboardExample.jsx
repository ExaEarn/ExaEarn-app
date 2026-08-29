/**
 * Example Responsive Dashboard Container
 * Demonstrates best practices for using the new responsive architecture.
 * 
 * This example shows:
 * - Using AppShell wrapper
 * - Responsive grid layouts
 * - Mobile-first design approach
 * - Proper content constraints
 * - Responsive navigation awareness
 */

import React from "react";
import { ResponsiveGrid, PageContainer, PageHeader, SplitPane } from "@/layouts";
import { useResponsive, getVisibleColumns, ResponsiveColumnConfig } from "@/hooks/useResponsive";
import "../styles/responsive-dashboard.css";

/**
 * ResponsiveDashboardExample
 * Shows how to structure a responsive page
 * 
 * Desktop (1024px+):
 * - Sidebar navigation (handled by AppShell)
 * - Top header
 * - Multi-column dashboard
 * - Split pane for complex layouts
 * 
 * Tablet (768-1023px):
 * - Simplified navigation
 * - 2-column layouts
 * - Collapsible sections
 * 
 * Mobile (<768px):
 * - Bottom navigation (handled by AppShell)
 * - Single column layout
 * - Stacked cards
 * - Simplified tables
 */
export default function ResponsiveDashboardExample({
  portfolio,
  markets,
  recentTransactions,
  onAction,
}) {
  const viewport = useResponsive();

  // Get which table columns to show based on viewport
  const marketColumns = getVisibleColumns("markets", viewport);

  return (
    <PageContainer compact={!viewport.isDesktop}>
      {/* Page Header */}
      <PageHeader
        title="Dashboard"
        subtitle="Your portfolio and market overview"
        actions={
          viewport.isDesktop && (
            <div className="page-header-actions">
              <button className="btn-primary">Customize Dashboard</button>
            </div>
          )
        }
      />

      {/* Portfolio Section - Always full width */}
      <section className="dashboard-portfolio">
        <div className="portfolio-card">
          <span className="portfolio-label">Total Balance</span>
          <h2 className="portfolio-value">${portfolio.totalValue.toLocaleString()}</h2>
          <p className="portfolio-change">
            {portfolio.dailyChange > 0 ? "+" : ""}
            {portfolio.dailyChange}% today
          </p>
        </div>

        {/* Quick Actions - Responsive Grid */}
        <ResponsiveGrid columns="auto" gap="lg">
          {portfolio.quickActions.map((action) => (
            <div key={action.id} className="quick-action-card">
              <div className="action-icon">{action.icon}</div>
              <h4>{action.label}</h4>
              <button className="btn-secondary" onClick={() => onAction(action.id)}>
                {viewport.isMobile ? "↓" : action.label}
              </button>
            </div>
          ))}
        </ResponsiveGrid>
      </section>

      {/* Main Content - Responsive Layout */}
      {viewport.isDesktop ? (
        // Desktop: Three column grid
        <div className="dashboard-grid dashboard-grid-3col">
          <div className="grid-section">
            <h3 className="section-title">Markets</h3>
            <MarketsTable columns={marketColumns} markets={markets} />
          </div>

          <div className="grid-section">
            <h3 className="section-title">Trending</h3>
            <TrendingList markets={markets.slice(0, 5)} />
          </div>

          <div className="grid-section">
            <h3 className="section-title">News</h3>
            <NewsFeed articles={portfolio.news} />
          </div>
        </div>
      ) : viewport.isTablet ? (
        // Tablet: Two column grid
        <div className="dashboard-grid dashboard-grid-2col">
          <div className="grid-section">
            <h3 className="section-title">Markets</h3>
            <MarketsTable columns={marketColumns} markets={markets} />
          </div>

          <div className="grid-section">
            <h3 className="section-title">Recent Activity</h3>
            <ActivityList transactions={recentTransactions} compact />
          </div>
        </div>
      ) : (
        // Mobile: Single column, stacked
        <>
          <section className="dashboard-section">
            <h3 className="section-title">Markets</h3>
            <MarketsTable columns={marketColumns} markets={markets} />
          </section>

          <section className="dashboard-section">
            <h3 className="section-title">Recent Activity</h3>
            <ActivityList transactions={recentTransactions} compact />
          </section>

          <section className="dashboard-section">
            <h3 className="section-title">News & Updates</h3>
            <NewsFeed articles={portfolio.news} />
          </section>
        </>
      )}

      {/* Split Pane Example - Desktop Only */}
      {viewport.isDesktop && (
        <section className="dashboard-section">
          <h3 className="section-title">Analysis</h3>
          <SplitPane
            ratio="60-40"
            left={<ChartPanel data={portfolio.chartData} />}
            right={<StatsPanel stats={portfolio.statistics} />}
          />
        </section>
      )}

      {/* Footer - Adapts based on space */}
      <footer className="dashboard-footer">
        {viewport.isDesktop ? (
          <div className="footer-grid footer-grid-4col">
            <FooterSection title="Support" links={["Help Center", "Contact Us"]} />
            <FooterSection title="Resources" links={["API", "Docs", "Blog"]} />
            <FooterSection title="Legal" links={["Privacy", "Terms", "Security"]} />
            <FooterSection title="Social" links={["Twitter", "Discord", "Telegram"]} />
          </div>
        ) : (
          <div className="footer-links">
            <a href="#help">Help</a>
            <a href="#docs">Docs</a>
            <a href="#contact">Contact</a>
          </div>
        )}
      </footer>
    </PageContainer>
  );
}

// Component Examples

function MarketsTable({ columns, markets }) {
  return (
    <div className="table-container">
      <table className="markets-table">
        <thead>
          <tr>
            {columns.map((col) => (
              <th key={col}>{formatColumnHeader(col)}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {markets.map((market) => (
            <tr key={market.pair}>
              {columns.includes("pair") && <td>{market.pair}</td>}
              {columns.includes("price") && <td>${market.price}</td>}
              {columns.includes("24h") && (
                <td className={market.change24h > 0 ? "positive" : "negative"}>
                  {market.change24h > 0 ? "+" : ""}{market.change24h}%
                </td>
              )}
              {columns.includes("high") && <td>${market.high24h}</td>}
              {columns.includes("low") && <td>${market.low24h}</td>}
              {columns.includes("volume") && <td>${market.volume}</td>}
              {columns.includes("action") && (
                <td>
                  <button className="btn-sm">Trade</button>
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function TrendingList({ markets }) {
  return (
    <div className="trending-list">
      {markets.map((market) => (
        <div key={market.pair} className="trending-item">
          <span className="pair">{market.pair}</span>
          <span className={`change ${market.change24h > 0 ? "positive" : "negative"}`}>
            {market.change24h > 0 ? "▲" : "▼"} {Math.abs(market.change24h)}%
          </span>
        </div>
      ))}
    </div>
  );
}

function ActivityList({ transactions, compact }) {
  return (
    <div className="activity-list">
      {transactions.map((tx) => (
        <div key={tx.id} className={`activity-item ${compact ? "compact" : ""}`}>
          <div className="activity-icon">{tx.icon}</div>
          <div className="activity-details">
            <p className="activity-type">{tx.type}</p>
            {!compact && <p className="activity-time">{tx.time}</p>}
          </div>
          <div className="activity-amount">
            {tx.amount > 0 ? "+" : ""}{tx.amount}
          </div>
        </div>
      ))}
    </div>
  );
}

function NewsFeed({ articles }) {
  return (
    <div className="news-feed">
      {articles.map((article) => (
        <div key={article.id} className="news-item">
          <h4>{article.title}</h4>
          <p>{article.summary}</p>
          <small>{article.source} • {article.time}</small>
        </div>
      ))}
    </div>
  );
}

function ChartPanel({ data }) {
  return <div className="chart-panel">{/* Chart implementation */}</div>;
}

function StatsPanel({ stats }) {
  return (
    <div className="stats-panel">
      {Object.entries(stats).map(([key, value]) => (
        <div key={key} className="stat">
          <span className="stat-label">{formatLabel(key)}</span>
          <span className="stat-value">{value}</span>
        </div>
      ))}
    </div>
  );
}

function FooterSection({ title, links }) {
  return (
    <div className="footer-section">
      <h4>{title}</h4>
      <ul>
        {links.map((link) => (
          <li key={link}>
            <a href="#">{link}</a>
          </li>
        ))}
      </ul>
    </div>
  );
}

// Utility Functions
function formatColumnHeader(column) {
  const headers = {
    pair: "Pair",
    price: "Price",
    "24h": "24h Change",
    high: "High",
    low: "Low",
    volume: "Volume",
    action: "Action",
  };
  return headers[column] || column;
}

function formatLabel(key) {
  return key
    .replace(/([A-Z])/g, " $1")
    .replace(/^./, (char) => char.toUpperCase())
    .trim();
}
