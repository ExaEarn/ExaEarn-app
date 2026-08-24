import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import {
  Activity,
  ArrowRight,
  BookOpen,
  CheckCircle2,
  Code2,
  DatabaseZap,
  FileJson,
  Gauge,
  KeyRound,
  LockKeyhole,
  Network,
  Radio,
  RefreshCcw,
  ShieldCheck,
  TerminalSquare,
  Webhook,
  type LucideIcon,
} from "lucide-react";
import type { ReactNode } from "react";
import "./styles.css";

const endpoints = [
  ["GET", "/api/developer/v1/exchange-info", "Public market metadata", "Available"],
  ["GET", "/api/developer/v1/tickers", "Authoritative and reference ticker contract", "Available"],
  ["GET", "/api/developer/v1/orderbook/{symbol}", "Sequenced order book snapshot", "Available"],
  ["GET", "/api/developer/v1/trades/{symbol}", "Recent public trade stream window", "Available"],
  ["GET", "/api/developer/v1/klines/{symbol}", "Candles built from market data service", "Available"],
  ["GET", "/api/developer/v1/wallet/balances", "Signed wallet or sandbox balance read", "Available"],
  ["POST", "/api/developer/v1/spot/orders", "Signed Spot order submission through ExaEarn OMS", "Available"],
  ["GET", "/api/developer/v1/spot/orders/{orderId}", "Signed Spot order lookup", "Available"],
  ["POST", "/api/developer/v1/futures/orders", "Futures order routed through ExaEarn Futures OMS and risk", "Private beta"],
  ["GET", "/api/developer/v1/futures/positions", "Signed Futures position view", "Private beta"],
  ["POST", "/api/developer/v1/margin/borrow", "Margin borrow through lending-pool controls", "Private beta"],
  ["POST", "/api/developer/v1/staking/positions", "Native staking position creation", "Available"],
  ["POST", "/api/developer/v1/copy/follow", "Copy Trading relationship creation with eligibility checks", "Private beta"],
  ["POST", "/api/developer/v1/exaai/sessions", "ExaAI session lifecycle through governance controls", "Private beta"],
  ["POST", "/api/developer/projects/{id}/sandbox/faucet", "Authenticated developer sandbox faucet", "Available"],
  ["POST", "/api/developer/webhooks", "Webhook endpoint registration and signed delivery", "Available"],
];

const permissions = [
  ["market.read", "Read market symbols, tickers, books, trades, and candles."],
  ["account.read", "Read balances for the key environment."],
  ["spot.read", "Read Spot orders created by the authenticated account."],
  ["spot.trade", "Submit Spot orders through the production OMS controls."],
  ["futures.read / futures.trade", "Read and submit Futures orders without bypassing Futures risk."],
  ["margin.read / margin.manage", "Read margin state and manage borrow, repay, transfer, and margin orders."],
  ["staking.read / staking.manage", "Read staking products and manage user staking positions."],
  ["copy.read / copy.manage", "Read leaders and manage copy relationships under public-mode controls."],
  ["exaai.read / exaai.manage", "Read and manage ExaAI sessions without bypassing strategy governance."],
  ["wallet.withdraw", "High-risk permission. Requires IP whitelist and additional operational approval."],
];

const readinessCards: Array<[string, string, LucideIcon]> = [
  ["Public market data", "Available", Radio],
  ["Signed private REST", "Available", LockKeyhole],
  ["Sandbox faucet", "Isolated", DatabaseZap],
  ["SDK", "Available", Code2],
];

const sdkExample = `import { createExaEarnClient } from "@exaearn/sdk";

const exaearn = createExaEarnClient({
  baseUrl: "https://api.exaearn.com",
  apiKey: process.env.EXAEARN_API_KEY,
  apiSecret: process.env.EXAEARN_API_SECRET,
});

const balances = await exaearn.balances();
const order = await exaearn.createSpotOrder({
  symbol: "BTC-USDT",
  side: "buy",
  type: "limit",
  quantity: "0.001",
  price: "65000",
});`;

const canonicalExample = `METHOD
/api/developer/v1/wallet/balances
query=string
EXA-API-TIMESTAMP
sha256(request_body)`;

const apiBaseUrl = (import.meta.env.VITE_API_URL || "https://api.exaearn.com").replace(/\/$/, "");

function App() {
  return (
    <main className="developer-shell">
      <nav className="topbar" aria-label="Developer portal navigation">
        <a className="brand" href="#top" aria-label="ExaEarn Developers">
          <span className="brand-mark">EX</span>
          <span>
            <strong>ExaEarn</strong>
            <small>Developers</small>
          </span>
        </a>
        <div className="topbar-links">
          <a href="#apis">APIs</a>
          <a href="#auth">Authentication</a>
          <a href="#sandbox">Sandbox</a>
          <a href="#sdk">SDK</a>
        </div>
        <a className="launch-link" href={`${apiBaseUrl}/api/developer/v1/exchange-info`}>
          Test API <ArrowRight size={16} />
        </a>
      </nav>

      <section className="hero" id="top">
        <div className="hero-copy">
          <p className="eyebrow">Phase 14 Developer Platform</p>
          <h1>Build on ExaEarn without bypassing ExaEarn.</h1>
          <p>
            Secure REST APIs, signed private endpoints, isolated sandbox balances,
            request logging, and SDK infrastructure around the existing Spot,
            Futures, Margin, Staking, Copy Trading, ExaAI, wallet, ledger,
            market data, and realtime systems.
          </p>
          <div className="hero-actions">
            <a className="primary-action" href="#apis">Explore APIs <ArrowRight size={17} /></a>
            <a className="secondary-action" href="#auth">View signing rules</a>
          </div>
        </div>
        <div className="hero-panel" aria-label="Developer platform readiness">
          {readinessCards.map(([label, status, Icon]) => (
            <article key={label}>
              <Icon size={22} />
              <span>{label}</span>
              <strong>{status}</strong>
            </article>
          ))}
        </div>
      </section>

      <section className="section-grid">
        <FeatureCard icon={ShieldCheck} title="Canonical Infrastructure">
          Phase 14 submits signed Spot orders into the existing OMS and execution
          controls. It does not create a second exchange, wallet, or ledger path.
        </FeatureCard>
        <FeatureCard icon={Gauge} title="Rate Limited by Design">
          Developer request logs carry request IDs, latency, status, API key,
          project, environment, and error metadata for support and abuse review.
        </FeatureCard>
        <FeatureCard icon={RefreshCcw} title="Sandbox First">
          Sandbox faucet funds are stored separately from real wallet balances,
          so test clients cannot accidentally mix simulated and real funds.
        </FeatureCard>
      </section>

      <section className="api-section" id="apis">
        <div className="section-heading">
          <p className="eyebrow">REST API</p>
          <h2>Stable external contracts around internal ExaEarn systems.</h2>
        </div>
        <div className="endpoint-table" role="table" aria-label="Developer REST API endpoints">
          {endpoints.map(([method, path, description, status]) => (
            <div className="endpoint-row" role="row" key={`${method}-${path}`}>
              <span className={`method ${method.toLowerCase()}`}>{method}</span>
              <code>{path}</code>
              <span>{description}</span>
              <strong>{status}</strong>
            </div>
          ))}
        </div>
      </section>

      <section className="two-column" id="auth">
        <div>
          <p className="eyebrow">HMAC Authentication</p>
          <h2>Private APIs require signed requests.</h2>
          <p>
            Developers sign the exact method, path, query string, timestamp, and
            SHA-256 body hash. Server-side permissions decide what each API key
            can access.
          </p>
          <ul className="check-list">
            {[
              "API secrets are shown once at creation or rotation.",
              "Withdrawal-capable keys require IP whitelist controls.",
              "Expired timestamps, invalid signatures, inactive keys, and missing permissions fail closed.",
              "Every developer API response includes an ExaEarn request ID.",
            ].map((item) => <li key={item}><CheckCircle2 size={16} /> {item}</li>)}
          </ul>
        </div>
        <CodeBlock title="Canonical payload" code={canonicalExample} />
      </section>

      <section className="permissions-section">
        <div className="section-heading compact">
          <p className="eyebrow">Permissions</p>
          <h2>Granular scopes for safer automation.</h2>
        </div>
        <div className="permission-grid">
          {permissions.map(([scope, description]) => (
            <article key={scope}>
              <KeyRound size={19} />
              <code>{scope}</code>
              <p>{description}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="two-column" id="sandbox">
        <div>
          <p className="eyebrow">Sandbox</p>
          <h2>Test trading flows without touching production funds.</h2>
          <p>
            Sandbox projects use `exa_test_` keys and isolated sandbox balances.
            The faucet is rate limited per asset and project. Production keys use
            `exa_live_` and real ExaEarn wallet/OMS controls.
          </p>
        </div>
        <div className="status-stack">
          <StatusLine icon={DatabaseZap} label="Balance source" value="developer_sandbox_balances" />
          <StatusLine icon={Activity} label="Faucet limit" value="Configured server-side" />
          <StatusLine icon={Network} label="Isolation" value="No real wallet credit" />
        </div>
      </section>

      <section className="two-column" id="sdk">
        <div>
          <p className="eyebrow">SDK</p>
          <h2>Typed JavaScript/TypeScript client.</h2>
          <p>
            The workspace package `@exaearn/sdk` implements public REST helpers,
            private HMAC signing, Spot/Futures/Margin/Staking/Copy/ExaAI helpers,
            balance reads, and typed API errors.
          </p>
          <div className="doc-links">
            <a href="https://github.com/ExaEarn/ExaEarn-app/blob/main/openapi/exaearn-developer-v1.yaml"><FileJson size={16} /> OpenAPI</a>
            <a href="#apis"><BookOpen size={16} /> API catalog</a>
            <a href="#webhooks"><Webhook size={16} /> Webhooks</a>
          </div>
        </div>
        <CodeBlock title="SDK example" code={sdkExample} />
      </section>

      <section className="roadmap" id="webhooks">
        <div className="section-heading compact">
          <p className="eyebrow">Controlled rollout</p>
          <h2>Interfaces intentionally gated until operational readiness is complete.</h2>
        </div>
        <div className="roadmap-grid">
          {[
            ["Webhook delivery jobs", "Signed delivery, retry, dead letter, and replay are implemented for developer events."],
            ["Futures and margin APIs", "Signed API routes now reuse the existing product controllers and risk services."],
            ["Custody withdrawals", "Withdrawals require stronger operational controls before public developer access."],
            ["Public websocket gateway", "Session, sequencing, replay, and backpressure policy are exposed through the developer realtime contract."],
          ].map(([title, detail]) => (
            <article key={title}>
              <TerminalSquare size={20} />
              <h3>{title}</h3>
              <p>{detail}</p>
            </article>
          ))}
        </div>
      </section>
    </main>
  );
}

function FeatureCard({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children: ReactNode }) {
  return (
    <article className="feature-card">
      <Icon size={22} />
      <h2>{title}</h2>
      <p>{children}</p>
    </article>
  );
}

function CodeBlock({ title, code }: { title: string; code: string }) {
  return (
    <figure className="code-block">
      <figcaption>{title}</figcaption>
      <pre><code>{code}</code></pre>
    </figure>
  );
}

function StatusLine({ icon: Icon, label, value }: { icon: LucideIcon; label: string; value: string }) {
  return (
    <div className="status-line">
      <Icon size={18} />
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
