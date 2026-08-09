import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRightLeft,
  BadgeDollarSign,
  CreditCard,
  Gamepad2,
  Gift,
  Globe,
  Lock,
  ShieldCheck,
  ShoppingBag,
  Smartphone,
} from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { submitGiftcardSell } from "../services/giftcardApi";
import { formatLockTime, formatNaira, useGiftcardLiveRate } from "../hooks/useGiftcardLiveRate";
import InputField from "./InputField";
import ProviderBadge from "./ProviderBadge";
import SummaryPanel from "./SummaryPanel";
import "./Giftcard.css";

const providerOptions = ["Amazon", "Steam", "iTunes", "Google Play"];
const giftcardCurrencies = ["NGN", "ZAR", "GHS", "KES", "USD", "EUR", "GBP", "CAD", "AUD", "USDT", "USDC"];

const supportedProviders = [
  { name: "Amazon", icon: <ShoppingBag className="h-3.5 w-3.5" aria-hidden="true" /> },
  { name: "Steam", icon: <Gamepad2 className="h-3.5 w-3.5" aria-hidden="true" /> },
  { name: "iTunes", icon: <Smartphone className="h-3.5 w-3.5" aria-hidden="true" /> },
  { name: "Google Play", icon: <Globe className="h-3.5 w-3.5" aria-hidden="true" /> },
];

function Giftcard({ onBack }) {
  const { apiBaseUrl, token } = useAuth();
  const [code, setCode] = useState("");
  const [provider, setProvider] = useState(providerOptions[0]);
  const [amount, setAmount] = useState("100");
  const [currency, setCurrency] = useState("USD");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [touched, setTouched] = useState({ code: false, amount: false });
  const [statusMessage, setStatusMessage] = useState("");
  const [errorMessage, setErrorMessage] = useState("");
  const [confirmationLock, setConfirmationLock] = useState(null);

  const parsedAmount = Number(amount);
  const amountValue = Number.isFinite(parsedAmount) && parsedAmount > 0 ? parsedAmount : 0;
  const {
    rate,
    loading: loadingRate,
    error: rateError,
    lockRate,
    rateLock,
    secondsRemaining,
    isLocked,
  } = useGiftcardLiveRate({
    apiBaseUrl,
    token,
    brand: provider,
    value: amountValue,
    transactionType: "sell",
  });
  const fee = 0;
  const receivable = rate?.payout ?? 0;

  const errors = useMemo(
    () => ({
      code: code.trim().length < 8 ? "Enter a valid giftcard code (min 8 characters)." : "",
      amount: amountValue <= 0 ? "Amount must be greater than zero." : "",
    }),
    [code, amountValue]
  );

  const hasError = Boolean(errors.code || errors.amount);

  const handleOpenConfirmation = async () => {
    setTouched({ code: true, amount: true });
    if (hasError || isSubmitting) {
      return;
    }

    try {
      setErrorMessage("");
      const lock = await lockRate();
      setConfirmationLock(lock);
    } catch (error) {
      setErrorMessage(error.message || "Unable to lock this rate. Please refresh pricing.");
    }
  };

  const handleSubmit = async () => {
    if (!confirmationLock || !isLocked) {
      setErrorMessage("Rate expired. Please refresh pricing before submission.");
      setConfirmationLock(null);
      return;
    }

    setIsSubmitting(true);
    try {
      setStatusMessage("");
      setErrorMessage("");
      const payload = await submitGiftcardSell({
        apiBaseUrl,
        token,
        payload: {
          card_type: provider,
          provider: provider === "Google Play" ? "google_play" : provider.toLowerCase(),
          amount: amountValue,
          currency,
          card_code: code.trim(),
          source_mode: "manual_upload",
          payment_method: "wallet_credit",
          rate_lock_id: confirmationLock.lock_id,
          locked_buy_rate: confirmationLock.rates?.buy_rate,
          device_id: window.navigator.userAgent,
          geo_location: Intl.DateTimeFormat().resolvedOptions().locale || "unknown",
          is_vpn: false,
        },
      });

      setStatusMessage(payload?.message || "Giftcard submitted for fraud analysis.");
      setCode("");
      setConfirmationLock(null);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleSubmitSafe = async () => {
    try {
      await handleSubmit();
    } catch (error) {
      setErrorMessage(error.message || "Unable to submit giftcard.");
    }
  };

  return (
    <main className="gift-bg min-h-screen px-4 py-10 sm:px-6 sm:py-12">
      <section className="gift-shell mx-auto w-full max-w-6xl rounded-[2rem] p-5 sm:p-8 lg:p-10">
        <header className="gift-card rounded-3xl p-6 sm:p-8">
          {onBack ? (
            <div className="mb-4 flex justify-start">
              <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Back
              </button>
            </div>
          ) : null}
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="max-w-3xl">
              <h1 className="font-['Sora'] text-4xl font-semibold tracking-tight text-[var(--exa-text-primary)] sm:text-5xl">Giftcard Conversion</h1>
              <p className="mt-3 text-sm leading-relaxed text-[var(--exa-text-secondary)] sm:text-base">
                Redeem or convert your supported giftcards securely within the ExaEarn ecosystem.
              </p>
            </div>
            <div className="inline-flex h-14 w-14 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] text-[var(--exa-gold-light)] shadow-[var(--exa-shadow-gold)]">
              <ArrowRightLeft className="h-7 w-7" aria-hidden="true" />
            </div>
          </div>
        </header>

        <section className="mt-6 grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
          <article className="gift-card rounded-2xl p-5 sm:p-6">
            <h2 className="font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)]">Giftcard Input Panel</h2>
            <div className="mt-5 grid gap-4">
              <InputField
                id="giftCode"
                label="Giftcard Code / Number"
                value={code}
                onChange={(event) => setCode(event.target.value)}
                onBlur={() => setTouched((prev) => ({ ...prev, code: true }))}
                placeholder="Enter card code"
                error={touched.code ? errors.code : ""}
              >
                <CreditCard className="h-4 w-4 text-[var(--exa-text-muted)]" aria-hidden="true" />
              </InputField>

              <div>
                <label htmlFor="giftProvider" className="mb-2 block text-sm font-medium tracking-wide text-[var(--exa-text-secondary)]">
                  Giftcard Type / Provider
                </label>
                <div className="gift-input-wrap rounded-xl px-4 py-3">
                  <select
                    id="giftProvider"
                    value={provider}
                    onChange={(event) => setProvider(event.target.value)}
                    className="w-full bg-transparent text-base text-[var(--exa-text-primary)] outline-none"
                  >
                    {providerOptions.map((option) => (
                      <option key={option} value={option} className="bg-[var(--exa-surface)] text-[var(--exa-text-primary)]">
                        {option}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <InputField
                  id="giftAmount"
                  label="Amount / Value"
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                  onBlur={() => setTouched((prev) => ({ ...prev, amount: true }))}
                  placeholder="100"
                  type="number"
                  min="0"
                  step="0.01"
                  error={touched.amount ? errors.amount : ""}
                >
                  <BadgeDollarSign className="h-4 w-4 text-[var(--exa-text-muted)]" aria-hidden="true" />
                </InputField>

                <div>
                  <label htmlFor="giftCurrency" className="mb-2 block text-sm font-medium tracking-wide text-[var(--exa-text-secondary)]">
                    Currency
                  </label>
                  <div className="gift-input-wrap rounded-xl px-4 py-3">
                    <select
                      id="giftCurrency"
                      value={currency}
                      onChange={(event) => setCurrency(event.target.value)}
                      className="w-full bg-transparent text-base text-[var(--exa-text-primary)] outline-none"
                    >
                      {giftcardCurrencies.map((option) => (
                        <option key={option} value={option} className="bg-[var(--exa-surface)]">
                          {option}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div className="mt-6 rounded-xl border border-emerald-300/20 bg-emerald-500/5 p-4">
              <p className="flex items-start gap-2 text-sm text-emerald-100/85">
                <ShieldCheck className="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-300" aria-hidden="true" />
                Live pricing is refreshed automatically and locked before submission.
              </p>
              <p className="mt-2 flex items-start gap-2 text-sm text-emerald-100/85">
                <Lock className="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-300" aria-hidden="true" />
                Giftcard details are encrypted before submission and never exposed in raw form.
              </p>
            </div>

            <button
              type="button"
              onClick={handleOpenConfirmation}
              disabled={hasError || isSubmitting}
              className="mt-6 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-3 text-lg font-semibold text-[var(--exa-gold-contrast)] transition-all duration-300 hover:scale-[1.01] hover:shadow-[var(--exa-shadow-gold)] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none"
            >
              <Gift className="h-5 w-5" aria-hidden="true" />
              {isSubmitting ? "Processing..." : loadingRate ? "Fetching best rate..." : "Convert / Redeem Giftcard"}
            </button>
            {rateLock && isLocked ? (
              <p className="mt-3 text-sm text-[var(--exa-gold-light)]">Rate locked for {formatLockTime(secondsRemaining)}</p>
            ) : null}
            {rateError ? <p className="mt-3 text-sm text-rose-300">{rateError}</p> : null}
            {statusMessage ? <p className="mt-3 text-sm text-emerald-300">{statusMessage}</p> : null}
            {errorMessage ? <p className="mt-3 text-sm text-rose-300">{errorMessage}</p> : null}
          </article>

          <SummaryPanel
            amount={amountValue}
            fee={fee}
            receivable={receivable}
            rateText={rate ? `${formatNaira(rate.buy_rate)}/$ (live)` : "-"}
            currency={currency}
            demandLevel={rate?.demand_level}
            inventoryStatus={rate?.inventory_status}
            marketFeedback={rate?.market_feedback}
            loading={loadingRate}
          />
        </section>

        <section className="mt-6 gift-card rounded-2xl p-5 sm:p-6">
          <h2 className="font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)]">Supported Giftcards</h2>
          <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">Providers currently available for secure conversion.</p>
          <div className="mt-4 flex flex-wrap gap-3">
            {supportedProviders.map((providerItem) => (
              <ProviderBadge key={providerItem.name} icon={providerItem.icon} name={providerItem.name} />
            ))}
          </div>
        </section>
        {confirmationLock ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
            <div className="gift-card w-full max-w-md rounded-2xl p-6">
              <h2 className="font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)]">Confirm Locked Rate</h2>
              <div className="mt-4 space-y-3 text-sm text-[var(--exa-text-secondary)]">
                <p>Brand: {confirmationLock.brand_label}</p>
                <p>Value: ${Number(confirmationLock.card_value).toFixed(2)}</p>
                <p>Rate Used: {formatNaira(confirmationLock.rates?.buy_rate)}/$</p>
                <p>You Receive: {formatNaira(confirmationLock.rates?.payout)}</p>
                <p className="text-[var(--exa-gold-light)]">Rate locked for {formatLockTime(secondsRemaining)}</p>
              </div>
              <div className="mt-6 grid gap-3 sm:grid-cols-2">
                <button type="button" className="btn-outline rounded-xl px-4 py-3" onClick={() => setConfirmationLock(null)}>
                  Cancel
                </button>
                <button
                  type="button"
                  className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold)] px-4 py-3 font-semibold text-[var(--exa-gold-contrast)] disabled:opacity-60"
                  disabled={!isLocked || isSubmitting}
                  onClick={handleSubmitSafe}
                >
                  {isSubmitting ? "Submitting..." : "Confirm"}
                </button>
              </div>
            </div>
          </div>
        ) : null}
      </section>
    </main>
  );
}

export default Giftcard;
