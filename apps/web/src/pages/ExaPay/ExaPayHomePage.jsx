import { useState } from "react";
import {
  ArrowDownToLine,
  ArrowLeftRight,
  ArrowUpRight,
  Bell,
  Bluetooth,
  Check,
  ChevronRight,
  Cloud,
  Home,
  MoreHorizontal,
  QrCode,
  ReceiptText,
  RefreshCw,
  ShieldCheck,
  Smartphone,
  Store,
  TrendingUp,
  X,
} from "lucide-react";
import exaearnLogo from "../../assets/images/exaearn-logo.png";
import "./ExaPayHomePage.css";

const balances = [
  {
    label: "Online Balance",
    amount: "60",
    status: "Connected",
    icon: Cloud,
    tone: "online",
  },
  {
    label: "Offline Wallet",
    amount: "40",
    status: "Stored on device",
    icon: Smartphone,
    tone: "offline",
  },
];

const actions = [
  { label: "Online Transfer", icon: ArrowUpRight, tone: "blue" },
  { label: "Offline Pay", icon: Bluetooth, tone: "violet" },
  { label: "Load Offline Wallet", icon: ArrowDownToLine, tone: "violet" },
  { label: "Sync & Redeem", icon: RefreshCw, tone: "green" },
];

const activity = [
  {
    title: "To: alice@exaearn",
    status: "Pending Sync",
    amount: "-10.00",
    tone: "pending",
  },
  {
    title: "Offline Sent",
    status: "Offline Committed",
    amount: "-20.00",
    tone: "offline",
  },
  {
    title: "Redeemed to Online",
    status: "Redeemed",
    amount: "+30.00",
    tone: "success",
  },
];

const navigation = [
  { label: "Home", icon: Home, route: "home" },
  { label: "Markets", icon: TrendingUp, route: "market" },
  { label: "ExaPay", icon: ArrowLeftRight, route: "exapay", active: true },
  { label: "Transactions", icon: ReceiptText, route: "transactions" },
  { label: "More", icon: MoreHorizontal, route: "more" },
];

function StatusPill({ children, tone }) {
  return (
    <span className={`exapay-home__status-pill exapay-home__status-pill--${tone}`}>
      <span className="exapay-home__status-dot" aria-hidden="true" />
      {children}
    </span>
  );
}

function SectionHeading({ title, id, action, onAction }) {
  return (
    <div className="exapay-home__section-heading">
      <h2 id={id}>{title}</h2>
      {action ? (
        <button type="button" className="exapay-home__text-action" onClick={onAction}>
          {action}
          <ChevronRight size={15} aria-hidden="true" />
        </button>
      ) : null}
    </div>
  );
}

function BalanceCard({ balance }) {
  const Icon = balance.icon;
  return (
    <article className={`exapay-home__balance-card exapay-home__balance-card--${balance.tone}`}>
      <div className="exapay-home__balance-top">
        <span className="exapay-home__icon-box" aria-hidden="true">
          <Icon size={18} />
        </span>
        <StatusPill tone={balance.tone === "online" ? "connected" : "device"}>
          {balance.status}
        </StatusPill>
      </div>
      <p className="exapay-home__balance-label">{balance.label}</p>
      <p className="exapay-home__balance-amount">
        {balance.amount}
        <span>simUSDT</span>
      </p>
    </article>
  );
}

function ActivityRow({ item, last }) {
  return (
    <li className={`exapay-home__activity-row${last ? " is-last" : ""}`}>
      <div className="exapay-home__activity-copy">
        <strong>{item.title}</strong>
        <StatusPill tone={item.tone}>{item.status}</StatusPill>
      </div>
      <strong className={`exapay-home__activity-amount${item.amount.startsWith("+") ? " is-credit" : ""}`}>
        {item.amount}
        <span>simUSDT</span>
      </strong>
    </li>
  );
}

export default function ExaPayHomePage({
  onNavigate,
  onNotificationOpen,
  onOpenMerchant,
  onOpenTransfer,
  onOpenOfflinePay,
  onOpenSyncRedeem,
}) {
  const [notice, setNotice] = useState("");

  const simulateAction = (action) => {
    setNotice(`${action} is in Simulation Mode. No funds were moved.`);
  };

  return (
    <main className="exapay-home">
      <div className="exapay-home__page">
        <header className="exapay-home__header">
          <div className="exapay-home__brand">
            <img src={exaearnLogo} alt="ExaEarn" />
            <span>
              <strong>ExaEarn</strong>
              <small>PAYMENTS</small>
            </span>
          </div>
          <button
            type="button"
            className="exapay-home__notification"
            onClick={onNotificationOpen}
            aria-label="Open notifications"
          >
            <Bell size={19} aria-hidden="true" />
          </button>
        </header>

        <section className="exapay-home__intro" aria-labelledby="exapay-title">
          <div className="exapay-home__intro-copy">
            <div className="exapay-home__title-line">
              <h1 id="exapay-title">ExaPay</h1>
              <span className="exapay-home__simulation">
                <span aria-hidden="true" />
                Simulation Mode
              </span>
            </div>
            <p>Move value online or offline with confidence.</p>
          </div>
          <div className="exapay-home__intro-mark" aria-hidden="true">
            <ArrowLeftRight size={28} />
          </div>
        </section>

        {notice ? (
          <div className="exapay-home__notice" role="status">
            <span>{notice}</span>
            <button type="button" onClick={() => setNotice("")} aria-label="Dismiss message">
              <X size={16} />
            </button>
          </div>
        ) : null}

        <div className="exapay-home__content-grid">
          <div className="exapay-home__main-column">
            <section aria-labelledby="exapay-balances-heading">
              <SectionHeading title="Your balances" id="exapay-balances-heading" />
              <div className="exapay-home__balance-grid">
                {balances.map((balance) => <BalanceCard key={balance.label} balance={balance} />)}
              </div>
            </section>

            <section aria-labelledby="exapay-actions-heading">
              <SectionHeading title="Pay and move" id="exapay-actions-heading" />
              <div className="exapay-home__action-grid">
                {actions.map((action) => {
                  const Icon = action.icon;
                  return (
                    <button
                      type="button"
                      key={action.label}
                      className="exapay-home__action"
                      onClick={() => action.label === "Offline Pay"
                        ? onOpenOfflinePay("bluetooth")
                        : action.label === "Online Transfer"
                          ? onOpenTransfer("online")
                        : action.label === "Sync & Redeem"
                          ? onOpenSyncRedeem()
                        : simulateAction(action.label)}
                    >
                      <span className={`exapay-home__action-icon exapay-home__action-icon--${action.tone}`}>
                        <Icon size={20} aria-hidden="true" />
                      </span>
                      <strong>{action.label}</strong>
                      <ChevronRight size={16} className="exapay-home__action-chevron" aria-hidden="true" />
                    </button>
                  );
                })}
              </div>
            </section>

            <div className="exapay-home__tools" aria-label="Payment tools">
              <button type="button" onClick={() => onOpenTransfer("qr")}>
                <QrCode size={18} aria-hidden="true" />
                Scan QR
              </button>
              <span aria-hidden="true" />
              <button type="button" onClick={() => onOpenOfflinePay("nearby")}>
                <Bluetooth size={18} aria-hidden="true" />
                Nearby Devices
              </button>
            </div>

            <button
              type="button"
              className="exapay-home__offline-banner"
              onClick={() => onOpenOfflinePay("bluetooth")}
            >
              <span className="exapay-home__offline-icon">
                <Bluetooth size={19} aria-hidden="true" />
              </span>
              <span className="exapay-home__offline-copy">
                <strong>Offline Pay</strong>
                <small>Make payments nearby via Bluetooth or QR, without internet.</small>
              </span>
              <ChevronRight size={18} aria-hidden="true" />
            </button>
          </div>

          <aside className="exapay-home__side-column">
            <section className="exapay-home__activity-section" aria-labelledby="exapay-activity-heading">
              <SectionHeading
                title="Recent Activity"
                id="exapay-activity-heading"
                action="See All"
                onAction={() => onNavigate("transactions")}
              />
              <ul className="exapay-home__activity-list">
                {activity.map((item, index) => (
                  <ActivityRow key={item.title} item={item} last={index === activity.length - 1} />
                ))}
              </ul>
            </section>

            <section className="exapay-home__trusted">
              <span className="exapay-home__trusted-icon">
                <ShieldCheck size={20} aria-hidden="true" />
              </span>
              <span className="exapay-home__trusted-copy">
                <strong>Trusted Device</strong>
                <small>This device is enrolled and protected.</small>
              </span>
              <StatusPill tone="success">
                <Check size={12} aria-hidden="true" />
                Enrolled
              </StatusPill>
            </section>

            <button type="button" className="exapay-home__merchant" onClick={onOpenMerchant}>
              <span className="exapay-home__merchant-icon"><Store size={18} aria-hidden="true" /></span>
              <span>
                <strong>Merchant tools</strong>
                <small>Manage your ExaPay business</small>
              </span>
              <ChevronRight size={17} aria-hidden="true" />
            </button>
          </aside>
        </div>
      </div>

      <nav className="exapay-home__bottom-nav" aria-label="Main navigation">
        <div className="exapay-home__bottom-nav-inner">
          {navigation.map((item) => {
            const Icon = item.icon;
            return (
              <button
                type="button"
                key={item.route}
                className={`exapay-home__nav-item${item.active ? " is-active" : ""}`}
                onClick={() => item.active ? undefined : onNavigate(item.route)}
                aria-current={item.active ? "page" : undefined}
                aria-label={item.label}
              >
                <Icon size={19} aria-hidden="true" />
                <span>{item.label}</span>
              </button>
            );
          })}
        </div>
      </nav>
    </main>
  );
}
