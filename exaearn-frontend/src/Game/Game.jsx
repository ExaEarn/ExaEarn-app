import { useMemo, useState } from "react";
import { ArrowLeft, Check, Coins, Copy, ExternalLink, ShieldCheck } from "lucide-react";
import earnLogo from "../assets/images/earn.jpg";
import ethereumLogo from "../assets/images/ethereum-eth-logo.png";
import ActionButton from "./ActionButton";
import StatCard from "./StatCard";
import "./Game.css";

const CONTRACT_ADDRESS = "0x079...6748";
const INITIAL_PARTICIPANTS = 2;
const POT_ETH = 0.025;
const WINNER_WALLET = "0x1234B6A7F81C2C6D9345EF92A8613B45D489AB";
const PAYOUT_TX_HASH = "0x9f5a2b7f8e23d921bc8ea8122db8e6f18d4a4c341d25a2087ab5220df33c9a5b";
const PAYOUT_TIMESTAMP = "March 3, 2026 - 08:42 UTC";
const PAYOUT_PRIZE = "0.025 ETH";
const PAYOUT_LINK = `https://etherscan.io/tx/${PAYOUT_TX_HASH}`;

function shortenWallet(address) {
  if (!address || address.length < 10) return address;
  return `${address.slice(0, 6)}...${address.slice(-4)}`;
}

function shortenHash(hash) {
  if (!hash || hash.length < 14) return hash;
  return `${hash.slice(0, 10)}...${hash.slice(-8)}`;
}

function Game({ onBack }) {
  const [entryEth, setEntryEth] = useState("0.01");
  const [isEntering, setIsEntering] = useState(false);
  const [isPicking, setIsPicking] = useState(false);
  const [copied, setCopied] = useState(false);

  const isInvalidEntry = useMemo(() => {
    const parsed = Number(entryEth);
    return !entryEth || Number.isNaN(parsed) || parsed <= 0;
  }, [entryEth]);

  const handlePickWinner = async () => {
    if (isInvalidEntry || isPicking || isEntering) {
      return;
    }

    setIsPicking(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 1400));
    } finally {
      setIsPicking(false);
    }
  };

  const handleEnterDraw = async () => {
    if (isInvalidEntry || isPicking || isEntering) {
      return;
    }

    setIsEntering(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 1200));
    } finally {
      setIsEntering(false);
    }
  };

  const handleCopyHash = async () => {
    try {
      await navigator.clipboard.writeText(PAYOUT_TX_HASH);
      setCopied(true);
      setTimeout(() => setCopied(false), 1400);
    } catch {
      setCopied(false);
    }
  };

  return (
    <main className="game-bg relative min-h-screen overflow-hidden px-4 py-10 sm:px-6 sm:py-14">
      <div className="pointer-events-none absolute inset-0 opacity-70" aria-hidden="true" />

      <section className="game-shell game-glow mx-auto w-full max-w-2xl rounded-[2rem] p-6 shadow-cosmic-card backdrop-blur-xl sm:p-10">
        <header className="animate-float-in text-center">
          {onBack ? (
            <div className="mb-4 flex justify-start">
              <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Back
              </button>
            </div>
          ) : null}
          <div className="game-logo-ring mx-auto flex h-28 w-28 items-center justify-center rounded-full p-1 sm:h-32 sm:w-32">
            <div className="game-logo-core flex h-full w-full items-center justify-center rounded-full">
              <img src={earnLogo} alt="ExaEarn logo" className="h-16 w-16 rounded-full object-cover sm:h-20 sm:w-20" />
            </div>
          </div>

          <h1 className="mt-6 font-['Sora'] text-4xl font-semibold tracking-tight text-auric-300 sm:text-5xl">Contract Manager</h1>
          <p className="mt-3 text-base text-violet-200/70 sm:text-lg">{CONTRACT_ADDRESS}</p>
        </header>

        <div className="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6">
          <StatCard label="Participants" value={INITIAL_PARTICIPANTS} />
          <StatCard label="Ether to Win" value={POT_ETH} />
        </div>

        <div className="mt-10">
          <label htmlFor="entryAmount" className="block text-2xl font-semibold text-violet-50">
            Enter the Draw
          </label>

          <div className="game-input-wrap mt-4 flex items-center rounded-2xl px-4 py-3 sm:px-5">
            <input
              id="entryAmount"
              type="number"
              min="0"
              step="0.001"
              value={entryEth}
              onChange={(event) => setEntryEth(event.target.value)}
              className="w-full bg-transparent text-2xl font-medium text-violet-50 outline-none placeholder:text-violet-200/45"
              placeholder="0.01"
              aria-label="Entry Ether amount"
            />
            <div className="ml-3 flex h-10 w-10 items-center justify-center rounded-full border border-auric-400/40 bg-cosmic-900/70 text-auric-400">
              <img src={ethereumLogo} alt="Ethereum" className="h-5 w-5 object-contain" />
            </div>
          </div>

          <div className="mt-8 space-y-3">
            <ActionButton isLoading={isEntering} loadingText="Entering..." disabled={isInvalidEntry || isPicking} onClick={handleEnterDraw}>
              Enter Draw
            </ActionButton>
            <ActionButton isLoading={isPicking} disabled={isInvalidEntry || isEntering} onClick={handlePickWinner}>
              <Coins className="h-5 w-5" aria-hidden="true" />
              Pick Winner
            </ActionButton>
          </div>
        </div>

        <section className="winner-proof-panel mt-10 rounded-3xl p-4 sm:p-6">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="inline-flex items-center gap-2 font-['Sora'] text-xl font-semibold text-violet-50">
              <ShieldCheck className="h-5 w-5 text-auric-300" aria-hidden="true" />
              Winner Proof
            </h2>
            <span className="winner-proof-pill">Verified Payout</span>
          </div>

          <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div className="winner-proof-row">
              <span className="winner-proof-label">Winner Wallet</span>
              <span className="winner-proof-value">{shortenWallet(WINNER_WALLET)}</span>
            </div>
            <div className="winner-proof-row">
              <span className="winner-proof-label">Prize Amount</span>
              <span className="winner-proof-value text-auric-300">{PAYOUT_PRIZE}</span>
            </div>
            <div className="winner-proof-row sm:col-span-2">
              <span className="winner-proof-label">Payout Transaction</span>
              <div className="flex items-center gap-2">
                <span className="winner-proof-value">{shortenHash(PAYOUT_TX_HASH)}</span>
                <button
                  type="button"
                  onClick={handleCopyHash}
                  className="winner-proof-copy"
                  aria-label="Copy payout transaction hash"
                >
                  {copied ? <Check className="h-3.5 w-3.5" aria-hidden="true" /> : <Copy className="h-3.5 w-3.5" aria-hidden="true" />}
                  {copied ? "Copied" : "Copy"}
                </button>
              </div>
            </div>
            <div className="winner-proof-row sm:col-span-2">
              <span className="winner-proof-label">Timestamp</span>
              <span className="winner-proof-value">{PAYOUT_TIMESTAMP}</span>
            </div>
          </div>

          <a href={PAYOUT_LINK} target="_blank" rel="noreferrer" className="winner-proof-link mt-4 inline-flex items-center gap-2 text-sm">
            View on Blockchain
            <ExternalLink className="h-4 w-4" aria-hidden="true" />
          </a>
        </section>
      </section>
    </main>
  );
}

export default Game;
