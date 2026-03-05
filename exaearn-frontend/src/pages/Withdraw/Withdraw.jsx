import { useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeInfo,
  Building2,
  ChevronDown,
  ChevronRight,
  Landmark,
  ShieldCheck,
  UserRound,
  Wallet,
} from "lucide-react";

const assets = [
  { symbol: "USDT", name: "Tether", icon: "US", balance: "4,820.42 USDT" },
  { symbol: "XRP", name: "Ripple", icon: "XR", balance: "2,840.55 XRP" },
  { symbol: "BTC", name: "Bitcoin", icon: "BT", balance: "0.146 BTC" },
  { symbol: "ETH", name: "Ethereum", icon: "ET", balance: "0.820 ETH" },
];

const networks = ["TRC20", "ERC20", "BEP20"];
const fiatPayoutMethods = ["Bank Account", "Mobile Money"];
const mobileMoneyProviders = ["MTN MoMo", "Airtel Money", "M-Pesa", "Vodafone Cash"];
const mobileCountryCodes = ["+234", "+233", "+254", "+1", "+44"];

const methods = [
  {
    id: "internal",
    title: "Internal Transfer",
    description: "Send to ExaEarn user (email, ID, or username).",
    icon: UserRound,
  },
  {
    id: "crypto",
    title: "Crypto Withdrawal",
    description: "Withdraw to an external wallet address.",
    icon: Wallet,
  },
  {
    id: "fiat",
    title: "Fiat Withdrawal",
    description: "Withdraw to bank account or mobile money.",
    icon: Landmark,
  },
];

function Withdraw({ onBack }) {
  const [selectedMethod, setSelectedMethod] = useState("crypto");
  const [asset, setAsset] = useState("USDT");
  const [network, setNetwork] = useState("TRC20");
  const [address, setAddress] = useState("");
  const [amount, setAmount] = useState("");
  const [internalRecipientType, setInternalRecipientType] = useState("email");
  const [internalEmail, setInternalEmail] = useState("");
  const [internalMobileCode, setInternalMobileCode] = useState("+234");
  const [internalMobile, setInternalMobile] = useState("");
  const [internalUid, setInternalUid] = useState("");
  const [internalAmount, setInternalAmount] = useState("");
  const [fiatAmount, setFiatAmount] = useState("");
  const [fiatPayoutMethod, setFiatPayoutMethod] = useState("Bank Account");
  const [bankName, setBankName] = useState("");
  const [accountNumber, setAccountNumber] = useState("");
  const [accountName, setAccountName] = useState("");
  const [mobileProvider, setMobileProvider] = useState("MTN MoMo");
  const [mobileNumber, setMobileNumber] = useState("");

  const selectedAsset = assets.find((item) => item.symbol === asset) || assets[0];
  const numericAmount = Number.parseFloat(amount || "0");
  const validAmount = Number.isNaN(numericAmount) ? 0 : numericAmount;
  const numericInternalAmount = Number.parseFloat(internalAmount || "0");
  const validInternalAmount = Number.isNaN(numericInternalAmount) ? 0 : numericInternalAmount;
  const numericFiatAmount = Number.parseFloat(fiatAmount || "0");
  const validFiatAmount = Number.isNaN(numericFiatAmount) ? 0 : numericFiatAmount;
  const networkFee = useMemo(() => {
    if (network === "ERC20") return 4.5;
    if (network === "BEP20") return 1.2;
    return 0.8;
  }, [network]);
  const youReceive = Math.max(validAmount - networkFee, 0);
  const internalFee = 0;
  const internalYouReceive = Math.max(validInternalAmount - internalFee, 0);
  const fiatFee = fiatPayoutMethod === "Bank Account" ? 1.5 : 1;
  const fiatYouReceive = Math.max(validFiatAmount - fiatFee, 0);
  const internalRecipientIsValid = useMemo(() => {
    if (internalRecipientType === "email") return Boolean(internalEmail.trim());
    if (internalRecipientType === "mobile") return Boolean(internalMobileCode.trim()) && Boolean(internalMobile.trim());
    return Boolean(internalUid.trim());
  }, [internalEmail, internalMobile, internalMobileCode, internalRecipientType, internalUid]);
  const canSubmit = useMemo(() => {
    if (selectedMethod === "crypto") {
      return Boolean(address.trim()) && validAmount > 0;
    }
    if (selectedMethod === "internal") {
      return internalRecipientIsValid && validInternalAmount > 0;
    }
    if (selectedMethod === "fiat") {
      if (fiatPayoutMethod === "Bank Account") {
        return Boolean(bankName.trim()) && Boolean(accountNumber.trim()) && Boolean(accountName.trim()) && validFiatAmount > 0;
      }
      return Boolean(mobileProvider.trim()) && Boolean(mobileNumber.trim()) && validFiatAmount > 0;
    }
    return false;
  }, [
    accountName,
    accountNumber,
    address,
    bankName,
    fiatPayoutMethod,
    internalRecipientIsValid,
    mobileNumber,
    mobileProvider,
    selectedMethod,
    validAmount,
    validFiatAmount,
    validInternalAmount,
  ]);

  return (
    <main className="min-h-screen bg-gradient-to-br from-[#060708] via-[#0c0d11] to-[#15100a] px-4 pb-28 pt-7 text-neutral-100 sm:px-6 lg:px-8">
      <div className="mx-auto w-full max-w-4xl">
        <header className="mb-6">
          <div className="flex items-start justify-between gap-4">
            <div>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="mb-3 inline-flex items-center gap-2 rounded-lg border border-[#d1ab55]/30 bg-black/30 px-3 py-2 text-xs font-semibold text-[#f2d27f] transition hover:border-[#d1ab55]/55 hover:bg-[#d1ab55]/10"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-neutral-50 sm:text-4xl">Withdraw Funds</h1>
              <p className="mt-1 text-sm text-neutral-400">Securely transfer your assets from ExaEarn</p>
            </div>
            <button
              type="button"
              className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#d1ab55]/30 bg-black/25 text-[#e9c673] transition hover:border-[#d1ab55]/50 hover:bg-[#d1ab55]/10"
              aria-label="Withdraw information"
            >
              <BadgeInfo className="h-4 w-4" />
            </button>
          </div>
        </header>

        <section className="rounded-2xl border border-[#d1ab55]/30 bg-[#121212]/85 p-5 shadow-[0_18px_55px_rgba(0,0,0,.45)]">
          <p className="text-xs uppercase tracking-[0.16em] text-neutral-500">Available Balance</p>
          <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-3xl font-semibold text-[#f3d58a] sm:text-4xl">$12,450.23</p>
              <p className="mt-1 text-xs text-neutral-400">Total Balance</p>
            </div>
            <div className="rounded-xl border border-[#d1ab55]/25 bg-black/30 px-3 py-2">
              <div className="flex items-center gap-2">
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-neutral-800 to-neutral-950 text-[10px] font-bold text-[#f2d27f] ring-1 ring-[#d1ab55]/30">
                  {selectedAsset.icon}
                </span>
                <div>
                  <p className="text-xs text-neutral-400">Selected Asset</p>
                  <p className="text-sm font-semibold text-neutral-100">{selectedAsset.symbol} ▼</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-[#d1ab55]/20 bg-[#111111]/80 p-4 sm:p-5">
          <h2 className="text-base font-semibold text-neutral-100">Withdraw Method</h2>
          <div className="mt-3 space-y-2">
            {methods.map((method) => {
              const Icon = method.icon;
              const active = selectedMethod === method.id;
              return (
                <button
                  key={method.id}
                  type="button"
                  onClick={() => setSelectedMethod(method.id)}
                  className={`group flex w-full items-center justify-between rounded-xl border px-3 py-3 text-left transition ${
                    active
                      ? "border-[#d1ab55]/70 bg-[#d1ab55]/10"
                      : "border-neutral-800 bg-black/20 hover:border-[#d1ab55]/35"
                  }`}
                >
                  <span className="flex items-center gap-3">
                    <span
                      className={`inline-flex h-10 w-10 items-center justify-center rounded-lg ${
                        active ? "bg-[#d1ab55]/20 text-[#efcf7c]" : "bg-neutral-900 text-neutral-300"
                      }`}
                    >
                      <Icon className="h-5 w-5" />
                    </span>
                    <span>
                      <span className="block text-sm font-semibold text-neutral-100">{method.title}</span>
                      <span className="block text-xs text-neutral-400">{method.description}</span>
                    </span>
                  </span>
                  <ChevronRight className={`h-4 w-4 transition ${active ? "text-[#efcf7c]" : "text-neutral-500"}`} />
                </button>
              );
            })}
          </div>
        </section>

        {selectedMethod === "crypto" ? (
          <section className="mt-5 rounded-2xl border border-[#d1ab55]/20 bg-[#101010]/85 p-4 shadow-[0_14px_36px_rgba(0,0,0,.35)] sm:p-5">
            <h2 className="text-base font-semibold text-neutral-100">Crypto Withdrawal</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <FieldSelect
                label="Asset"
                value={asset}
                onChange={setAsset}
                options={assets.map((item) => item.symbol)}
              />
              <FieldSelect label="Network" value={network} onChange={setNetwork} options={networks} />
            </div>

            <div className="mt-3">
              <label className="mb-2 block text-xs text-neutral-500">Wallet Address</label>
              <input
                value={address}
                onChange={(event) => setAddress(event.target.value)}
                placeholder="Paste destination wallet address"
                className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
              />
            </div>

            <div className="mt-3">
              <label className="mb-2 block text-xs text-neutral-500">Amount</label>
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                  placeholder="0.00"
                  className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                />
                <button
                  type="button"
                  onClick={() => setAmount("100")}
                  className="rounded-xl border border-[#d1ab55]/40 bg-[#d1ab55]/10 px-4 py-3 text-xs font-semibold text-[#f2d27f] transition hover:border-[#d1ab55]/70"
                >
                  Max
                </button>
              </div>
            </div>

            <div className="mt-4 rounded-xl border border-neutral-800 bg-black/30 p-3 text-xs">
              <LineItem label="Network Fee" value={`${networkFee.toFixed(2)} ${asset}`} />
              <LineItem label="You Will Receive" value={`${youReceive.toFixed(4)} ${asset}`} />
              <p className="mt-2 inline-flex items-center gap-1 rounded-md border border-[#d1ab55]/30 bg-[#d1ab55]/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#efcf7c]">
                <ShieldCheck className="h-3.5 w-3.5" />
                Verified Withdrawal Route
              </p>
            </div>
          </section>
        ) : null}

        {selectedMethod === "internal" ? (
          <section className="mt-5 rounded-2xl border border-[#d1ab55]/20 bg-[#101010]/85 p-4 shadow-[0_14px_36px_rgba(0,0,0,.35)] sm:p-5">
            <h2 className="text-base font-semibold text-neutral-100">Internal Transfer</h2>

            <div className="mt-3">
              <p className="mb-2 text-xs text-neutral-500">Recipient Type</p>
              <div className="grid grid-cols-3 gap-2">
                {[
                  { id: "email", label: "Email" },
                  { id: "mobile", label: "Mobile" },
                  { id: "uid", label: "UID" },
                ].map((recipientType) => {
                  const active = internalRecipientType === recipientType.id;
                  return (
                    <button
                      key={recipientType.id}
                      type="button"
                      onClick={() => setInternalRecipientType(recipientType.id)}
                      className={`rounded-xl border px-3 py-2 text-sm font-semibold transition ${
                        active
                          ? "border-[#d1ab55]/70 bg-[#d1ab55]/12 text-[#f2d27f]"
                          : "border-neutral-700 bg-black/35 text-neutral-300 hover:border-[#d1ab55]/35"
                      }`}
                    >
                      {recipientType.label}
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="mt-3">
              {internalRecipientType === "email" ? (
                <label className="block">
                  <span className="mb-2 block text-xs text-neutral-500">Recipient Email</span>
                  <input
                    type="email"
                    value={internalEmail}
                    onChange={(event) => setInternalEmail(event.target.value)}
                    placeholder="recipient@email.com"
                    className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                  />
                </label>
              ) : null}

              {internalRecipientType === "mobile" ? (
                <div className="grid gap-3 sm:grid-cols-3">
                  <FieldSelect label="Country Code" value={internalMobileCode} onChange={setInternalMobileCode} options={mobileCountryCodes} />
                  <label className="block sm:col-span-2">
                    <span className="mb-2 block text-xs text-neutral-500">Mobile Number</span>
                    <input
                      type="tel"
                      value={internalMobile}
                      onChange={(event) => setInternalMobile(event.target.value)}
                      placeholder="8012345678"
                      className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                    />
                  </label>
                </div>
              ) : null}

              {internalRecipientType === "uid" ? (
                <label className="block">
                  <span className="mb-2 block text-xs text-neutral-500">Recipient UID</span>
                  <input
                    value={internalUid}
                    onChange={(event) => setInternalUid(event.target.value)}
                    placeholder="Enter user UID"
                    className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                  />
                </label>
              ) : null}
            </div>

            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <FieldSelect
                label="Asset"
                value={asset}
                onChange={setAsset}
                options={assets.map((item) => item.symbol)}
              />
              <label className="block">
                <span className="mb-2 block text-xs text-neutral-500">Amount</span>
                <input
                  type="number"
                  value={internalAmount}
                  onChange={(event) => setInternalAmount(event.target.value)}
                  placeholder="0.00"
                  className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                />
              </label>
            </div>

            <div className="mt-4 rounded-xl border border-neutral-800 bg-black/30 p-3 text-xs">
              <LineItem label="Transfer Fee" value={`${internalFee.toFixed(2)} ${asset}`} />
              <LineItem label="Recipient Gets" value={`${internalYouReceive.toFixed(4)} ${asset}`} />
              <p className="mt-2 inline-flex items-center gap-1 rounded-md border border-[#d1ab55]/30 bg-[#d1ab55]/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#efcf7c]">
                <ShieldCheck className="h-3.5 w-3.5" />
                Instant Internal Settlement
              </p>
            </div>
          </section>
        ) : null}

        {selectedMethod === "fiat" ? (
          <section className="mt-5 rounded-2xl border border-[#d1ab55]/20 bg-[#101010]/85 p-4 shadow-[0_14px_36px_rgba(0,0,0,.35)] sm:p-5">
            <h2 className="text-base font-semibold text-neutral-100">Fiat Withdrawal</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <FieldSelect label="Payout Method" value={fiatPayoutMethod} onChange={setFiatPayoutMethod} options={fiatPayoutMethods} />
              <label className="block">
                <span className="mb-2 block text-xs text-neutral-500">Amount (USD)</span>
                <input
                  type="number"
                  value={fiatAmount}
                  onChange={(event) => setFiatAmount(event.target.value)}
                  placeholder="0.00"
                  className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                />
              </label>
            </div>

            {fiatPayoutMethod === "Bank Account" ? (
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <label className="block sm:col-span-2">
                  <span className="mb-2 block text-xs text-neutral-500">Bank Name</span>
                  <input
                    value={bankName}
                    onChange={(event) => setBankName(event.target.value)}
                    placeholder="Enter bank name"
                    className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                  />
                </label>
                <label className="block">
                  <span className="mb-2 block text-xs text-neutral-500">Account Number</span>
                  <input
                    value={accountNumber}
                    onChange={(event) => setAccountNumber(event.target.value)}
                    placeholder="Enter account number"
                    className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                  />
                </label>
                <label className="block">
                  <span className="mb-2 block text-xs text-neutral-500">Account Name</span>
                  <input
                    value={accountName}
                    onChange={(event) => setAccountName(event.target.value)}
                    placeholder="Enter account name"
                    className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                  />
                </label>
              </div>
            ) : (
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <FieldSelect label="Mobile Money Provider" value={mobileProvider} onChange={setMobileProvider} options={mobileMoneyProviders} />
                <label className="block">
                  <span className="mb-2 block text-xs text-neutral-500">Mobile Number</span>
                  <input
                    value={mobileNumber}
                    onChange={(event) => setMobileNumber(event.target.value)}
                    placeholder="Enter mobile number"
                    className="w-full rounded-xl border border-neutral-700 bg-black/35 px-3 py-3 text-sm text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d1ab55]/70 focus:ring-2 focus:ring-[#d1ab55]/20"
                  />
                </label>
              </div>
            )}

            <div className="mt-4 rounded-xl border border-neutral-800 bg-black/30 p-3 text-xs">
              <LineItem label="Processing Fee" value={`$${fiatFee.toFixed(2)}`} />
              <LineItem label="You Will Receive" value={`$${fiatYouReceive.toFixed(2)}`} />
              <p className="mt-2 inline-flex items-center gap-1 rounded-md border border-[#d1ab55]/30 bg-[#d1ab55]/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#efcf7c]">
                <Building2 className="h-3.5 w-3.5" />
                Bank & Mobile Money Payout
              </p>
            </div>
          </section>
        ) : null}

        <section className="mt-5 space-y-3">
          <div className="rounded-xl border border-amber-300/30 bg-amber-400/10 p-3 text-xs text-amber-100">
            Ensure the network matches the destination address. Incorrect transfers may result in permanent loss.
          </div>
          <div className="rounded-xl border border-[#d1ab55]/20 bg-black/30 p-3 text-xs text-neutral-300">
            <p className="flex items-start gap-2">
              <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-[#efcf7c]" />
              Withdrawals are protected by ExaEarn security protocols and 2FA verification.
            </p>
          </div>
        </section>

        <div className="mt-5 hidden gap-3 sm:grid sm:grid-cols-2">
          <button
            type="button"
            disabled={!canSubmit}
            className="rounded-xl bg-gradient-to-r from-[#f7df8f] via-[#d1ab55] to-[#ad832a] px-4 py-3 text-sm font-semibold text-[#1b1509] shadow-[0_0_25px_rgba(209,171,85,.35)] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-45"
          >
            Withdraw Now
          </button>
          <button
            type="button"
            className="rounded-xl border border-neutral-700 bg-black/35 px-4 py-3 text-sm font-semibold text-neutral-200 transition hover:border-neutral-500"
          >
            Cancel
          </button>
        </div>
      </div>

      <div className="fixed inset-x-0 bottom-0 z-30 border-t border-[#d1ab55]/20 bg-[#0a0a0a]/95 p-3 backdrop-blur sm:hidden">
        <button
          type="button"
          disabled={!canSubmit}
          className="w-full rounded-xl bg-gradient-to-r from-[#f7df8f] via-[#d1ab55] to-[#ad832a] px-4 py-3 text-sm font-semibold text-[#1b1509] shadow-[0_0_25px_rgba(209,171,85,.35)] transition disabled:cursor-not-allowed disabled:opacity-45"
        >
          Withdraw Now
        </button>
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

export default Withdraw;
