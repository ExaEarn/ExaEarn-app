import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  CalendarClock,
  Building2,
  MessageCircleMore,
  PlayCircle,
  ShieldCheck,
  Share2,
  Smartphone,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import { campaignData } from "./campaignData";
import "./SupportCampaignPage.css";

function formatNaira(amount) {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    maximumFractionDigits: 0,
  }).format(amount);
}

function formatUsd(amount) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    maximumFractionDigits: 2,
  }).format(amount);
}

function formatCrypto(amount) {
  return `${Number(amount).toFixed(4)} USDT`;
}

const paymentMethodsByCurrency = {
  NGN: ["Card Payment", "Local Transfer", "USSD / Mobile Money", "Connect Wallet"],
  USD: ["Card Payment", "Bank Wire", "Pay with Crypto", "Connect Wallet"],
  CRYPTO: ["Connect Wallet", "Pay with Crypto"],
};

function Counter({ value }) {
  const [count, setCount] = useState(0);

  useEffect(() => {
    let start;
    let raf;
    const duration = 1000;
    const animate = (time) => {
      if (!start) start = time;
      const progress = Math.min((time - start) / duration, 1);
      setCount(Math.floor(value * progress));
      if (progress < 1) raf = requestAnimationFrame(animate);
    };
    raf = requestAnimationFrame(animate);
    return () => cancelAnimationFrame(raf);
  }, [value]);

  return <span>{count.toLocaleString()}</span>;
}

function SupportCampaignPage({ onBack, campaignId }) {
  const campaign = useMemo(
    () => campaignData.find((item) => item.id === campaignId) || campaignData[0],
    [campaignId],
  );

  const [currency, setCurrency] = useState("NGN");
  const [amount, setAmount] = useState(campaign.rewardTiers[0]?.amount || 10000);
  const [selectedTierId, setSelectedTierId] = useState(campaign.rewardTiers[0]?.id);
  const [paymentMethod, setPaymentMethod] = useState(paymentMethodsByCurrency.NGN[0]);
  const [accountName, setAccountName] = useState("");
  const [walletAddressInput, setWalletAddressInput] = useState("");
  const [cardNumber, setCardNumber] = useState("");
  const [expiryDate, setExpiryDate] = useState("");
  const [cvv, setCvv] = useState("");
  const [network, setNetwork] = useState("ERC-20");
  const [comment, setComment] = useState("");
  const [comments, setComments] = useState([
    "Great initiative, this solves a real market gap.",
    "Will there be monthly progress updates?",
  ]);

  useEffect(() => {
    setSelectedTierId(campaign.rewardTiers[0]?.id);
    setAmount(campaign.rewardTiers[0]?.amount || 10000);
  }, [campaign]);

  useEffect(() => {
    setPaymentMethod(paymentMethodsByCurrency[currency][0]);
  }, [currency]);

  const selectedTier = campaign.rewardTiers.find((tier) => tier.id === selectedTierId) || campaign.rewardTiers[0];
  const progress = Math.min((campaign.raised / campaign.target) * 100, 100);
  const estimatedBenefit = selectedTier?.benefit || "Support updates";
  const paymentMethods = paymentMethodsByCurrency[currency];

  const conversion = useMemo(() => {
    const usdRate = 1600;
    const amountInNgn = currency === "NGN" ? amount : amount * usdRate;
    const amountInUsd = currency === "USD" ? amount : amount / usdRate;
    const amountInCrypto = currency === "CRYPTO" ? amount : amount / usdRate;
    const platformFee = amountInNgn * 0.015;
    const totalNgn = amountInNgn + platformFee;

    return { amountInNgn, amountInUsd, amountInCrypto, platformFee, totalNgn };
  }, [amount, currency]);
  const isCardPaymentValid =
    cardNumber.trim().length === 16 &&
    expiryDate.trim().length === 5 &&
    cvv.trim().length === 3 &&
    accountName.trim().length > 1;

  const submitComment = () => {
    const trimmed = comment.trim();
    if (!trimmed) return;
    setComments((current) => [trimmed, ...current]);
    setComment("");
  };

  return (
    <main className="min-h-screen exa-bg support-campaign-page text-[#F8F8F8]">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <span className="sc-particle sc-p1" />
          <span className="sc-particle sc-p2" />
          <span className="sc-particle sc-p3" />
          <div className="sc-grid" />
        </div>

        <section className="relative rounded-3xl border border-[#D4AF37]/30 bg-[#0B0B0B]/70 p-4 shadow-[0_24px_58px_rgba(0,0,0,0.5)] backdrop-blur-xl sm:p-6">
          <header className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/55 p-4 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/35 bg-[#0B0B0B]/65 px-3 py-2 text-xs font-semibold text-[#F8F8F8] transition hover:border-[#D4AF37] hover:text-[#D4AF37]"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <p className="text-xs uppercase tracking-[0.24em] text-[#D4AF37]/85">ExaEarn Support Campaign</p>
            </div>

            <div className="mt-4 overflow-hidden rounded-2xl border border-[#D4AF37]/35">
              <div className="relative">
                <img src={Image.crowdfund} alt={campaign.title} className="h-[250px] w-full object-cover opacity-45 sm:h-[320px]" />
                <div className="absolute inset-0 bg-[linear-gradient(130deg,rgba(11,11,11,0.45),rgba(11,11,11,0.92)),radial-gradient(circle_at_20%_15%,rgba(212,175,55,0.25),transparent_45%)]" />
                <div className="absolute inset-0 p-5 sm:p-7">
                  <p className="inline-flex rounded-full border border-[#D4AF37]/55 bg-[#D4AF37]/14 px-3 py-1 text-xs font-semibold text-[#D4AF37]">{campaign.category}</p>
                  <h1 className="mt-3 max-w-3xl font-['Sora'] text-2xl font-semibold sm:text-4xl">{campaign.title}</h1>
                  <p className="mt-2 text-sm text-[#F8F8F8]/86">{campaign.description}</p>
                  <p className="mt-3 inline-flex items-center gap-2 text-sm text-[#F8F8F8]/86">
                    {campaign.founder}
                    <span className="inline-flex items-center gap-1 rounded-full border border-[#D4AF37]/55 bg-[#D4AF37]/12 px-2 py-0.5 text-xs text-[#D4AF37]">
                      <BadgeCheck className="h-3.5 w-3.5" />
                      Verified
                    </span>
                  </p>
                  <div className="mt-4 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                      <p className="text-xs text-[#F8F8F8]/68">Funding Goal</p>
                      <p className="mt-1 font-semibold text-[#D4AF37]">{formatNaira(campaign.target)}</p>
                    </div>
                    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                      <p className="text-xs text-[#F8F8F8]/68">Amount Raised</p>
                      <p className="mt-1 font-semibold text-[#D4AF37]">{formatNaira(campaign.raised)}</p>
                    </div>
                    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                      <p className="text-xs text-[#F8F8F8]/68">Days Remaining</p>
                      <p className="mt-1 inline-flex items-center gap-1 font-semibold text-[#D4AF37]"><CalendarClock className="h-4 w-4" />{campaign.daysRemaining} days</p>
                    </div>
                  </div>
                  <div className="mt-3 h-2.5 rounded-full bg-[#F8F8F8]/12">
                    <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] via-[#f0d375] to-[#D4AF37]" style={{ width: `${progress}%` }} />
                  </div>
                  <div className="mt-4 flex flex-wrap gap-3">
                    <button className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_22px_rgba(212,175,55,0.4)]">
                      Support This Campaign
                    </button>
                    <button className="inline-flex items-center gap-2 rounded-xl border border-[#F8F8F8]/45 bg-[#F8F8F8]/8 px-4 py-2 text-sm font-semibold text-[#F8F8F8]">
                      <Share2 className="h-4 w-4 text-[#D4AF37]" />
                      Share Campaign
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </header>

          <section className="mt-6 grid gap-5 lg:grid-cols-[1.25fr_0.9fr]">
            <div className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-xl font-semibold">Contribution</h2>
                <div className="mt-4 grid gap-4">
                  <div className="grid gap-2 sm:grid-cols-3">
                    {["NGN", "USD", "CRYPTO"].map((item) => (
                      <button
                        key={item}
                        type="button"
                        onClick={() => setCurrency(item)}
                        className={`rounded-lg border px-3 py-2 text-xs font-semibold ${
                          currency === item ? "border-[#D4AF37] bg-[#D4AF37]/14 text-[#D4AF37]" : "border-[#F8F8F8]/20 bg-[#F8F8F8]/5 text-[#F8F8F8]/75"
                        }`}
                      >
                        {item}
                      </button>
                    ))}
                  </div>
                  <label className="text-sm">
                    Enter Support Amount ({currency})
                    <input
                      type="number"
                      value={amount}
                      onChange={(e) => setAmount(Math.max(100, Number(e.target.value) || 100))}
                      className="sc-input"
                    />
                  </label>
                  <p className="rounded-xl border border-[#D4AF37]/25 bg-[#D4AF37]/10 px-3 py-2 text-xs text-[#F8F8F8]/85">
                    Payment Mode: <span className="font-semibold text-[#D4AF37]">{currency}</span> selected.
                    {currency === "NGN" ? " You are paying in Naira." : null}
                    {currency === "USD" ? " You are paying in US Dollars." : null}
                    {currency === "CRYPTO" ? " You are paying in crypto token (USDT equivalent)." : null}
                  </p>
                  <div className="grid gap-2 sm:grid-cols-2">
                    {paymentMethods.map((method) => (
                      <button
                        key={method}
                        type="button"
                        onClick={() => setPaymentMethod(method)}
                        className={`rounded-lg border px-3 py-2 text-left text-xs font-semibold ${
                          paymentMethod === method ? "border-[#D4AF37] bg-[#D4AF37]/14 text-[#D4AF37]" : "border-[#F8F8F8]/20 bg-[#F8F8F8]/5 text-[#F8F8F8]/78"
                        }`}
                      >
                        {method}
                      </button>
                    ))}
                  </div>
                  {paymentMethod === "Card Payment" ? (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <label className="text-xs">
                        Card Number (16 digits)
                        <input
                          inputMode="numeric"
                          value={cardNumber}
                          onChange={(e) => setCardNumber(e.target.value.replace(/\D/g, "").slice(0, 16))}
                          className="sc-input mt-1"
                          placeholder="1234567890123456"
                        />
                      </label>
                      <label className="text-xs">
                        Expiry Date (MM/YY)
                        <input
                          inputMode="numeric"
                          value={expiryDate}
                          onChange={(e) => {
                            const digits = e.target.value.replace(/\D/g, "").slice(0, 4);
                            const formatted = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
                            setExpiryDate(formatted);
                          }}
                          className="sc-input mt-1"
                          placeholder="08/30"
                        />
                      </label>
                      <label className="text-xs">
                        CVV (3-digit code)
                        <input
                          inputMode="numeric"
                          value={cvv}
                          onChange={(e) => setCvv(e.target.value.replace(/\D/g, "").slice(0, 3))}
                          className="sc-input mt-1"
                          placeholder="123"
                        />
                      </label>
                      <label className="text-xs">
                        Cardholder Name
                        <input
                          value={accountName}
                          onChange={(e) => setAccountName(e.target.value)}
                          className="sc-input mt-1"
                          placeholder="Full name"
                        />
                      </label>
                      {!isCardPaymentValid ? (
                        <p className="sm:col-span-2 text-[11px] text-[#F8F8F8]/70">
                          Enter a valid 16-digit card number, MM/YY expiry, CVV, and cardholder name.
                        </p>
                      ) : null}
                    </div>
                  ) : null}
                  {paymentMethod === "Local Transfer" || paymentMethod === "Bank Wire" ? (
                    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                      <p className="inline-flex items-center gap-2 text-xs text-[#D4AF37]">
                        <Building2 className="h-4 w-4" />
                        Bank Transfer Instructions
                      </p>
                      <p className="mt-2 text-xs text-[#F8F8F8]/82">ExaEarn Escrow Bank: Zenith Bank</p>
                      <p className="text-xs text-[#F8F8F8]/82">Account No: 1023345567</p>
                      <p className="text-xs text-[#F8F8F8]/82">Reference: {campaign.id.toUpperCase()}-{Date.now().toString().slice(-6)}</p>
                    </div>
                  ) : null}
                  {paymentMethod === "Connect Wallet" || paymentMethod === "Pay with Crypto" ? (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <label className="text-xs">
                        Network
                        <select value={network} onChange={(e) => setNetwork(e.target.value)} className="sc-input mt-1">
                          <option value="ERC-20">ERC-20</option>
                          <option value="BEP-20">BEP-20</option>
                          <option value="Polygon">Polygon</option>
                        </select>
                      </label>
                      <label className="text-xs">
                        Wallet Address
                        <input
                          value={walletAddressInput}
                          onChange={(e) => setWalletAddressInput(e.target.value)}
                          className="sc-input mt-1"
                          placeholder="0x..."
                        />
                      </label>
                    </div>
                  ) : null}
                  {paymentMethod === "USSD / Mobile Money" ? (
                    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                      <p className="inline-flex items-center gap-2 text-xs text-[#D4AF37]">
                        <Smartphone className="h-4 w-4" />
                        Mobile Checkout Prompt
                      </p>
                      <p className="mt-2 text-xs text-[#F8F8F8]/82">You will receive a secure USSD/mobile prompt after clicking confirm.</p>
                    </div>
                  ) : null}
                  <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs">
                    <p className="flex items-center justify-between"><span>Contribution Value</span><span className="text-[#D4AF37]">{formatNaira(conversion.amountInNgn)}</span></p>
                    <p className="mt-1 flex items-center justify-between"><span>Equivalent USD</span><span>{formatUsd(conversion.amountInUsd)}</span></p>
                    <p className="mt-1 flex items-center justify-between"><span>Equivalent Crypto</span><span>{formatCrypto(conversion.amountInCrypto)}</span></p>
                    <p className="mt-1 flex items-center justify-between"><span>Platform Fee (1.5%)</span><span>{formatNaira(conversion.platformFee)}</span></p>
                    <p className="mt-2 flex items-center justify-between border-t border-[#F8F8F8]/12 pt-2 font-semibold"><span>Total Charge</span><span className="text-[#D4AF37]">{formatNaira(conversion.totalNgn)}</span></p>
                  </div>
                  <div className="rounded-xl border border-[#D4AF37]/25 bg-[#D4AF37]/8 p-3 text-sm text-[#F8F8F8]/85">
                    Estimated Reward / Equity: <span className="font-semibold text-[#D4AF37]">{estimatedBenefit}</span>
                  </div>
                  <button
                    disabled={paymentMethod === "Card Payment" && !isCardPaymentValid}
                    className="inline-flex items-center justify-center gap-2 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_22px_rgba(212,175,55,0.4)] disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    <Wallet className="h-4 w-4" />
                    Confirm Support
                  </button>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-xl font-semibold">Reward / Equity Tiers</h2>
                <div className="mt-4 grid gap-3">
                  {campaign.rewardTiers.map((tier) => (
                    <button
                      key={tier.id}
                      type="button"
                      onClick={() => {
                        setSelectedTierId(tier.id);
                        setAmount(tier.amount);
                      }}
                      className={`rounded-xl border p-4 text-left transition ${
                        selectedTierId === tier.id
                          ? "border-[#D4AF37]/85 bg-[#D4AF37]/12 shadow-[0_0_18px_rgba(212,175,55,0.25)]"
                          : "border-[#F8F8F8]/14 bg-[#F8F8F8]/[0.03]"
                      }`}
                    >
                      <p className="text-sm font-semibold text-[#F8F8F8]">{tier.title} - <span className="text-[#D4AF37]">{formatNaira(tier.amount)}</span></p>
                      <p className="mt-1 text-xs text-[#F8F8F8]/78">{tier.benefit}</p>
                    </button>
                  ))}
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-xl font-semibold">Campaign Story</h2>
                <div className="mt-4 grid gap-3 text-sm text-[#F8F8F8]/86">
                  <p><span className="font-semibold text-[#D4AF37]">Problem Statement:</span> {campaign.story.problem}</p>
                  <p><span className="font-semibold text-[#D4AF37]">Solution:</span> {campaign.story.solution}</p>
                  <p><span className="font-semibold text-[#D4AF37]">Market Opportunity:</span> {campaign.story.market}</p>
                  <p><span className="font-semibold text-[#D4AF37]">Roadmap:</span> {campaign.story.roadmap}</p>
                  <p><span className="font-semibold text-[#D4AF37]">Use of Funds:</span> {campaign.story.useOfFunds}</p>
                  <p><span className="font-semibold text-[#D4AF37]">Team Introduction:</span> {campaign.story.team}</p>
                </div>
                <div className="mt-4 relative overflow-hidden rounded-xl border border-[#D4AF37]/30">
                  <img src={Image.campaigns} alt="Pitch video cover" className="h-44 w-full object-cover opacity-45" />
                  <div className="absolute inset-0 flex items-center justify-center">
                    <PlayCircle className="h-12 w-12 text-[#D4AF37]" />
                  </div>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-xl font-semibold">Transparency & Security</h2>
                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                  <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs"><BadgeCheck className="mb-2 h-4 w-4 text-[#D4AF37]" /> Verified Founder Identity</p>
                  <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs"><Blocks className="mb-2 h-4 w-4 text-[#D4AF37]" /> Blockchain Recorded Contributions</p>
                  <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs"><ShieldCheck className="mb-2 h-4 w-4 text-[#D4AF37]" /> Escrow / Smart Contract Distribution</p>
                </div>
              </article>
            </div>

            <aside className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">Community Engagement</h3>
                <div className="mt-3 rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <label className="text-xs">Comments, Q&A, Updates</label>
                  <textarea
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    className="sc-input mt-2 min-h-[82px]"
                    placeholder="Post your question or update..."
                  />
                  <button
                    type="button"
                    onClick={submitComment}
                    className="mt-3 inline-flex items-center gap-2 rounded-lg border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-3 py-2 text-xs font-semibold text-[#0B0B0B]"
                  >
                    <MessageCircleMore className="h-4 w-4" />
                    Post Comment
                  </button>
                </div>
                <div className="mt-3 space-y-2">
                  {comments.map((item, idx) => (
                    <p key={`${item}-${idx}`} className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs text-[#F8F8F8]/84">{item}</p>
                  ))}
                </div>
                <div className="mt-3 rounded-xl border border-[#D4AF37]/25 bg-[#D4AF37]/8 p-3">
                  <p className="text-xs font-semibold text-[#D4AF37]">Backer Activity Feed</p>
                  <ul className="mt-2 space-y-1 text-xs text-[#F8F8F8]/82">
                    {campaign.activity.map((line) => (
                      <li key={line}>- {line}</li>
                    ))}
                  </ul>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">Impact Metrics</h3>
                <div className="mt-3 grid grid-cols-2 gap-3">
                  <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs">
                    Backers
                    <span className="mt-1 block text-base font-semibold text-[#D4AF37]"><Counter value={campaign.metrics.backers} /></span>
                  </p>
                  <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs">
                    Funds Deployed
                    <span className="mt-1 block text-base font-semibold text-[#D4AF37]">{formatNaira(campaign.metrics.fundsDeployed)}</span>
                  </p>
                  <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs">
                    Projects Funded
                    <span className="mt-1 block text-base font-semibold text-[#D4AF37]"><Counter value={campaign.metrics.projectsFunded} /></span>
                  </p>
                  <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs">
                    Countries Participating
                    <span className="mt-1 block text-base font-semibold text-[#D4AF37]"><Counter value={campaign.metrics.countries} /></span>
                  </p>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-r from-[#D4AF37]/14 via-[#D4AF37]/6 to-transparent p-5 text-center">
                <h3 className="font-['Sora'] text-xl font-semibold">Your Support Builds Innovation. Your Capital Powers the Future.</h3>
                <button className="mt-4 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-5 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.36)]">
                  Support & Invest Now
                </button>
                <p className="mt-2 text-xs text-[#F8F8F8]/66">Secure, Transparent, Community-Driven</p>
              </article>
            </aside>
          </section>
        </section>
      </div>
    </main>
  );
}

export default SupportCampaignPage;
