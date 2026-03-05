import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Check, Coins, Search, Wallet } from "lucide-react";

const currencyOptions = [
  { code: "USD", name: "US Dollar", symbol: "$", flag: "US", type: "fiat" },
  { code: "NGN", name: "Naira", symbol: "₦", flag: "NG", type: "fiat" },
  { code: "EUR", name: "Euro", symbol: "€", flag: "EU", type: "fiat" },
  { code: "GBP", name: "Pound Sterling", symbol: "£", flag: "GB", type: "fiat" },
  { code: "BTC", name: "Bitcoin", symbol: "₿", flag: "", type: "crypto" },
  { code: "ETH", name: "Ethereum", symbol: "Ξ", flag: "", type: "crypto" },
];

const storageKey = "exaearn-currency-preference";

const sampleRates = {
  USD: 1,
  NGN: 1530,
  EUR: 0.92,
  GBP: 0.78,
  BTC: 0.000013,
  ETH: 0.00044,
};

function CurrencyPreferencePage({ onBack }) {
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [displayCurrency, setDisplayCurrency] = useState("USD");
  const [transactionCurrency, setTransactionCurrency] = useState("USD");
  const [savedPrefs, setSavedPrefs] = useState({ displayCurrency: "USD", transactionCurrency: "USD" });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState("");

  useEffect(() => {
    const timer = setTimeout(() => {
      try {
        const raw = localStorage.getItem(storageKey);
        if (raw) {
          const parsed = JSON.parse(raw);
          const nextDisplay = parsed.displayCurrency || "USD";
          const nextTransaction = parsed.transactionCurrency || nextDisplay;
          setDisplayCurrency(nextDisplay);
          setTransactionCurrency(nextTransaction);
          setSavedPrefs({ displayCurrency: nextDisplay, transactionCurrency: nextTransaction });
        }
      } catch (error) {
        console.error("Unable to load currency preferences", error);
      } finally {
        setLoading(false);
      }
    }, 520);
    return () => clearTimeout(timer);
  }, []);

  const filteredCurrencies = useMemo(() => {
    const key = search.trim().toLowerCase();
    if (!key) return currencyOptions;
    return currencyOptions.filter(
      (item) =>
        item.code.toLowerCase().includes(key) ||
        item.name.toLowerCase().includes(key) ||
        item.type.toLowerCase().includes(key)
    );
  }, [search]);

  const hasChanges =
    displayCurrency !== savedPrefs.displayCurrency || transactionCurrency !== savedPrefs.transactionCurrency;

  const displayMeta = currencyOptions.find((item) => item.code === displayCurrency) || currencyOptions[0];
  const txMeta = currencyOptions.find((item) => item.code === transactionCurrency) || currencyOptions[0];

  const saveChanges = async () => {
    if (!hasChanges || saving) return;
    setSaving(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 780));
      const payload = { displayCurrency, transactionCurrency };
      localStorage.setItem(storageKey, JSON.stringify(payload));
      setSavedPrefs(payload);
      setToast("Currency preference saved.");
      setTimeout(() => setToast(""), 2200);
    } catch (error) {
      setToast("Unable to save preference.");
      setTimeout(() => setToast(""), 2200);
    } finally {
      setSaving(false);
    }
  };

  const convertedBalance = useMemo(() => {
    const usdBase = 123;
    const rate = sampleRates[displayCurrency] || 1;
    if (displayCurrency === "BTC" || displayCurrency === "ETH") {
      return `${(usdBase * rate).toFixed(6)} ${displayCurrency} (~$${usdBase.toFixed(2)} USD)`;
    }
    const amount = usdBase * rate;
    return `${displayMeta.symbol}${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} ${displayCurrency} (~$${usdBase.toFixed(2)} USD)`;
  }, [displayCurrency, displayMeta.symbol]);

  return (
    <main className="relative h-[100dvh] overflow-hidden bg-[#070B14] text-white">
      <header
        className="fixed inset-x-0 top-0 z-40 border-b border-[#D4AF37]/20 bg-gradient-to-r from-[#121A2A]/95 via-[#0E1524]/95 to-[#0A0F1D]/95 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-3xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#E6EAF2] hover:border-[#D4AF37]/60"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[#F8F1DE] sm:text-xl">Currency Preference</h1>
              <p className="text-xs text-[#B8C0CF] sm:text-sm">
                Choose your default currency for display and transactions
              </p>
            </div>
          </div>
        </div>
      </header>

      <section
        className="mx-auto h-full w-full max-w-3xl overflow-y-auto px-4 pb-28 pt-[90px] sm:px-6"
        style={{ paddingBottom: "calc(96px + env(safe-area-inset-bottom))" }}
      >
        {loading ? (
          <LoadingState />
        ) : (
          <>
            <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
              <div className="mb-3 flex items-center gap-2">
                <Coins className="h-4 w-4 text-[#D4AF37]" />
                <h2 className="text-base font-semibold text-[#F8F1DE]">Default Currency</h2>
              </div>
              <label className="mb-3 block">
                <div className="flex items-center gap-2 rounded-xl border border-white/15 bg-[#0C1424] px-3 py-2.5">
                  <Search className="h-4 w-4 text-[#D4AF37]" />
                  <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search currency..."
                    className="w-full bg-transparent text-sm text-white placeholder:text-[#8F99AB] outline-none"
                  />
                </div>
              </label>
              <div className="max-h-60 space-y-2 overflow-y-auto pr-1">
                {filteredCurrencies.map((item) => (
                  <button
                    key={item.code}
                    type="button"
                    onClick={() => setDisplayCurrency(item.code)}
                    className={`flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-left transition ${
                      displayCurrency === item.code
                        ? "border-[#D4AF37]/70 bg-[#D4AF37]/12 text-[#F3D88F]"
                        : "border-white/10 bg-[#0C1424] text-[#D7DDEA] hover:border-[#D4AF37]/40"
                    }`}
                  >
                    <div>
                      <p className="text-sm">
                        {item.code} - {item.name}
                      </p>
                      <p className="text-xs text-[#98A1B2]">
                        {item.symbol} • {item.type.toUpperCase()} {item.flag ? `• ${flagEmoji(item.flag)}` : ""}
                      </p>
                    </div>
                    {displayCurrency === item.code ? <Check className="h-4 w-4" /> : null}
                  </button>
                ))}
              </div>
            </article>

            <article className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
              <div className="mb-3 flex items-center gap-2">
                <Wallet className="h-4 w-4 text-[#D4AF37]" />
                <h2 className="text-base font-semibold text-[#F8F1DE]">Transaction Currency (Optional)</h2>
              </div>
              <div className="grid gap-2 sm:grid-cols-2">
                {currencyOptions.map((item) => (
                  <button
                    key={`tx-${item.code}`}
                    type="button"
                    onClick={() => setTransactionCurrency(item.code)}
                    className={`rounded-xl border px-3 py-2 text-left text-sm transition ${
                      transactionCurrency === item.code
                        ? "border-[#D4AF37]/70 bg-gradient-to-r from-[#D4AF37]/18 to-[#D4AF37]/5 text-[#F3D88F]"
                        : "border-white/10 bg-[#0C1424] text-[#D7DDEA] hover:border-[#D4AF37]/35"
                    }`}
                  >
                    {item.code} - {item.name}
                  </button>
                ))}
              </div>
            </article>

            <article className="mt-4 rounded-2xl border border-[#D4AF37]/30 bg-[#D4AF37]/12 p-4 shadow-[0_0_20px_rgba(212,175,55,0.2)]">
              <p className="text-xs uppercase tracking-[0.1em] text-[#EAC97A]">Real-time Conversion Preview</p>
              <p className="mt-1 text-sm text-[#F8F1DE]">
                Total Balance: {convertedBalance}
              </p>
              <p className="mt-1 text-xs text-[#D8C488]">
                Display: {displayMeta.code} • Transactions: {txMeta.code}
              </p>
            </article>
          </>
        )}
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[#D4AF37]/20 bg-[#0A0F1D]/95 p-3 backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="mx-auto w-full max-w-3xl">
          <button
            type="button"
            disabled={!hasChanges || saving || loading}
            onClick={saveChanges}
            className="w-full rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] py-3 text-sm font-semibold text-[#111827] shadow-[0_10px_24px_rgba(212,175,55,0.28)] disabled:cursor-not-allowed disabled:opacity-45"
          >
            {saving ? "Saving..." : "Save Changes"}
          </button>
        </div>
      </section>

      {toast ? (
        <div className="fixed right-4 top-24 z-50 rounded-xl border border-[#22C55E]/35 bg-[#22C55E]/12 px-3 py-2 text-xs text-[#BBF7D0] shadow-lg">
          {toast}
        </div>
      ) : null}
    </main>
  );
}

function LoadingState() {
  return (
    <div className="space-y-4">
      <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
        <div className="mb-3 h-5 w-40 animate-pulse rounded bg-gradient-to-r from-[#D4AF37]/25 to-transparent" />
        <div className="space-y-2">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-11 animate-pulse rounded-xl bg-gradient-to-r from-[#1C263A] via-[#243146] to-[#1C263A]" />
          ))}
        </div>
      </article>
      <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
        <div className="mb-3 h-5 w-44 animate-pulse rounded bg-gradient-to-r from-[#D4AF37]/25 to-transparent" />
        <div className="space-y-2">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-10 animate-pulse rounded-xl bg-gradient-to-r from-[#1C263A] via-[#243146] to-[#1C263A]" />
          ))}
        </div>
      </article>
    </div>
  );
}

function flagEmoji(code) {
  if (code === "EU") return "🇪🇺";
  return code
    .toUpperCase()
    .split("")
    .map((char) => String.fromCodePoint(127397 + char.charCodeAt()))
    .join("");
}

export default CurrencyPreferencePage;
