import React, { useCallback, useEffect, useMemo, useState } from "react";
import { AlertTriangle, ArrowLeft, BadgeDollarSign, Landmark, Loader2, RefreshCcw, ShieldCheck, Wallet } from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { useWebSocketEvent } from "../../services/webSocketService";

const formatMoney = (value, asset = "USDT") => {
  const number = Number(value);
  if (!Number.isFinite(number)) return "--";
  return `${number.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${asset}`;
};

const formatRatio = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return "--";
  if (number > 1000) return "No debt";
  return `${number.toFixed(2)}x`;
};

const riskClass = (status) => {
  if (status === "HEALTHY") return "border-emerald-400/30 bg-emerald-500/10 text-emerald-200";
  if (status === "WARNING") return "border-amber-400/30 bg-amber-500/10 text-amber-200";
  return "border-red-400/30 bg-red-500/10 text-red-200";
};

const inputClass = "w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-[#d6b45f]/70 focus:ring-2 focus:ring-[#d6b45f]/15";
const primaryClass = "inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-[#d6b45f] px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-[#e5c66e] disabled:cursor-not-allowed disabled:opacity-50";

export default function Margin({ onBack, onOpenSpot, onOpenFutures }) {
  const { request, user } = useAuth();
  const [overview, setOverview] = useState(null);
  const [selectedAccount, setSelectedAccount] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [accountMode, setAccountMode] = useState("CROSS");
  const [isolatedPair, setIsolatedPair] = useState("BTC/USDT");
  const [transfer, setTransfer] = useState({ direction: "IN", asset: "USDT", amount: "", source_account: "funding", destination_account: "funding" });
  const [borrow, setBorrow] = useState({ asset: "USDT", amount: "" });
  const [repay, setRepay] = useState({ loan_uuid: "", amount: "" });
  const [order, setOrder] = useState({ pair: "BTC/USDT", side: "buy", type: "limit", amount: "", price: "", borrow_mode: "NORMAL" });
  const [streamStatus, setStreamStatus] = useState({ label: "Realtime ready", event: "" });

  const load = useCallback(async ({ silent = false } = {}) => {
    if (!silent) setLoading(true);
    setError("");
    try {
      const payload = await request("/api/margin/overview", { method: "GET", timeoutMs: 20000 });
      setOverview(payload);
      const first = payload?.accounts?.[0]?.account_uuid || "";
      setSelectedAccount((current) => current || first);
    } catch (err) {
      setError(err?.message || "Unable to load Margin account.");
    } finally {
      if (!silent) setLoading(false);
    }
  }, [request]);

  useEffect(() => {
    load();
  }, [load]);

  useWebSocketEvent("margin:update", useCallback((payload) => {
    const eventUserId = payload?.user_id;
    const currentUserId = user?.id || user?.user?.id;
    if (eventUserId && currentUserId && String(eventUserId) !== String(currentUserId)) {
      return;
    }

    setStreamStatus({
      label: "Live update received",
      event: payload?.event || "margin:update",
    });
    void load({ silent: true });
  }, [load, user?.id, user?.user?.id]));

  const accounts = overview?.accounts || [];
  const selected = useMemo(() => accounts.find((account) => account.account_uuid === selectedAccount) || accounts[0], [accounts, selectedAccount]);
  const health = selected?.health;
  const loans = overview?.loans || [];
  const orders = overview?.orders || [];
  const pools = overview?.pools || [];
  const activeLoan = loans.find((loan) => loan.loan_uuid === repay.loan_uuid) || loans[0];

  useEffect(() => {
    if (!repay.loan_uuid && loans[0]?.loan_uuid) {
      setRepay((current) => ({ ...current, loan_uuid: loans[0].loan_uuid }));
    }
  }, [loans, repay.loan_uuid]);

  const submit = async (label, action) => {
    setBusy(label);
    setError("");
    setMessage("");
    try {
      await action();
      setMessage(`${label} completed.`);
      await load();
    } catch (err) {
      setError(err?.message || `${label} failed.`);
    } finally {
      setBusy("");
    }
  };

  const createAccount = () => submit("Account setup", async () => {
    await request("/api/margin/accounts", {
      method: "POST",
      body: JSON.stringify({ mode: accountMode, market_symbol: isolatedPair }),
    });
  });

  const transferFunds = () => submit("Transfer", async () => {
    await request("/api/margin/transfer", {
      method: "POST",
      body: JSON.stringify({
        account_uuid: selected?.account_uuid,
        ...transfer,
        idempotency_key: `margin-transfer-${Date.now()}`,
      }),
    });
  });

  const borrowAsset = () => submit("Borrow", async () => {
    await request("/api/margin/borrow", {
      method: "POST",
      body: JSON.stringify({
        account_uuid: selected?.account_uuid,
        ...borrow,
        idempotency_key: `margin-borrow-${Date.now()}`,
      }),
    });
  });

  const repayLoan = () => submit("Repay", async () => {
    await request(`/api/margin/loans/${repay.loan_uuid}/repay`, {
      method: "POST",
      body: JSON.stringify({ amount: repay.amount, idempotency_key: `margin-repay-${Date.now()}` }),
    });
  });

  const placeMarginOrder = () => submit("Margin order", async () => {
    await request("/api/margin/orders", {
      method: "POST",
      body: JSON.stringify({
        account_uuid: selected?.account_uuid,
        ...order,
        client_order_id: `web-margin-${Date.now()}`,
      }),
    });
  });

  const cancelMarginOrder = (marginOrderUuid) => submit("Cancel order", async () => {
    await request(`/api/margin/orders/${marginOrderUuid}/cancel`, { method: "POST" });
  });

  return (
    <main className="min-h-screen bg-[#07090d] px-4 py-5 text-white sm:px-6 lg:px-8">
      <header className="mx-auto flex max-w-7xl items-center justify-between gap-3">
        <button type="button" onClick={onBack} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200" aria-label="Back">
          <ArrowLeft size={18} />
        </button>
        <div className="min-w-0 flex-1">
          <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[#d6b45f]">ExaEarn Margin</p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Margin Trading</h1>
          <p className="mt-1 max-w-2xl text-sm text-slate-400">Borrow real assets from funded ExaEarn lending pools, trade through Spot, and manage collateral with server-side risk controls.</p>
          <p className="mt-2 inline-flex items-center rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">
            {streamStatus.label}{streamStatus.event ? `: ${streamStatus.event}` : ""}
          </p>
        </div>
        <button type="button" onClick={load} className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200">
          <RefreshCcw size={16} /> Refresh
        </button>
      </header>

      <section className="mx-auto mt-5 grid max-w-7xl gap-4 lg:grid-cols-[1.45fr_0.9fr]">
        <div className="rounded-2xl border border-white/10 bg-[#0d1118] p-4 shadow-2xl shadow-black/20">
          {loading ? (
            <div className="flex min-h-48 items-center justify-center text-slate-400"><Loader2 className="mr-2 animate-spin" size={18} /> Loading Margin account...</div>
          ) : (
            <>
              <div className="flex flex-wrap items-center gap-2">
                {accounts.map((account) => (
                  <button key={account.account_uuid} type="button" onClick={() => setSelectedAccount(account.account_uuid)} className={`rounded-xl px-3 py-2 text-sm font-semibold ${selected?.account_uuid === account.account_uuid ? "bg-[#d6b45f] text-[#090b0f]" : "border border-white/10 bg-white/5 text-slate-300"}`}>
                    {account.mode === "CROSS" ? "Cross" : account.market_symbol}
                  </button>
                ))}
              </div>

              <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Metric icon={<Wallet size={17} />} label="Equity" value={formatMoney(health?.equity)} />
                <Metric icon={<ShieldCheck size={17} />} label="Health" value={formatRatio(health?.health_factor)} />
                <Metric icon={<BadgeDollarSign size={17} />} label="Assets" value={formatMoney(health?.gross_asset_value)} />
                <Metric icon={<Landmark size={17} />} label="Debt" value={formatMoney(health?.gross_liability_value)} />
              </div>

              <div className={`mt-4 rounded-2xl border px-4 py-3 ${riskClass(health?.status)}`}>
                <div className="flex items-start gap-3">
                  <AlertTriangle className="mt-0.5 shrink-0" size={18} />
                  <div>
                    <strong className="block text-sm">{health?.status || "UNKNOWN"}</strong>
                    <p className="mt-1 text-sm opacity-90">Risk is calculated by the backend using collateral factors and configured reference prices. Unsafe transfers and borrows are blocked server-side.</p>
                  </div>
                </div>
              </div>

              <div className="mt-5 grid gap-4 xl:grid-cols-2">
                <Panel title="Assets">
                  <DataTable empty="No margin assets yet" rows={health?.assets || []} columns={[["Asset", (row) => row.asset], ["Amount", (row) => formatMoney(row.amount, row.asset)], ["Collateral", (row) => formatMoney(row.adjusted_collateral)]]} />
                </Panel>
                <Panel title="Loans">
                  <DataTable empty="No active loans" rows={loans} columns={[["Asset", (row) => row.asset], ["Principal", (row) => formatMoney(row.principal, row.asset)], ["Interest", (row) => formatMoney(row.accrued_interest, row.asset)], ["Status", (row) => row.status]]} />
                </Panel>
                <Panel title="Margin Orders">
                  <DataTable
                    empty="No margin orders yet"
                    rows={orders}
                    columns={[
                      ["Pair", (row) => row.pair],
                      ["Side", (row) => row.side],
                      ["Amount", (row) => formatMoney(row.amount, row.pair?.split("/")?.[0] || "")],
                      ["Mode", (row) => row.borrow_mode],
                      ["Status", (row) => row.spot_order?.status || row.status],
                      ["Action", (row) => ["open", "accepted", "partially_filled", "SUBMITTED"].includes(row.spot_order?.status || row.status) ? <button type="button" onClick={() => cancelMarginOrder(row.margin_order_uuid)} className="rounded-lg border border-red-400/30 px-2 py-1 text-xs text-red-200">Cancel</button> : "--"],
                    ]}
                  />
                </Panel>
              </div>
            </>
          )}
        </div>

        <aside className="space-y-4">
          {error ? <div className="rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-100">{error}</div> : null}
          {message ? <div className="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{message}</div> : null}

          <Panel title="Account Setup">
            <div className="grid gap-2">
              <select value={accountMode} onChange={(event) => setAccountMode(event.target.value)} className={inputClass}>
                <option value="CROSS">Cross Margin</option>
                <option value="ISOLATED">Isolated Margin</option>
              </select>
              {accountMode === "ISOLATED" ? <input className={inputClass} value={isolatedPair} onChange={(event) => setIsolatedPair(event.target.value)} placeholder="BTC/USDT" /> : null}
              <button type="button" onClick={createAccount} disabled={Boolean(busy)} className={primaryClass}>{busy === "Account setup" ? "Working..." : "Create / Select Account"}</button>
            </div>
          </Panel>

          <Panel title="Transfer Collateral">
            <div className="grid gap-2">
              <select className={inputClass} value={transfer.direction} onChange={(event) => setTransfer({ ...transfer, direction: event.target.value })}>
                <option value="IN">Funding to Margin</option>
                <option value="OUT">Margin to Funding</option>
              </select>
              <input className={inputClass} value={transfer.asset} onChange={(event) => setTransfer({ ...transfer, asset: event.target.value.toUpperCase() })} placeholder="USDT" />
              <input className={inputClass} value={transfer.amount} onChange={(event) => setTransfer({ ...transfer, amount: event.target.value })} placeholder="Amount" inputMode="decimal" />
              <button type="button" onClick={transferFunds} disabled={!selected || !transfer.amount || Boolean(busy)} className={primaryClass}>{busy === "Transfer" ? "Checking risk..." : "Transfer"}</button>
            </div>
          </Panel>

          <Panel title="Borrow">
            <div className="grid gap-2">
              <input className={inputClass} value={borrow.asset} onChange={(event) => setBorrow({ ...borrow, asset: event.target.value.toUpperCase() })} placeholder="USDT" />
              <input className={inputClass} value={borrow.amount} onChange={(event) => setBorrow({ ...borrow, amount: event.target.value })} placeholder="Amount" inputMode="decimal" />
              <button type="button" onClick={borrowAsset} disabled={!selected || !borrow.amount || Boolean(busy)} className={primaryClass}>{busy === "Borrow" ? "Borrowing..." : "Borrow Asset"}</button>
            </div>
          </Panel>

          <Panel title="Margin Order">
            <div className="grid gap-2">
              <input className={inputClass} value={order.pair} onChange={(event) => setOrder({ ...order, pair: event.target.value.toUpperCase() })} placeholder="BTC/USDT" />
              <div className="grid grid-cols-2 gap-2">
                <select className={inputClass} value={order.side} onChange={(event) => setOrder({ ...order, side: event.target.value })}>
                  <option value="buy">Buy</option>
                  <option value="sell">Sell</option>
                </select>
                <select className={inputClass} value={order.borrow_mode} onChange={(event) => setOrder({ ...order, borrow_mode: event.target.value })}>
                  <option value="NORMAL">Normal</option>
                  <option value="AUTO_BORROW">Auto Borrow</option>
                  <option value="AUTO_REPAY">Auto Repay</option>
                </select>
              </div>
              <input className={inputClass} value={order.amount} onChange={(event) => setOrder({ ...order, amount: event.target.value })} placeholder="Amount" inputMode="decimal" />
              <input className={inputClass} value={order.price} onChange={(event) => setOrder({ ...order, price: event.target.value })} placeholder="Limit price" inputMode="decimal" />
              <button type="button" onClick={placeMarginOrder} disabled={!selected || !order.amount || !order.price || Boolean(busy)} className={primaryClass}>{busy === "Margin order" ? "Routing..." : "Place Margin Order"}</button>
              <p className="text-xs leading-5 text-slate-500">Orders route through the authoritative Spot engine with Margin account settlement context. Market buys require explicit price protection in this checkpoint.</p>
            </div>
          </Panel>

          <Panel title="Repay">
            <div className="grid gap-2">
              <select className={inputClass} value={repay.loan_uuid || activeLoan?.loan_uuid || ""} onChange={(event) => setRepay({ ...repay, loan_uuid: event.target.value })}>
                {loans.length ? loans.map((loan) => <option key={loan.loan_uuid} value={loan.loan_uuid}>{loan.asset} debt - {loan.status}</option>) : <option value="">No active loans</option>}
              </select>
              <input className={inputClass} value={repay.amount} onChange={(event) => setRepay({ ...repay, amount: event.target.value })} placeholder="Repayment amount" inputMode="decimal" />
              <button type="button" onClick={repayLoan} disabled={!repay.loan_uuid || !repay.amount || Boolean(busy)} className={primaryClass}>{busy === "Repay" ? "Repaying..." : "Repay Loan"}</button>
            </div>
          </Panel>

          <Panel title="Lending Pools">
            <DataTable empty="No funded lending pools" rows={pools} columns={[["Asset", (row) => row.asset], ["Available", (row) => formatMoney(row.available_liquidity, row.asset)], ["Borrowed", (row) => formatMoney(row.borrowed_liquidity, row.asset)]]} />
          </Panel>

          <div className="grid grid-cols-2 gap-2">
            <button type="button" onClick={onOpenSpot} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200">Open Spot</button>
            <button type="button" onClick={onOpenFutures} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200">Open Futures</button>
          </div>
        </aside>
      </section>
    </main>
  );
}

function Metric({ icon, label, value }) {
  return <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4"><div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{icon}{label}</div><div className="mt-3 text-xl font-semibold tabular-nums text-white">{value}</div></div>;
}

function Panel({ title, children }) {
  return <section className="rounded-2xl border border-white/10 bg-[#0d1118] p-4"><h2 className="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-[#d6b45f]">{title}</h2>{children}</section>;
}

function DataTable({ rows, columns, empty }) {
  if (!rows?.length) {
    return <p className="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-4 text-sm text-slate-400">{empty}</p>;
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-max text-left text-sm">
        <thead className="text-xs uppercase tracking-[0.16em] text-slate-500"><tr>{columns.map(([label]) => <th key={label} className="px-2 py-2 font-semibold">{label}</th>)}</tr></thead>
        <tbody>{rows.map((row, index) => <tr key={row.id || row.loan_uuid || row.asset || index} className="border-t border-white/8 text-slate-200">{columns.map(([label, render]) => <td key={label} className="px-2 py-2 tabular-nums">{render(row)}</td>)}</tr>)}</tbody>
      </table>
    </div>
  );
}
