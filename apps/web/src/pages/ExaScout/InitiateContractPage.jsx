import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  CalendarDays,
  CircleDollarSign,
  Clock3,
  CreditCard,
  Landmark,
  ShieldCheck,
  Timer,
  Wallet,
} from "lucide-react";

const sportOptions = ["Football", "Basketball", "Athletics", "Tennis"];
const leagueOptions = [
  "Premier Talent League",
  "Elite Development Cup",
  "Continental Showcase",
  "Academy Invitational",
];
const eventOptions = [
  "Matchday 7: Hawks vs Titans",
  "Matchday 8: Metro FC vs Valley Stars",
  "Quarterfinal: Northside vs Southside",
  "Final: United Academy vs Royal Athletic",
];
const predictionTypes = ["Win/Loss", "Scoreline", "Points Spread", "Player Performance Index"];
const inputCls = "w-full rounded-lg border border-[#F8F8F8]/22 bg-[#0B0B0B]/70 px-3 py-2 text-sm text-[#F8F8F8] outline-none transition focus:border-[#D4AF37]/70 focus:ring-2 focus:ring-[#D4AF37]/20";

function formatCurrency(amount, currency) {
  if (currency === "USD") return `$${amount.toLocaleString()}`;
  if (currency === "CRYPTO") return `${amount.toLocaleString()} USDT`;
  return `NGN ${amount.toLocaleString()}`;
}

function digitsOnly(value) {
  return value.replace(/\D/g, "");
}

function formatCardNumber(value) {
  const digits = digitsOnly(value).slice(0, 16);
  return digits.replace(/(\d{4})(?=\d)/g, "$1 ").trim();
}

function formatExpiry(value) {
  const digits = digitsOnly(value).slice(0, 4);
  if (digits.length < 3) return digits;
  return `${digits.slice(0, 2)}/${digits.slice(2)}`;
}

function isFutureExpiry(value) {
  if (!/^\d{2}\/\d{2}$/.test(value)) return false;
  const [mm, yy] = value.split("/").map(Number);
  if (mm < 1 || mm > 12) return false;
  const now = new Date();
  const currentYear = now.getFullYear() % 100;
  const currentMonth = now.getMonth() + 1;
  return yy > currentYear || (yy === currentYear && mm >= currentMonth);
}

function secondsToCountdown(target) {
  const diff = Math.max(0, Math.floor((target.getTime() - Date.now()) / 1000));
  const days = Math.floor(diff / 86400);
  const hours = Math.floor((diff % 86400) / 3600);
  const minutes = Math.floor((diff % 3600) / 60);
  const seconds = diff % 60;
  return { days, hours, minutes, seconds };
}

function InitiateContractPage({ onBack }) {
  const [sport, setSport] = useState("Football");
  const [league, setLeague] = useState(leagueOptions[0]);
  const [eventName, setEventName] = useState(eventOptions[0]);
  const [predictionType, setPredictionType] = useState(predictionTypes[0]);
  const [currency, setCurrency] = useState("NGN");
  const [entryFee, setEntryFee] = useState(20000);
  const [minStake, setMinStake] = useState(10000);
  const [maxStake, setMaxStake] = useState(100000);
  const [participants, setParticipants] = useState(36);
  const contractDuration = "72 hours";
  const lockPeriod = "30 mins before kickoff";
  const [paymentMethod, setPaymentMethod] = useState("wallet");
  const [walletProvider, setWalletProvider] = useState("MetaMask");
  const [walletNetwork, setWalletNetwork] = useState("Ethereum");
  const [walletAddress, setWalletAddress] = useState("");
  const [walletConnected, setWalletConnected] = useState(false);
  const [cardNumber, setCardNumber] = useState("");
  const [cardExpiry, setCardExpiry] = useState("");
  const [cardCvv, setCardCvv] = useState("");
  const [cardName, setCardName] = useState("");
  const [internalSource, setInternalSource] = useState("Main Wallet");
  const [internalPin, setInternalPin] = useState("");
  const [internalOtp, setInternalOtp] = useState("");
  const [acceptTerms, setAcceptTerms] = useState(false);
  const matchDate = useMemo(() => new Date(Date.now() + 2 * 24 * 60 * 60 * 1000 + 5 * 60 * 60 * 1000), []);
  const [countdown, setCountdown] = useState(() => secondsToCountdown(matchDate));

  useEffect(() => {
    const interval = setInterval(() => setCountdown(secondsToCountdown(matchDate)), 1000);
    return () => clearInterval(interval);
  }, [matchDate]);

  const platformFeeRate = 0.04;
  const poolTarget = maxStake * 60;
  const grossPool = participants * entryFee;
  const platformFee = Math.round(grossPool * platformFeeRate);
  const netPrizePool = grossPool - platformFee;
  const poolProgress = Math.min(100, Math.round((grossPool / poolTarget) * 100));
  const estimatedWinnings = Math.round(netPrizePool * 0.35);
  const payoutDate = useMemo(() => new Date(matchDate.getTime() + 3 * 60 * 60 * 1000), [matchDate]);
  const contractId = useMemo(() => `EXA-ST-${Date.now().toString().slice(-7)}`, []);
  const walletBalance = currency === "USD" ? 1280 : currency === "CRYPTO" ? 950 : 845000;
  const networkFee = currency === "CRYPTO" ? "0.0018 ETH" : formatCurrency(Math.max(120, Math.round(entryFee * 0.006)), currency);
  const processingFee = paymentMethod === "card" ? Math.round(entryFee * 0.015) : 0;
  const totalTransaction = entryFee + processingFee;
  const cardNumberDigits = digitsOnly(cardNumber);
  const cardCvvDigits = digitsOnly(cardCvv);
  const isCardReady = cardNumberDigits.length === 16 && isFutureExpiry(cardExpiry) && cardCvvDigits.length === 3 && cardName.trim().length >= 3;
  const isWalletReady = walletConnected && walletAddress.length > 10;
  const isBalanceReady = internalPin.length === 4 && internalOtp.length === 6;
  const isPaymentReady = paymentMethod === "wallet" ? isWalletReady : paymentMethod === "card" ? isCardReady : isBalanceReady;

  const connectWallet = () => {
    setWalletConnected(true);
    setWalletAddress("0x8A23...12CD");
  };

  const disconnectWallet = () => {
    setWalletConnected(false);
    setWalletAddress("");
  };

  return (
    <main className="min-h-screen exa-bg text-[#F8F8F8]">
      <div className="mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <section className="rounded-3xl border border-[#D4AF37]/30 bg-[#0B0B0B]/72 p-4 shadow-[0_24px_60px_rgba(0,0,0,0.45)] backdrop-blur-xl sm:p-6">
          <header className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/60 p-4 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              {onBack ? (
                <button type="button" onClick={onBack} className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/35 bg-[#0B0B0B]/70 px-3 py-2 text-xs font-semibold text-[#F8F8F8] hover:border-[#D4AF37] hover:text-[#D4AF37]">
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <span className="inline-flex items-center gap-1 rounded-full border border-[#D4AF37]/55 bg-[#D4AF37]/14 px-3 py-1 text-xs font-semibold text-[#D4AF37]">
                <ShieldCheck className="h-3.5 w-3.5" />
                Blockchain Verified
              </span>
            </div>

            <div className="mt-4 grid gap-5 lg:grid-cols-[1.18fr_0.82fr]">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-[#D4AF37]/80">ExaEarn Sports Tech</p>
                <h1 className="mt-2 font-['Sora'] text-3xl font-semibold sm:text-5xl">Initiate Sports Trial Contract</h1>
                <p className="mt-3 max-w-2xl text-sm text-[#F8F8F8]/80 sm:text-base">
                  Enter a transparent, smart-contract powered sports prediction challenge. Compete. Perform. Earn.
                </p>
                <div className="mt-4 flex flex-wrap gap-2 text-xs">
                  <p className="rounded-lg border border-[#F8F8F8]/14 bg-[#F8F8F8]/[0.03] px-3 py-2">{sport}</p>
                  <p className="rounded-lg border border-[#F8F8F8]/14 bg-[#F8F8F8]/[0.03] px-3 py-2">{league}</p>
                  <p className="inline-flex items-center gap-1 rounded-lg border border-[#F8F8F8]/14 bg-[#F8F8F8]/[0.03] px-3 py-2"><CalendarDays className="h-3.5 w-3.5 text-[#D4AF37]" />{matchDate.toLocaleDateString()}</p>
                </div>
                <button className="mt-4 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-6 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.32)]">
                  Start Contract Setup
                </button>
              </div>

              <aside className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <p className="text-xs uppercase tracking-[0.16em] text-[#D4AF37]/85">Countdown to Match</p>
                <div className="mt-3 grid grid-cols-4 gap-2 text-center">
                  <CountdownBox label="D" value={countdown.days} />
                  <CountdownBox label="H" value={countdown.hours} />
                  <CountdownBox label="M" value={countdown.minutes} />
                  <CountdownBox label="S" value={countdown.seconds} pulse />
                </div>
                <p className="mt-3 text-xs text-[#F8F8F8]/72">Competition integrity lock activates automatically {lockPeriod}.</p>
              </aside>
            </div>
          </header>

          <section className="mt-6 grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
            <div className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-lg font-semibold">Contract Configuration</h2>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                  <Field label="Sport Category"><select value={sport} onChange={(e) => setSport(e.target.value)} className={inputCls}>{sportOptions.map((item) => <option key={item}>{item}</option>)}</select></Field>
                  <Field label="League / Tournament"><select value={league} onChange={(e) => setLeague(e.target.value)} className={inputCls}>{leagueOptions.map((item) => <option key={item}>{item}</option>)}</select></Field>
                  <Field label="Match / Event"><select value={eventName} onChange={(e) => setEventName(e.target.value)} className={inputCls}>{eventOptions.map((item) => <option key={item}>{item}</option>)}</select></Field>
                  <Field label="Prediction Type"><select value={predictionType} onChange={(e) => setPredictionType(e.target.value)} className={inputCls}>{predictionTypes.map((item) => <option key={item}>{item}</option>)}</select></Field>
                </div>

                <h3 className="mt-5 text-sm font-semibold text-[#D4AF37]">Entry & Stake</h3>
                <div className="mt-2 flex flex-wrap gap-2">
                  {["NGN", "USD", "CRYPTO"].map((unit) => (
                    <button
                      key={unit}
                      type="button"
                      onClick={() => setCurrency(unit)}
                      className={`rounded-lg border px-3 py-1.5 text-xs font-semibold ${currency === unit ? "border-[#D4AF37] bg-[#D4AF37]/14 text-[#D4AF37]" : "border-[#F8F8F8]/20 bg-[#0B0B0B]/65 text-[#F8F8F8]/72"}`}
                    >
                      {unit}
                    </button>
                  ))}
                </div>

                <div className="mt-3 grid gap-3 sm:grid-cols-3">
                  <Field label="Entry Fee"><input type="number" min={100} value={entryFee} onChange={(e) => setEntryFee(Math.max(100, Number(e.target.value || 0)))} className={inputCls} /></Field>
                  <Field label="Minimum Stake"><input type="number" min={100} value={minStake} onChange={(e) => setMinStake(Math.max(100, Number(e.target.value || 0)))} className={inputCls} /></Field>
                  <Field label="Maximum Stake"><input type="number" min={minStake} value={maxStake} onChange={(e) => setMaxStake(Math.max(minStake, Number(e.target.value || 0)))} className={inputCls} /></Field>
                </div>

                <div className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Prize Pool Preview: <span className="font-semibold text-[#D4AF37]">{formatCurrency(grossPool, currency)}</span></p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Platform Fee ({Math.round(platformFeeRate * 100)}%): <span className="font-semibold text-[#D4AF37]">{formatCurrency(platformFee, currency)}</span></p>
                </div>
                <div className="mt-3">
                  <div className="mb-1 flex items-center justify-between text-xs text-[#F8F8F8]/75">
                    <span>Prize Pool Build</span>
                    <span>{poolProgress}%</span>
                  </div>
                  <div className="h-2 overflow-hidden rounded-full bg-[#F8F8F8]/14">
                    <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020]" style={{ width: `${poolProgress}%` }} />
                  </div>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-lg font-semibold">Smart Contract Terms</h2>
                <div className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                  <Term label="Contract Duration" value={contractDuration} />
                  <Term label="Lock-in Period" value={lockPeriod} />
                  <Term label="Payout Distribution" value="Top 3 ranked outcomes split 60% / 25% / 15%" />
                  <Term label="Winner Determination" value="Oracle-verified final stats + rules engine resolution" />
                  <Term label="Refund Conditions" value="Automatic refund if event canceled or invalidated" />
                  <Term label="Risk Disclaimer" value="Skill-based competition; outcomes depend on predictive accuracy" />
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                  <button className="rounded-lg border border-[#D4AF37]/65 bg-[#D4AF37]/14 px-3 py-2 text-xs font-semibold text-[#D4AF37]">View Smart Contract Logic</button>
                  <button className="inline-flex items-center gap-1 rounded-lg border border-[#F8F8F8]/20 bg-[#0B0B0B]/65 px-3 py-2 text-xs font-semibold text-[#F8F8F8]"><BadgeCheck className="h-3.5 w-3.5 text-[#D4AF37]" />Blockchain Verified</button>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-lg font-semibold">Wallet & Payment</h2>
                <div className="mt-3 grid gap-2 sm:grid-cols-3">
                  <PaymentButton id="wallet" active={paymentMethod === "wallet"} onClick={() => setPaymentMethod("wallet")} icon={<Wallet className="h-4 w-4" />} label="Connect Wallet" />
                  <PaymentButton id="card" active={paymentMethod === "card"} onClick={() => setPaymentMethod("card")} icon={<CreditCard className="h-4 w-4" />} label="Pay with Card" />
                  <PaymentButton id="balance" active={paymentMethod === "balance"} onClick={() => setPaymentMethod("balance")} icon={<Landmark className="h-4 w-4" />} label="Internal Wallet" />
                </div>
                {paymentMethod === "wallet" ? (
                  <div className="mt-3 rounded-xl border border-[#F8F8F8]/14 bg-[#F8F8F8]/[0.03] p-3">
                    <div className="grid gap-3 sm:grid-cols-2">
                      <Field label="Wallet Provider">
                        <select value={walletProvider} onChange={(e) => setWalletProvider(e.target.value)} className={inputCls}>
                          <option>MetaMask</option>
                          <option>WalletConnect</option>
                          <option>Coinbase Wallet</option>
                        </select>
                      </Field>
                      <Field label="Network">
                        <select value={walletNetwork} onChange={(e) => setWalletNetwork(e.target.value)} className={inputCls}>
                          <option>Ethereum</option>
                          <option>BNB Chain</option>
                          <option>Polygon</option>
                        </select>
                      </Field>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {!walletConnected ? (
                        <button type="button" onClick={connectWallet} className="rounded-lg border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-3 py-2 text-xs font-semibold text-[#0B0B0B]">
                          Connect Wallet
                        </button>
                      ) : (
                        <button type="button" onClick={disconnectWallet} className="rounded-lg border border-[#F8F8F8]/30 bg-[#0B0B0B]/65 px-3 py-2 text-xs font-semibold text-[#F8F8F8]">
                          Disconnect Wallet
                        </button>
                      )}
                      <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#0B0B0B]/70 px-3 py-2 text-xs text-[#F8F8F8]/80">
                        Address: <span className="font-semibold text-[#D4AF37]">{walletConnected ? walletAddress : "Not connected"}</span>
                      </p>
                    </div>
                  </div>
                ) : null}
                {paymentMethod === "card" ? (
                  <div className="mt-3 rounded-xl border border-[#F8F8F8]/14 bg-[#F8F8F8]/[0.03] p-3">
                    <div className="grid gap-3 sm:grid-cols-2">
                      <Field label="Card Number (16-digit)">
                        <input
                          type="text"
                          inputMode="numeric"
                          value={cardNumber}
                          onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
                          placeholder="1234 5678 9012 3456"
                          className={inputCls}
                        />
                      </Field>
                      <Field label="Cardholder Name">
                        <input
                          type="text"
                          value={cardName}
                          onChange={(e) => setCardName(e.target.value)}
                          placeholder="John Doe"
                          className={inputCls}
                        />
                      </Field>
                      <Field label="Expiry Date (MM/YY)">
                        <input
                          type="text"
                          inputMode="numeric"
                          value={cardExpiry}
                          onChange={(e) => setCardExpiry(formatExpiry(e.target.value))}
                          placeholder="08/29"
                          className={inputCls}
                        />
                      </Field>
                      <Field label="CVV (3-digit)">
                        <input
                          type="password"
                          inputMode="numeric"
                          value={cardCvv}
                          onChange={(e) => setCardCvv(digitsOnly(e.target.value).slice(0, 3))}
                          placeholder="123"
                          className={inputCls}
                        />
                      </Field>
                    </div>
                    {!isCardReady ? (
                      <p className="mt-2 text-xs text-[#F8F8F8]/70">
                        Complete valid card details to continue: 16-digit card number, valid MM/YY, 3-digit CVV, and cardholder name.
                      </p>
                    ) : null}
                  </div>
                ) : null}
                {paymentMethod === "balance" ? (
                  <div className="mt-3 rounded-xl border border-[#F8F8F8]/14 bg-[#F8F8F8]/[0.03] p-3">
                    <div className="grid gap-3 sm:grid-cols-3">
                      <Field label="Wallet Source">
                        <select value={internalSource} onChange={(e) => setInternalSource(e.target.value)} className={inputCls}>
                          <option>Main Wallet</option>
                          <option>Reward Wallet</option>
                          <option>Stablecoin Wallet</option>
                        </select>
                      </Field>
                      <Field label="Transaction PIN (4-digit)">
                        <input
                          type="password"
                          inputMode="numeric"
                          value={internalPin}
                          onChange={(e) => setInternalPin(digitsOnly(e.target.value).slice(0, 4))}
                          placeholder="0000"
                          className={inputCls}
                        />
                      </Field>
                      <Field label="OTP Code (6-digit)">
                        <input
                          type="text"
                          inputMode="numeric"
                          value={internalOtp}
                          onChange={(e) => setInternalOtp(digitsOnly(e.target.value).slice(0, 6))}
                          placeholder="123456"
                          className={inputCls}
                        />
                      </Field>
                    </div>
                    {!isBalanceReady ? <p className="mt-2 text-xs text-[#F8F8F8]/70">Enter valid 4-digit PIN and 6-digit OTP to authorize internal wallet payment.</p> : null}
                  </div>
                ) : null}
                <div className="mt-3 grid gap-2 text-xs sm:grid-cols-3">
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Method: <span className="font-semibold text-[#D4AF37]">{paymentMethod === "wallet" ? walletProvider : paymentMethod === "card" ? "Card Payment" : internalSource}</span></p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Wallet Balance: <span className="font-semibold text-[#D4AF37]">{formatCurrency(walletBalance, currency)}</span></p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Network Fee Estimate: <span className="font-semibold text-[#D4AF37]">{networkFee}</span></p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Processing Fee: <span className="font-semibold text-[#D4AF37]">{formatCurrency(processingFee, currency)}</span></p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Total Transaction: <span className="font-semibold text-[#D4AF37]">{formatCurrency(totalTransaction, currency)}</span></p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Status: <span className={`font-semibold ${isPaymentReady ? "text-[#D4AF37]" : "text-[#F8F8F8]/70"}`}>{isPaymentReady ? "Ready to sign" : "Awaiting valid payment details"}</span></p>
                </div>
                <label className="mt-3 inline-flex items-center gap-2 text-xs text-[#F8F8F8]/75">
                  <input type="checkbox" checked={acceptTerms} onChange={(e) => setAcceptTerms(e.target.checked)} className="accent-[#D4AF37]" />
                  I confirm payment authorization and agree to smart-contract execution terms.
                </label>
                <button
                  disabled={!isPaymentReady || !acceptTerms}
                  className={`mt-4 w-full rounded-xl border px-4 py-3 text-sm font-semibold transition ${isPaymentReady && acceptTerms ? "border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] text-[#0B0B0B] shadow-[0_0_22px_rgba(212,175,55,0.32)]" : "border-[#F8F8F8]/20 bg-[#F8F8F8]/10 text-[#F8F8F8]/50 cursor-not-allowed"}`}
                >
                  Sign & Initiate Contract
                </button>
              </article>
            </div>

            <aside className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-lg font-semibold">Live Contract Preview</h2>
                <div className="mt-3 grid gap-2 text-xs">
                  <PreviewRow label="Selected Match" value={eventName} />
                  <PreviewRow label="Entry Fee" value={formatCurrency(entryFee, currency)} gold />
                  <PreviewRow label="Participants" value={`${participants} public entries`} />
                  <PreviewRow label="Estimated Winnings" value={formatCurrency(estimatedWinnings, currency)} gold />
                  <PreviewRow label="Contract ID" value={contractId} />
                  <PreviewRow label="Estimated Payout Date" value={payoutDate.toLocaleString()} />
                </div>
                <label className="mt-3 block text-xs text-[#F8F8F8]/70">
                  Participants (public pool)
                  <input type="range" min={5} max={100} value={participants} onChange={(e) => setParticipants(Number(e.target.value))} className="mt-2 w-full accent-[#D4AF37]" />
                </label>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                <h2 className="font-['Sora'] text-lg font-semibold">Fair Play & Transparency</h2>
                <div className="mt-3 grid gap-2 text-xs">
                  <FairItem icon={<ShieldCheck className="h-4 w-4 text-[#D4AF37]" />} text="Smart Contract Secured" />
                  <FairItem icon={<Blocks className="h-4 w-4 text-[#D4AF37]" />} text="No Manual Result Manipulation" />
                  <FairItem icon={<CircleDollarSign className="h-4 w-4 text-[#D4AF37]" />} text="Automated Payout Distribution" />
                  <FairItem icon={<Timer className="h-4 w-4 text-[#D4AF37]" />} text="Transparent Leaderboard Resolution" />
                  <FairItem icon={<Clock3 className="h-4 w-4 text-[#D4AF37]" />} text="On-chain Verification Trail" />
                </div>
              </article>
            </aside>
          </section>

          <section className="mt-6 rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-r from-[#D4AF37]/12 via-[#D4AF37]/6 to-transparent p-6 text-center">
            <h2 className="font-['Sora'] text-3xl font-semibold sm:text-4xl">Compete With Skill. Earn With Integrity.</h2>
            <button className="mt-4 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-6 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.34)]">
              Confirm & Deploy Contract
            </button>
            <p className="mt-2 text-xs text-[#F8F8F8]/70">By initiating this contract, you agree to ExaEarn Sports Tech terms.</p>
          </section>
        </section>
      </div>
    </main>
  );
}

function Field({ label, children }) {
  return (
    <label className="text-xs text-[#F8F8F8]/78">
      {label}
      <div className="mt-1">{children}</div>
    </label>
  );
}

function Term({ label, value }) {
  return (
    <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">
      <span className="block text-[11px] text-[#F8F8F8]/66">{label}</span>
      <span className="text-[#F8F8F8]/86">{value}</span>
    </p>
  );
}

function PaymentButton({ id, active, onClick, icon, label }) {
  return (
    <button
      type="button"
      id={id}
      onClick={onClick}
      className={`inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold ${active ? "border-[#D4AF37] bg-[#D4AF37]/14 text-[#D4AF37]" : "border-[#F8F8F8]/20 bg-[#0B0B0B]/65 text-[#F8F8F8]/75"}`}
    >
      {icon}
      {label}
    </button>
  );
}

function PreviewRow({ label, value, gold = false }) {
  return (
    <div className="flex items-center justify-between rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] px-3 py-2">
      <span className="text-[#F8F8F8]/70">{label}</span>
      <span className={`font-semibold ${gold ? "text-[#D4AF37]" : "text-[#F8F8F8]"}`}>{value}</span>
    </div>
  );
}

function FairItem({ icon, text }) {
  return (
    <p className="inline-flex items-center gap-2 rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] px-3 py-2">
      {icon}
      <span>{text}</span>
    </p>
  );
}

function CountdownBox({ label, value, pulse = false }) {
  return (
    <div className={`rounded-lg border border-[#D4AF37]/35 bg-[#D4AF37]/10 p-2 ${pulse ? "animate-pulse" : ""}`}>
      <p className="text-lg font-semibold text-[#D4AF37]">{String(value).padStart(2, "0")}</p>
      <p className="text-[10px] text-[#F8F8F8]/70">{label}</p>
    </div>
  );
}

export default InitiateContractPage;
