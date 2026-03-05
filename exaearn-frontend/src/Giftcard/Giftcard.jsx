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
import InputField from "./InputField";
import ProviderBadge from "./ProviderBadge";
import SummaryPanel from "./SummaryPanel";
import "./Giftcard.css";

const providerOptions = ["Amazon", "Steam", "iTunes", "Google Play"];

const providerRates = {
  Amazon: 0.95,
  Steam: 0.92,
  iTunes: 0.9,
  "Google Play": 0.91,
};

const supportedProviders = [
  { name: "Amazon", icon: <ShoppingBag className="h-3.5 w-3.5" aria-hidden="true" /> },
  { name: "Steam", icon: <Gamepad2 className="h-3.5 w-3.5" aria-hidden="true" /> },
  { name: "iTunes", icon: <Smartphone className="h-3.5 w-3.5" aria-hidden="true" /> },
  { name: "Google Play", icon: <Globe className="h-3.5 w-3.5" aria-hidden="true" /> },
];

function Giftcard({ onBack }) {
  const [code, setCode] = useState("");
  const [provider, setProvider] = useState(providerOptions[0]);
  const [amount, setAmount] = useState("100");
  const [currency, setCurrency] = useState("USD");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [touched, setTouched] = useState({ code: false, amount: false });

  const parsedAmount = Number(amount);
  const amountValue = Number.isFinite(parsedAmount) && parsedAmount > 0 ? parsedAmount : 0;
  const rate = providerRates[provider] ?? 0.9;
  const fee = amountValue * 0.02;
  const receivable = Math.max(amountValue * rate - fee, 0);

  const errors = useMemo(
    () => ({
      code: code.trim().length < 8 ? "Enter a valid giftcard code (min 8 characters)." : "",
      amount: amountValue <= 0 ? "Amount must be greater than zero." : "",
    }),
    [code, amountValue]
  );

  const hasError = Boolean(errors.code || errors.amount);

  const handleSubmit = async () => {
    setTouched({ code: true, amount: true });
    if (hasError || isSubmitting) {
      return;
    }

    setIsSubmitting(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 1500));
    } finally {
      setIsSubmitting(false);
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
              <h1 className="font-['Sora'] text-4xl font-semibold tracking-tight text-violet-50 sm:text-5xl">Giftcard Conversion</h1>
              <p className="mt-3 text-sm leading-relaxed text-violet-100/75 sm:text-base">
                Redeem or convert your supported giftcards securely within the ExaEarn ecosystem.
              </p>
            </div>
            <div className="inline-flex h-14 w-14 items-center justify-center rounded-2xl border border-auric-400/60 bg-cosmic-900/70 text-auric-300 shadow-button-glow">
              <ArrowRightLeft className="h-7 w-7" aria-hidden="true" />
            </div>
          </div>
        </header>

        <section className="mt-6 grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
          <article className="gift-card rounded-2xl p-5 sm:p-6">
            <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Giftcard Input Panel</h2>
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
                <CreditCard className="h-4 w-4 text-violet-200/70" aria-hidden="true" />
              </InputField>

              <div>
                <label htmlFor="giftProvider" className="mb-2 block text-sm font-medium tracking-wide text-violet-100/85">
                  Giftcard Type / Provider
                </label>
                <div className="gift-input-wrap rounded-xl px-4 py-3">
                  <select
                    id="giftProvider"
                    value={provider}
                    onChange={(event) => setProvider(event.target.value)}
                    className="w-full bg-transparent text-base text-violet-50 outline-none"
                  >
                    {providerOptions.map((option) => (
                      <option key={option} value={option} className="bg-cosmic-900 text-violet-50">
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
                  <BadgeDollarSign className="h-4 w-4 text-violet-200/70" aria-hidden="true" />
                </InputField>

                <div>
                  <label htmlFor="giftCurrency" className="mb-2 block text-sm font-medium tracking-wide text-violet-100/85">
                    Currency
                  </label>
                  <div className="gift-input-wrap rounded-xl px-4 py-3">
                    <select
                      id="giftCurrency"
                      value={currency}
                      onChange={(event) => setCurrency(event.target.value)}
                      className="w-full bg-transparent text-base text-violet-50 outline-none"
                    >
                      <option value="USD" className="bg-cosmic-900">USD</option>
                      <option value="EUR" className="bg-cosmic-900">EUR</option>
                      <option value="GBP" className="bg-cosmic-900">GBP</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div className="mt-6 rounded-xl border border-emerald-300/20 bg-emerald-500/5 p-4">
              <p className="flex items-start gap-2 text-sm text-emerald-100/85">
                <ShieldCheck className="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-300" aria-hidden="true" />
                Transactions are securely processed end-to-end with encrypted transfer channels.
              </p>
              <p className="mt-2 flex items-start gap-2 text-sm text-emerald-100/85">
                <Lock className="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-300" aria-hidden="true" />
                Giftcard details are used only for conversion and are never stored after completion.
              </p>
            </div>

            <button
              type="button"
              onClick={handleSubmit}
              disabled={hasError || isSubmitting}
              className="mt-6 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-auric-300/80 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-3 text-lg font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none"
            >
              <Gift className="h-5 w-5" aria-hidden="true" />
              {isSubmitting ? "Processing..." : "Convert / Redeem Giftcard"}
            </button>
          </article>

          <SummaryPanel
            amount={amountValue}
            fee={fee}
            receivable={receivable}
            rateText={`1 ${currency} = ${rate.toFixed(2)} EXA`}
            currency={currency}
          />
        </section>

        <section className="mt-6 gift-card rounded-2xl p-5 sm:p-6">
          <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Supported Giftcards</h2>
          <p className="mt-2 text-sm text-violet-100/70">Providers currently available for secure conversion.</p>
          <div className="mt-4 flex flex-wrap gap-3">
            {supportedProviders.map((providerItem) => (
              <ProviderBadge key={providerItem.name} icon={providerItem.icon} name={providerItem.name} />
            ))}
          </div>
        </section>
      </section>
    </main>
  );
}

export default Giftcard;
