import { useMemo, useState } from "react";
import { ArrowLeft, Lock, ShieldCheck, ShoppingBag, Gamepad2, Smartphone, Globe, Gift } from "lucide-react";
import GiftcardSelector from "./GiftcardSelector";
import PaymentPanel from "./PaymentPanel";
import SummaryCard from "./SummaryCard";
import "./BuyGiftcard.css";

const providerOptions = ["Amazon", "Steam", "iTunes", "Google Play"];
const categoryOptions = ["Shopping", "Gaming", "Entertainment", "Lifestyle"];
const denominationOptions = ["$25", "$50", "$100", "$200"];

const providerIcons = {
  Amazon: <ShoppingBag className="h-3.5 w-3.5" aria-hidden="true" />,
  Steam: <Gamepad2 className="h-3.5 w-3.5" aria-hidden="true" />,
  iTunes: <Smartphone className="h-3.5 w-3.5" aria-hidden="true" />,
  "Google Play": <Globe className="h-3.5 w-3.5" aria-hidden="true" />,
};

const paymentLabelMap = {
  balance: "Platform Balance",
  crypto: "Crypto Wallet",
  token: "ExaToken",
};

function BuyGiftcard({ onBack }) {
  const [provider, setProvider] = useState(providerOptions[0]);
  const [category, setCategory] = useState(categoryOptions[0]);
  const [country, setCountry] = useState("United States");
  const [denomination, setDenomination] = useState(denominationOptions[2]);
  const [paymentMethod, setPaymentMethod] = useState("balance");
  const [isBuying, setIsBuying] = useState(false);

  const amount = Number(denomination.replace(/[^\d.]/g, "")) || 0;
  const fee = amount * 0.015;
  const total = amount + fee;

  const isDisabled = useMemo(() => !provider || !category || !country || !denomination || !paymentMethod || isBuying, [provider, category, country, denomination, paymentMethod, isBuying]);

  const handleBuy = async () => {
    if (isDisabled) {
      return;
    }

    setIsBuying(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 1400));
    } finally {
      setIsBuying(false);
    }
  };

  return (
    <main className="buy-bg min-h-screen px-4 py-10 sm:px-6 sm:py-12">
      <section className="buy-shell mx-auto w-full max-w-6xl rounded-[2rem] p-5 sm:p-8 lg:p-10">
        <header className="buy-card rounded-3xl p-6 sm:p-8">
          {onBack ? (
            <div className="mb-4 flex justify-start">
              <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Back
              </button>
            </div>
          ) : null}
          <h1 className="font-['Sora'] text-4xl font-semibold tracking-tight text-violet-50 sm:text-5xl">Buy Giftcards</h1>
          <p className="mt-3 text-sm leading-relaxed text-violet-100/75 sm:text-base">
            Purchase digital giftcards securely within the ExaEarn ecosystem.
          </p>
        </header>

        <section className="mt-6 grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
          <div className="grid gap-4">
            <GiftcardSelector
              providers={providerOptions}
              selectedProvider={provider}
              onProviderChange={(event) => setProvider(event.target.value)}
              categories={categoryOptions}
              selectedCategory={category}
              onCategoryChange={(event) => setCategory(event.target.value)}
              selectedCountry={country}
              onCountryChange={(event) => setCountry(event.target.value)}
              selectedDenomination={denomination}
              onDenominationChange={(event) => setDenomination(event.target.value)}
              denominations={denominationOptions}
            />

            <PaymentPanel selectedMethod={paymentMethod} onMethodChange={setPaymentMethod} />

            <div className="buy-card rounded-2xl p-5 sm:p-6">
              <button
                type="button"
                onClick={handleBuy}
                disabled={isDisabled}
                className="w-full rounded-xl border border-auric-300/80 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-3 text-lg font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none"
              >
                {isBuying ? "Processing Purchase..." : "Buy Giftcard"}
              </button>
              <div className="mt-4 space-y-2 rounded-xl border border-emerald-300/20 bg-emerald-500/5 p-4">
                <p className="flex items-center gap-2 text-sm text-emerald-100/85">
                  <ShieldCheck className="h-4 w-4 text-emerald-300" aria-hidden="true" />
                  Secure digital delivery after successful payment.
                </p>
                <p className="flex items-center gap-2 text-sm text-emerald-100/85">
                  <Gift className="h-4 w-4 text-emerald-300" aria-hidden="true" />
                  Instant processing for supported denominations.
                </p>
                <p className="flex items-center gap-2 text-sm text-emerald-100/85">
                  <Lock className="h-4 w-4 text-emerald-300" aria-hidden="true" />
                  All transactions are encrypted end-to-end.
                </p>
              </div>
            </div>
          </div>

          <SummaryCard
            provider={`${provider} (${category})`}
            denomination={denomination}
            fee={fee}
            total={total}
            paymentLabel={paymentLabelMap[paymentMethod]}
          />
        </section>

        <section className="mt-6 buy-card rounded-2xl p-5 sm:p-6">
          <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Supported Giftcards</h2>
          <p className="mt-2 text-sm text-violet-100/70">Trusted partners available for instant checkout.</p>
          <div className="mt-4 flex flex-wrap gap-3">
            {providerOptions.map((item) => (
              <span key={item} className="buy-provider inline-flex items-center gap-2 rounded-full border border-violet-300/20 bg-cosmic-900/60 px-3 py-2 text-sm text-violet-100/85">
                <span className="inline-flex h-6 w-6 items-center justify-center rounded-full border border-auric-300/45 bg-cosmic-800/80 text-auric-300">
                  {providerIcons[item]}
                </span>
                {item}
              </span>
            ))}
          </div>
        </section>
      </section>
    </main>
  );
}

export default BuyGiftcard;
