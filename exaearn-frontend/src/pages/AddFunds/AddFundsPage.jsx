import { useMemo, useState } from "react";
import {
  ArrowDownToLine,
  ArrowLeft,
  ArrowRight,
  BarChart3,
  Building2,
  CheckCircle2,
  ChevronDown,
  Eye,
  EyeOff,
  Landmark,
  Lock,
  Mail,
  Phone,
  QrCode,
  ReceiptText,
  Send,
  ShieldCheck,
  Smartphone,
  Users,
  Wallet,
} from "lucide-react";

const assets = [
  { symbol: "USDT", label: "Tether USD", balance: "4,820.42 USDT" },
  { symbol: "BTC", label: "Bitcoin", balance: "0.146 BTC" },
  { symbol: "ETH", label: "Ethereum", balance: "0.820 ETH" },
  { symbol: "XRP", label: "Ripple", balance: "2,840.55 XRP" },
];

const networks = ["TRC20", "ERC20", "BEP20"];
const fiatMethods = ["Bank Transfer", "Card Payment", "Payment Gateway"];
const receiveTypes = ["Username", "Email", "ExaEarn ID"];

function AddFundsPage({ onBack, onOpenSend, onOpenSwap, onOpenWithdraw, onOpenP2P }) {
  const [showBalance, setShowBalance] = useState(true);
  const [currency, setCurrency] = useState("USD");
  const [selectedMethod, setSelectedMethod] = useState("deposit-crypto");
  const [asset, setAsset] = useState("USDT");
  const [network, setNetwork] = useState("TRC20");
  const [fiatMethod, setFiatMethod] = useState("Bank Transfer");
  const [receiveType, setReceiveType] = useState("Username");
  const [fiatAmount, setFiatAmount] = useState("");

  const pnl = useMemo(() => ({ value: "+4.2%", positive: true }), []);
  const selectedAsset = assets.find((item) => item.symbol === asset) || assets[0];
  const networkConfirmations = useMemo(() => {
    if (network === "ERC20") return "12 confirmations";
    if (network === "BEP20") return "15 confirmations";
    return "1 confirmation";
  }, [network]);
  const depositAddress = useMemo(() => {
    if (network === "ERC20") return "0xEA83...9F2C";
    if (network === "BEP20") return "0xB31E...4D7A";
    return "TKk4...X9mP";
  }, [network]);
  const receiveHandle = useMemo(() => {
    if (receiveType === "Email") return "wallet@exaearn.com";
    if (receiveType === "ExaEarn ID") return "EXA-41033651";
    return "@exaearnwallet";
  }, [receiveType]);

  const methods = [
    {
      id: "deposit-crypto",
      title: "Deposit Crypto",
      description: "Deposit crypto from external wallets or exchanges into ExaEarn.",
      icon: ArrowDownToLine,
      featured: false,
    },
    {
      id: "exa-pay",
      title: "Receive via ExaEarn Pay",
      description: "Instantly receive crypto from other ExaEarn users.",
      icon: QrCode,
      featured: false,
    },
    {
      id: "deposit-fiat",
      title: "Deposit Fiat (USD / Local Currency)",
      description: "Fund your account via bank transfer, card payment, or supported gateways.",
      icon: Landmark,
      featured: false,
    },
    {
      id: "p2p",
      title: "P2P Marketplace",
      description: "Buy crypto directly from verified users using local payment methods.",
      icon: Users,
      featured: true,
    },
  ];

  return (
    <main className="min-h-screen bg-gradient-to-br from-[#060708] via-[#0c0d11] to-[#15100a] px-4 pb-28 pt-7 text-neutral-100 sm:px-6 lg:px-8">
      <div className="mx-auto w-full max-w-4xl">
        <header className="mb-6">
          <div className="flex items-start justify-between gap-4">
            <div>
              <button
                type="button"
                onClick={onBack}
                className="mb-3 inline-flex items-center gap-2 rounded-lg border border-[#d1ab55]/30 bg-black/30 px-3 py-2 text-xs font-semibold text-[#f2d27f] transition hover:border-[#d1ab55]/55 hover:bg-[#d1ab55]/10"
              >
                <ArrowLeft className="h-4 w-4" />
                Back
              </button>
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-neutral-50 sm:text-4xl">Add Funds</h1>
              <p className="mt-1 text-sm text-neutral-400">Choose how you want to fund your ExaEarn account</p>
            </div>
          </div>
        </header>

        <section className="rounded-2xl border border-[#d1ab55]/30 bg-[#121212]/85 p-5 shadow-[0_18px_55px_rgba(0,0,0,.45)]">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-xs uppercase tracking-[0.16em] text-neutral-500">Estimated Total Value</p>
              <div className="mt-1 flex items-center gap-2">
                <p className="font-['Sora'] text-3xl font-semibold text-[#f3d58a] sm:text-4xl">
                  {showBalance ? "$125,480.20" : "******"}
                </p>
                <select
                  value={currency}
                  onChange={(event) => setCurrency(event.target.value)}
                  className="rounded-lg border border-[#d1ab55]/25 bg-black/35 px-2 py-1 text-xs font-semibold text-neutral-100 outline-none transition focus:border-[#d1ab55]/70"
                >
                  <option value="USD">USD</option>
                  <option value="NGN">NGN</option>
                  <option value="EUR">EUR</option>
                </select>
              </div>
              <p className={`mt-2 text-xs font-semibold ${pnl.positive ? "text-emerald-300" : "text-rose-300"}`}>
                {pnl.value} Today&apos;s PNL
              </p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
              <button
                type="button"
                onClick={() => setShowBalance((prev) => !prev)}
                className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#d1ab55]/25 bg-black/30 text-neutral-100 transition hover:border-[#d1ab55]/60 hover:text-[#f2d27f]"
                aria-label="Toggle balance visibility"
              >
                {showBalance ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
              </button>
              <button
                type="button"
                className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#d1ab55]/25 bg-black/30 text-neutral-100 transition hover:border-[#d1ab55]/60 hover:text-[#f2d27f]"
                aria-label="Portfolio analytics"
              >
                <BarChart3 className="h-4 w-4" />
              </button>
            </div>
          </div>

          <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
            <button
              type="button"
              className="rounded-xl bg-gradient-to-r from-[#f7df8f] via-[#d1ab55] to-[#ad832a] px-3 py-2.5 text-sm font-semibold text-[#1b1509] shadow-[0_0_25px_rgba(209,171,85,.35)] transition-all duration-300 hover:brightness-105 active:scale-[0.98]"
            >
              Add Funds
            </button>
            <button
              type="button"
              onClick={onOpenSend}
              className="rounded-xl border border-neutral-700 bg-black/35 px-3 py-2.5 text-sm font-semibold text-neutral-100 transition-all duration-300 hover:border-[#d1ab55]/55 hover:text-[#f2d27f] active:scale-[0.98]"
            >
              <span className="inline-flex items-center gap-1">
                <Send className="h-4 w-4" />
                Send
              </span>
            </button>
            <button
              type="button"
              onClick={onOpenSwap}
              className="rounded-xl border border-neutral-700 bg-black/35 px-3 py-2.5 text-sm font-semibold text-neutral-100 transition-all duration-300 hover:border-[#d1ab55]/55 hover:text-[#f2d27f] active:scale-[0.98]"
            >
              <span className="inline-flex items-center gap-1">
                <ArrowRight className="h-4 w-4" />
                Swap
              </span>
            </button>
            <button
              type="button"
              onClick={onOpenWithdraw}
              className="rounded-xl border border-neutral-700 bg-black/35 px-3 py-2.5 text-sm font-semibold text-neutral-100 transition-all duration-300 hover:border-[#d1ab55]/55 hover:text-[#f2d27f] active:scale-[0.98]"
            >
              <span className="inline-flex items-center gap-1">
                <ReceiptText className="h-4 w-4" />
                Withdraw
              </span>
            </button>
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-[#d1ab55]/20 bg-[#111111]/80 p-4 sm:p-5">
          <h2 className="text-base font-semibold text-neutral-100">Select Funding Method</h2>
          <div className="mt-3 space-y-2">
            {methods.map((method) => {
              const Icon = method.icon;
              const active = selectedMethod === method.id;
              return (
                <button
                  type="button"
                  key={method.id}
                  onClick={() => {
                    if (method.id === "p2p") {
                      onOpenP2P?.();
                      return;
                    }
                    setSelectedMethod(method.id);
                  }}
                  className={`group flex w-full items-center justify-between rounded-xl border px-3 py-3 text-left transition ${
                    active
                      ? "border-[#d1ab55]/70 bg-[#d1ab55]/10"
                      : method.featured
                        ? "border-[#d1ab55]/35 bg-[#d1ab55]/6 hover:border-[#d1ab55]/55"
                        : "border-neutral-800 bg-black/20 hover:border-[#d1ab55]/35"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${
                      active ? "bg-[#d1ab55]/20 text-[#efcf7c]" : "bg-neutral-900 text-neutral-300"
                    }`}>
                      <Icon className="h-5 w-5" />
                    </span>
                    <span className="min-w-0">
                      <span className="block text-sm font-semibold text-neutral-100">{method.title}</span>
                      <span className="mt-0.5 block text-xs text-neutral-400">{method.description}</span>
                    </span>
                  </div>
                  <ArrowRight className={`h-5 w-5 shrink-0 transition-all duration-300 group-hover:translate-x-1 ${
                    active || method.featured ? "text-[#efcf7c]" : "text-neutral-500"
                  }`} />
                </button>
              );
            })}
          </div>
        </section>

        {selectedMethod === "deposit-crypto" ? (
          <section className="mt-5 rounded-2xl border border-[#d1ab55]/20 bg-[#101010]/85 p-4 shadow-[0_14px_36px_rgba(0,0,0,.35)] sm:p-5">
            <h2 className="text-base font-semibold text-neutral-100">Deposit Crypto From External Wallets or Exchanges Into ExaEarn</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <FieldSelect label="Asset" value={asset} onChange={setAsset} options={assets.map((item) => item.symbol)} />
              <FieldSelect label="Network" value={network} onChange={setNetwork} options={networks} />
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
              <div className="rounded-2xl border border-neutral-800 bg-black/30 p-4">
                <label className="mb-2 block text-xs text-neutral-500">Deposit Address</label>
                <div className="rounded-xl border border-[#d1ab55]/25 bg-[#d1ab55]/8 px-4 py-3 text-sm font-medium text-[#f3d58a]">
                  {depositAddress}
                </div>
                <div className="mt-3 space-y-2 text-xs text-neutral-400">
                  <p className="flex items-center gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-300" />
                    Only send {asset} via the {network} network.
                  </p>
                  <p className="flex items-center gap-2">
                    <ShieldCheck className="h-4 w-4 text-[#efcf7c]" />
                    Funds credit after {networkConfirmations}.
                  </p>
                </div>
              </div>

              <div className="rounded-2xl border border-[#d1ab55]/25 bg-gradient-to-br from-[#1a1711] to-[#0f0f0f] p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs uppercase tracking-[0.14em] text-neutral-500">Scan to Deposit</p>
                    <p className="mt-1 text-sm font-semibold text-neutral-100">{selectedAsset.label}</p>
                  </div>
                  <Wallet className="h-5 w-5 text-[#efcf7c]" />
                </div>
                <div className="mt-4 flex aspect-square items-center justify-center rounded-2xl border border-dashed border-[#d1ab55]/35 bg-black/35">
                  <div className="text-center">
                    <QrCode className="mx-auto h-16 w-16 text-[#efcf7c]" />
                    <p className="mt-3 text-xs text-neutral-400">Share this code with your external wallet or exchange</p>
                  </div>
                </div>
              </div>
            </div>

            <div className="mt-4 rounded-xl border border-neutral-800 bg-black/30 p-3 text-xs">
              <LineItem label="Min Deposit" value={`10 ${asset}`} />
              <LineItem label="Estimated Arrival" value="1-10 minutes" />
              <LineItem label="Current Asset Balance" value={selectedAsset.balance} />
            </div>
          </section>
        ) : null}

        {selectedMethod === "exa-pay" ? (
          <section className="mt-5 rounded-2xl border border-[#d1ab55]/20 bg-[#101010]/85 p-4 shadow-[0_14px_36px_rgba(0,0,0,.35)] sm:p-5">
            <h2 className="text-base font-semibold text-neutral-100">Receive via ExaEarn Pay</h2>
            <p className="mt-1 text-sm text-neutral-400">Instantly receive crypto from other ExaEarn users.</p>

            <div className="mt-4">
              <p className="mb-2 text-xs text-neutral-500">Receive With</p>
              <div className="grid grid-cols-3 gap-2">
                {receiveTypes.map((type) => {
                  const active = receiveType === type;
                  return (
                    <button
                      key={type}
                      type="button"
                      onClick={() => setReceiveType(type)}
                      className={`rounded-xl border px-3 py-2 text-sm font-semibold transition ${
                        active
                          ? "border-[#d1ab55]/70 bg-[#d1ab55]/12 text-[#f2d27f]"
                          : "border-neutral-700 bg-black/35 text-neutral-300 hover:border-[#d1ab55]/35"
                      }`}
                    >
                      {type}
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-[0.95fr_1.05fr]">
              <div className="rounded-2xl border border-[#d1ab55]/25 bg-gradient-to-br from-[#1a1711] to-[#0f0f0f] p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs uppercase tracking-[0.14em] text-neutral-500">Personal Receive QR</p>
                    <p className="mt-1 text-sm font-semibold text-neutral-100">{receiveType}</p>
                  </div>
                  <QrCode className="h-5 w-5 text-[#efcf7c]" />
                </div>
                <div className="mt-4 flex aspect-square items-center justify-center rounded-2xl border border-dashed border-[#d1ab55]/35 bg-black/35">
                  <QrCode className="h-16 w-16 text-[#efcf7c]" />
                </div>
              </div>

              <div className="rounded-2xl border border-neutral-800 bg-black/30 p-4">
                <div className="flex items-center gap-3">
                  <span className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[#d1ab55]/12 text-[#efcf7c]">
                    {receiveType === "Email" ? <Mail className="h-5 w-5" /> : receiveType === "ExaEarn ID" ? <Smartphone className="h-5 w-5" /> : <Phone className="h-5 w-5" />}
                  </span>
                  <div>
                    <p className="text-xs text-neutral-500">Active Receive Handle</p>
                    <p className="text-lg font-semibold text-[#f3d58a]">{receiveHandle}</p>
                  </div>
                </div>

                <div className="mt-4 rounded-xl border border-[#d1ab55]/25 bg-[#d1ab55]/8 px-4 py-3 text-sm text-neutral-200">
                  Share your QR code or handle with the sender. Incoming transfers settle instantly within ExaEarn.
                </div>

                <div className="mt-4 rounded-xl border border-neutral-800 bg-[#0d0d0d] p-3 text-xs">
                  <LineItem label="Settlement Speed" value="Instant" />
                  <LineItem label="Transfer Fee" value="0.00" />
                  <LineItem label="Supported Assets" value="USDT, BTC, ETH, XRP" />
                </div>
              </div>
            </div>
          </section>
        ) : null}

        {selectedMethod === "deposit-fiat" ? (
          <section className="mt-5 rounded-2xl border border-[#d1ab55]/20 bg-[#101010]/85 p-4 shadow-[0_14px_36px_rgba(0,0,0,.35)] sm:p-5">
            <h2 className="text-base font-semibold text-neutral-100">Deposit Fiat (USD / Local Currency)</h2>
            <p className="mt-1 text-sm text-neutral-400">Fund your account via bank transfer, card payment, or supported gateway.</p>

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <FieldSelect label="Funding Method" value={fiatMethod} onChange={setFiatMethod} options={fiatMethods} />
              <FieldSelect label="Settlement Currency" value={currency} onChange={setCurrency} options={["USD", "NGN", "EUR"]} />
            </div>

            <div className="mt-3">
              <label className="block">
                <span className="mb-2 block text-xs text-neutral-500">Deposit Amount</span>
                <input
                  type="number"
                  value={fiatAmount}
                  onChange={(event) => setFiatAmount(event.target.value)}
                  placeholder="0.00"
                  className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                />
              </label>
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <div className="rounded-2xl border border-neutral-800 bg-black/30 p-4">
                <div className="flex items-center gap-3">
                  <span className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[#d1ab55]/12 text-[#efcf7c]">
                    {fiatMethod === "Bank Transfer" ? <Building2 className="h-5 w-5" /> : fiatMethod === "Card Payment" ? <Landmark className="h-5 w-5" /> : <Smartphone className="h-5 w-5" />}
                  </span>
                  <div>
                    <p className="text-sm font-semibold text-neutral-100">{fiatMethod}</p>
                    <p className="text-xs text-neutral-500">Preferred on-ramp channel</p>
                  </div>
                </div>
                <div className="mt-4 rounded-xl border border-[#d1ab55]/25 bg-[#d1ab55]/8 p-3 text-xs text-neutral-200">
                  Complete verification and follow the payment instructions shown here before your fiat deposit is credited.
                </div>
              </div>

              <div className="rounded-2xl border border-neutral-800 bg-black/30 p-4">
                <h3 className="text-sm font-semibold text-neutral-100">Funding Notes</h3>
                <div className="mt-3 space-y-2 text-xs text-neutral-400">
                  <p className="flex items-center gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-300" />
                    Deposits are matched to your verified ExaEarn identity.
                  </p>
                  <p className="flex items-center gap-2">
                    <ShieldCheck className="h-4 w-4 text-[#efcf7c]" />
                    Card and gateway payments may require additional checks.
                  </p>
                  <p className="flex items-center gap-2">
                    <Landmark className="h-4 w-4 text-[#efcf7c]" />
                    Bank transfers typically settle within a few minutes to a few hours.
                  </p>
                </div>
              </div>
            </div>

            <div className="mt-4 rounded-xl border border-neutral-800 bg-black/30 p-3 text-xs">
              <LineItem label="Minimum Fiat Deposit" value={`${currency} 25`} />
              <LineItem label="Processing Time" value={fiatMethod === "Bank Transfer" ? "Up to 2 hours" : "Instant to 15 minutes"} />
              <LineItem label="Gateway Support" value="Available in supported regions" />
            </div>
          </section>
        ) : null}

        <section className="mt-5 space-y-3">
          <div className="rounded-xl border border-amber-300/30 bg-amber-400/10 p-3 text-xs text-amber-100">
            Always confirm the selected network or payment rail before sending funds. Incorrect deposits may be delayed or unrecoverable.
          </div>
          <div className="rounded-xl border border-[#d1ab55]/20 bg-black/30 p-3 text-xs text-neutral-300">
            <p className="flex items-start gap-2">
              <Lock className="mt-0.5 h-4 w-4 shrink-0 text-[#efcf7c]" />
              ExaEarn protects your assets using verified transactions, internal account matching, and wallet security checks.
            </p>
          </div>
        </section>
      </div>
    </main>
  );
}

function FieldSelect({ label, value, onChange, options }) {
  return (
    <label className="block">
      <span className="mb-2 block text-xs text-neutral-500">{label}</span>
      <span className="relative block">
        <select
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="w-full appearance-none rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
        >
          {options.map((item) => (
            <option key={item} value={item} className="bg-[#111111] text-neutral-100">
              {item}
            </option>
          ))}
        </select>
        <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-500" />
      </span>
    </label>
  );
}

function LineItem({ label, value }) {
  return (
    <div className="flex items-center justify-between border-b border-neutral-800 pb-2 last:border-0 last:pb-0">
      <span className="text-neutral-500">{label}</span>
      <span className="font-medium text-neutral-100">{value}</span>
    </div>
  );
}

export default AddFundsPage;
