import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Check, Coins, Search, Wallet } from "lucide-react";
import { useAuth } from "../../context/AuthContext";

const currencyOptions = [
  { code: "USD", name: "US Dollar", symbol: "$", flag: "US", type: "fiat" },
  { code: "NGN", name: "Naira", symbol: "NGN", flag: "NG", type: "fiat" },
  { code: "EUR", name: "Euro", symbol: "EUR", flag: "EU", type: "fiat" },
  { code: "GBP", name: "Pound Sterling", symbol: "GBP", flag: "GB", type: "fiat" },
  { code: "BTC", name: "Bitcoin", symbol: "BTC", flag: "", type: "crypto" },
  { code: "ETH", name: "Ethereum", symbol: "ETH", flag: "", type: "crypto" },
]

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
  const { request, user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [displayCurrency, setDisplayCurrency] = useState("USD");
  const [transactionCurrency, setTransactionCurrency] = useState("USD");
  const [savedPrefs, setSavedPrefs] = useState({ displayCurrency: "USD", transactionCurrency: "USD" });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState("");

  useEffect(() => {
    let mounted = true;

    async function loadPreferences() {
      try {
        let parsed = null;

        if (user) {
          try {
            const payload = await request("/api/preferences/currency", { method: "GET" });
            parsed = payload.data;
          } catch {
            parsed = null;
          }
        }

        if (!parsed) {
          const raw = localStorage.getItem(storageKey);
          parsed = raw ? JSON.parse(raw) : null;
        }

        if (!mounted) return;

        const nextDisplay = parsed?.displayCurrency || "USD";
        const nextTransaction = parsed?.transactionCurrency || nextDisplay;
        setDisplayCurrency(nextDisplay);
        setTransactionCurrency(nextTransaction);
        setSavedPrefs({ displayCurrency: nextDisplay, transactionCurrency: nextTransaction });
      } catch (error) {
        console.error("Unable to load currency preferences", error);
      } finally {
        if (mounted) setLoading(false);
      }
    }

    loadPreferences();

    return () => {
      mounted = false;
    };
  }, [request, user]);

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
      const payload = { displayCurrency, transactionCurrency };

      if (user) {
        await request("/api/preferences/currency", {
          method: "PATCH",
          body: JSON.stringify(payload),
        });
      }

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
    <main className="relative h-[100dvh] overflow-hidden bg-[var(--exa-bg-primary)] text-white">
      <header
        className="fixed inset-x-0 top-0 z-40 border-b border-[var(--exa-border-active)] bg-[var(--exa-surface)] backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-3xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-2 text-[var(--exa-text-primary)] hover:border-[var(--exa-border-active)]"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[var(--exa-text-primary)] sm:text-xl">Currency Preference</h1>
              <p className="text-xs text-[var(--exa-text-secondary)] sm:text-sm">
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
            <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <div className="mb-3 flex items-center gap-2">
                <Coins className="h-4 w-4 text-[var(--exa-gold-light)]" />
                <h2 className="text-base font-semibold text-[var(--exa-text-primary)]">Default Currency</h2>
              </div>
              <label className="mb-3 block">
                <div className="flex items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2.5">
                  <Search className="h-4 w-4 text-[var(--exa-gold-light)]" />
                  <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search currency..."
                    className="w-full bg-transparent text-sm text-white placeholder:text-[var(--exa-text-muted)] outline-none"
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
                        ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]"
                        : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)]"
                    }`}
                  >
                    <div>
                      <p className="text-sm">
                        {item.code} - {item.name}
                      </p>
                      <p className="text-xs text-[var(--exa-text-muted)]">
                        {item.symbol} - {item.type.toUpperCase()} {item.flag ? `- ${flagEmoji(item.flag)}` : ""}
                      </p>
                    </div>
                    {displayCurrency === item.code ? <Check className="h-4 w-4" /> : null}
                  </button>
                ))}
              </div>
            </article>

            <article className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <div className="mb-3 flex items-center gap-2">
                <Wallet className="h-4 w-4 text-[var(--exa-gold-light)]" />
                <h2 className="text-base font-semibold text-[var(--exa-text-primary)]">Transaction Currency (Optional)</h2>
              </div>
              <div className="grid gap-2 sm:grid-cols-2">
                {currencyOptions.map((item) => (
                  <button
                    key={`tx-${item.code}`}
                    type="button"
                    onClick={() => setTransactionCurrency(item.code)}
                    className={`rounded-xl border px-3 py-2 text-left text-sm transition ${
                      transactionCurrency === item.code
                        ? "border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-surface)] to-transparent text-[var(--exa-gold-light)]"
                        : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)]"
                    }`}
                  >
                    {item.code} - {item.name}
                  </button>
                ))}
              </div>
            </article>

            <article className="mt-4 rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-4 shadow-[var(--exa-shadow-soft)]">
              <p className="text-xs uppercase tracking-[0.1em] text-[var(--exa-gold-light)]">Real-time Conversion Preview</p>
              <p className="mt-1 text-sm text-[var(--exa-text-primary)]">
                Total Balance: {convertedBalance}
              </p>
              <p className="mt-1 text-xs text-[var(--exa-text-secondary)]">
                Display: {displayMeta.code} -ons: {txMeta.code}
              </p>
            </article>
          </>
        )}
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-3 backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="mx-auto w-full max-w-3xl">
          <button
            type="button"
            disabled={!hasChanges || saving || loading}
            onClick={saveChanges}
            className="w-full rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] disabled:cursor-not-allowed disabled:opacity-45"
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
      <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
        <div className="mb-3 h-5 w-40 animate-pulse rounded bg-gradient-to-r from-[var(--exa-gold-surface)] to-transparent" />
        <div className="space-y-2">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-11 animate-pulse rounded-xl bg-gradient-to-r from-[#1C263A] via-[#243146] to-[#1C263A]" />
          ))}
        </div>
      </article>
      <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
        <div className="mb-3 h-5 w-44 animate-pulse rounded bg-gradient-to-r from-[var(--exa-gold-surface)] to-transparent" />
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
  if (code === "EU") return "EU";
  return code
    .toUpperCase()
    .split("")
    .map((char) => String.fromCodePoint(127397 + char.charCodeAt()))
    .join("");
}

export default CurrencyPreferencePage;

