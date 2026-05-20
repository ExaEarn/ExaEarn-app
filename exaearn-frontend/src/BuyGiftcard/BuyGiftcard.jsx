import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Lock, ShieldCheck, ShoppingBag, Gamepad2, Smartphone, Globe, Gift } from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { fetchGiftcardInventory, fetchGiftcardOrders, submitGiftcardBuy } from "../services/giftcardApi";
import { formatLockTime, formatNaira, useGiftcardLiveRate } from "../hooks/useGiftcardLiveRate";
import GiftcardSelector from "./GiftcardSelector";
import PaymentPanel from "./PaymentPanel";
import SummaryCard from "./SummaryCard";
import "./BuyGiftcard.css";

const categoryOptions = ["Shopping", "Gaming", "Entertainment", "Lifestyle"];

const providerIcons = {
  Amazon: <ShoppingBag className="h-3.5 w-3.5" aria-hidden="true" />,
  Steam: <Gamepad2 className="h-3.5 w-3.5" aria-hidden="true" />,
  iTunes: <Smartphone className="h-3.5 w-3.5" aria-hidden="true" />,
  Apple: <Smartphone className="h-3.5 w-3.5" aria-hidden="true" />,
  "Google Play": <Globe className="h-3.5 w-3.5" aria-hidden="true" />,
};

const paymentLabelMap = {
  balance: "Platform Balance",
  crypto: "Crypto Wallet",
  token: "ExaToken",
};

function BuyGiftcard({ onBack }) {
  const { apiBaseUrl, token } = useAuth();
  const [inventory, setInventory] = useState([]);
  const [provider, setProvider] = useState("");
  const [category, setCategory] = useState(categoryOptions[0]);
  const [country, setCountry] = useState("United States");
  const [denomination, setDenomination] = useState("");
  const [paymentMethod, setPaymentMethod] = useState("balance");
  const [isBuying, setIsBuying] = useState(false);
  const [loadingInventory, setLoadingInventory] = useState(true);
  const [statusMessage, setStatusMessage] = useState("");
  const [errorMessage, setErrorMessage] = useState("");
  const [latestOrder, setLatestOrder] = useState(null);
  const [confirmationLock, setConfirmationLock] = useState(null);

  useEffect(() => {
    let active = true;

    const loadInventory = async () => {
      try {
        setLoadingInventory(true);
        setErrorMessage("");
        const payload = await fetchGiftcardInventory({
          apiBaseUrl,
          token,
          params: { per_page: 50 },
        });

        if (!active) {
          return;
        }

        const items = Array.isArray(payload?.data?.data) ? payload.data.data : [];
        setInventory(items);
        if (items.length > 0) {
          setProvider((current) => current || items[0].card_type);
        }
      } catch (error) {
        if (active) {
          setErrorMessage(error.message || "Unable to load giftcard inventory.");
        }
      } finally {
        if (active) {
          setLoadingInventory(false);
        }
      }
    };

    loadInventory();

    return () => {
      active = false;
    };
  }, [apiBaseUrl, token]);

  const providerOptions = useMemo(() => {
    const unique = [...new Set(inventory.map((item) => item.card_type).filter(Boolean))];
    return unique.length ? unique : ["Amazon", "Steam", "Apple", "Google Play"];
  }, [inventory]);

  const denominationOptions = useMemo(() => {
    const matching = inventory
      .filter((item) => item.card_type === provider)
      .map((item) => `$${Number(item.amount || 0).toFixed(0)}`);
    const unique = [...new Set(matching)];
    return unique.length ? unique : ["$25", "$50", "$100", "$200"];
  }, [inventory, provider]);

  useEffect(() => {
    if (!providerOptions.includes(provider)) {
      setProvider(providerOptions[0] || "");
    }
  }, [provider, providerOptions]);

  useEffect(() => {
    if (!denominationOptions.includes(denomination)) {
      setDenomination(denominationOptions[0] || "");
    }
  }, [denomination, denominationOptions]);

  const amount = Number(denomination.replace(/[^\d.]/g, "")) || 0;
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
    value: amount,
    transactionType: "buy",
  });
  const fee = 0;
  const total = rate?.price ?? 0;
  const selectedInventoryItem = useMemo(
    () =>
      inventory.find(
        (item) => item.card_type === provider && Number(item.amount || 0) === amount
      ) || null,
    [amount, inventory, provider]
  );

  const isDisabled = useMemo(
    () =>
      !provider ||
      !category ||
      !country ||
      !denomination ||
      !paymentMethod ||
      isBuying ||
      loadingInventory ||
      !selectedInventoryItem,
    [provider, category, country, denomination, paymentMethod, isBuying, loadingInventory, selectedInventoryItem]
  );

  const handleOpenConfirmation = async () => {
    if (isDisabled) {
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

  const handleBuy = async () => {
    if (!confirmationLock || !isLocked) {
      setErrorMessage("Rate expired. Please refresh pricing before submission.");
      setConfirmationLock(null);
      return;
    }

    setIsBuying(true);
    try {
      setStatusMessage("");
      setErrorMessage("");
      const payload = await submitGiftcardBuy({
        apiBaseUrl,
        token,
        payload: {
          giftcard_id: selectedInventoryItem.id,
          payment_method: paymentMethod,
          rate_lock_id: confirmationLock.lock_id,
          locked_sell_rate: confirmationLock.rates?.sell_rate,
          device_id: window.navigator.userAgent,
          geo_location: Intl.DateTimeFormat().resolvedOptions().locale || "unknown",
          is_vpn: false,
        },
      });

      setStatusMessage(payload?.message || "Giftcard purchase submitted for secure processing.");

      const ordersPayload = await fetchGiftcardOrders({
        apiBaseUrl,
        token,
        perPage: 1,
      });
      const latest = Array.isArray(ordersPayload?.data?.data) ? ordersPayload.data.data[0] : null;
      setLatestOrder(latest || null);
      setConfirmationLock(null);
    } finally {
      setIsBuying(false);
    }
  };

  const handleBuySafe = async () => {
    try {
      await handleBuy();
    } catch (error) {
      setErrorMessage(error.message || "Unable to complete giftcard purchase.");
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
                onClick={handleOpenConfirmation}
                disabled={isDisabled}
                className="w-full rounded-xl border border-auric-300/80 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-3 text-lg font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none"
              >
                {isBuying ? "Processing Purchase..." : loadingRate ? "Updating price..." : "Buy Giftcard"}
              </button>
              {rateLock && isLocked ? (
                <p className="mt-3 text-sm text-auric-300">Rate locked for {formatLockTime(secondsRemaining)}</p>
              ) : null}
              {loadingInventory ? <p className="mt-3 text-sm text-violet-100/70">Loading live giftcard inventory...</p> : null}
              {rateError ? <p className="mt-3 text-sm text-rose-300">{rateError}</p> : null}
              {statusMessage ? <p className="mt-3 text-sm text-emerald-300">{statusMessage}</p> : null}
              {errorMessage ? <p className="mt-3 text-sm text-rose-300">{errorMessage}</p> : null}
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
                  Giftcard delivery remains masked until secure fulfillment is complete.
                </p>
              </div>
              {latestOrder ? (
                <div className="mt-4 rounded-xl border border-violet-300/20 bg-cosmic-900/55 p-4 text-sm text-violet-100/80">
                  <p className="font-semibold text-violet-50">Latest Order Status</p>
                  <p className="mt-2">Reference: {latestOrder.reference}</p>
                  <p className="mt-1">Status: {latestOrder.status}</p>
                  <p className="mt-1">Risk Level: {latestOrder.risk_level}</p>
                  {latestOrder?.metadata?.delivery?.masked_code ? (
                    <p className="mt-1">Delivery: {latestOrder.metadata.delivery.masked_code}</p>
                  ) : null}
                </div>
              ) : null}
            </div>
          </div>

          <SummaryCard
            provider={`${provider} (${category})`}
            denomination={denomination}
            fee={fee}
            total={total}
            paymentLabel={paymentLabelMap[paymentMethod]}
            availability={selectedInventoryItem ? rate?.inventory_status || "Available" : "Out of Stock"}
            sellRate={rate?.sell_rate}
            demandLevel={rate?.demand_level}
            marketFeedback={rate?.market_feedback}
            loading={loadingRate}
          />
        </section>

        <section className="mt-6 buy-card rounded-2xl p-5 sm:p-6">
          <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Supported Giftcards</h2>
          <p className="mt-2 text-sm text-violet-100/70">Trusted partners available from current ExaEarn inventory.</p>
          <div className="mt-4 flex flex-wrap gap-3">
            {providerOptions.map((item) => (
              <span key={item} className="buy-provider inline-flex items-center gap-2 rounded-full border border-violet-300/20 bg-cosmic-900/60 px-3 py-2 text-sm text-violet-100/85">
                <span className="inline-flex h-6 w-6 items-center justify-center rounded-full border border-auric-300/45 bg-cosmic-800/80 text-auric-300">
                  {providerIcons[item] || <Gift className="h-3.5 w-3.5" aria-hidden="true" />}
                </span>
                {item}
              </span>
            ))}
          </div>
        </section>
        {confirmationLock ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
            <div className="buy-card w-full max-w-md rounded-2xl p-6">
              <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Confirm Locked Price</h2>
              <div className="mt-4 space-y-3 text-sm text-violet-100/85">
                <p>Brand: {confirmationLock.brand_label}</p>
                <p>Value: ${Number(confirmationLock.card_value).toFixed(2)}</p>
                <p>Rate Used: {formatNaira(confirmationLock.rates?.sell_rate)}/$</p>
                <p>Price: {formatNaira(confirmationLock.rates?.price)}</p>
                <p className="text-auric-300">Rate locked for {formatLockTime(secondsRemaining)}</p>
              </div>
              <div className="mt-6 grid gap-3 sm:grid-cols-2">
                <button type="button" className="btn-outline rounded-xl px-4 py-3" onClick={() => setConfirmationLock(null)}>
                  Cancel
                </button>
                <button
                  type="button"
                  className="rounded-xl border border-auric-300/80 bg-auric-400 px-4 py-3 font-semibold text-cosmic-900 disabled:opacity-60"
                  disabled={!isLocked || isBuying}
                  onClick={handleBuySafe}
                >
                  {isBuying ? "Submitting..." : "Confirm"}
                </button>
              </div>
            </div>
          </div>
        ) : null}
      </section>
    </main>
  );
}

export default BuyGiftcard;
