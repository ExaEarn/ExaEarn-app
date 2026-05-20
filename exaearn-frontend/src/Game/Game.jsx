import { useCallback, useEffect, useMemo, useState } from "react";
import { ArrowLeft, Check, Coins, Copy, ExternalLink, ShieldCheck } from "lucide-react";
import { useAuth } from "../context/AuthContext";
import earnLogo from "../assets/images/earn.jpg";
import ethereumLogo from "../assets/images/ethereum-eth-logo.png";
import { fetchLotteryGame, fetchLotteryGames, joinLotteryGame } from "../services/gameFiApi";
import ActionButton from "./ActionButton";
import StatCard from "./StatCard";
import "./Game.css";

const FALLBACK_CONTRACT_ADDRESS = "Awaiting contract deployment";

function shortenWallet(address) {
  if (!address || address.length < 10) return address;
  return `${address.slice(0, 6)}...${address.slice(-4)}`;
}

function shortenHash(hash) {
  if (!hash || hash.length < 14) return hash;
  return `${hash.slice(0, 10)}...${hash.slice(-8)}`;
}

function formatDrawTime(value) {
  if (!value) {
    return "Pending draw";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

function Game({ onBack }) {
  const { apiBaseUrl, token, user } = useAuth();
  const [entryEth, setEntryEth] = useState("0.01");
  const [walletAddress, setWalletAddress] = useState("");
  const [isEntering, setIsEntering] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [copied, setCopied] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [activeGame, setActiveGame] = useState(null);

  const isInvalidEntry = useMemo(() => {
    const parsed = Number(entryEth);
    return !entryEth || Number.isNaN(parsed) || parsed <= 0;
  }, [entryEth]);

  const latestResult = activeGame?.results?.[activeGame.results.length - 1] || activeGame?.latest_result || null;
  const payoutTxHash = latestResult?.tx_hash || "";
  const payoutLink = payoutTxHash ? `https://basescan.org/tx/${payoutTxHash}` : "#";

  const loadGame = useCallback(async (preferredGameId) => {
    setLoading(true);
    setError("");

    try {
      const listPayload = await fetchLotteryGames({ apiBaseUrl, token });
      const games = Array.isArray(listPayload?.data) ? listPayload.data : [];
      const targetGame = games.find((game) => game.id === preferredGameId) || games.find((game) => game.status === "open") || games[0];

      if (!targetGame) {
        setActiveGame(null);
        return;
      }

      const detailPayload = await fetchLotteryGame({ apiBaseUrl, token, gameId: targetGame.id });
      const game = detailPayload?.data || null;

      setActiveGame(game);
      if (game?.entry_fee_eth) {
        setEntryEth(String(game.entry_fee_eth));
      }
    } catch (loadError) {
      setError(loadError.message || "Unable to load game data.");
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl, token]);

  useEffect(() => {
    loadGame();
  }, [loadGame]);

  const handleRefreshResult = async () => {
    if (!activeGame || isRefreshing || isEntering) {
      return;
    }

    setIsRefreshing(true);
    try {
      await loadGame(activeGame.id);
    } finally {
      setIsRefreshing(false);
    }
  };

  const handleEnterDraw = async () => {
    if (isInvalidEntry || !walletAddress || !activeGame || isEntering || isRefreshing) {
      return;
    }

    setIsEntering(true);
    setError("");
    setSuccess("");

    try {
      const payload = await joinLotteryGame({
        apiBaseUrl,
        token,
        gameId: activeGame.id,
        payload: {
          wallet_address: walletAddress,
          network: "base",
        },
      });

      const txHash = payload?.data?.entry_tx_hash;
      setSuccess(txHash ? `Entry submitted: ${shortenHash(txHash)}` : "Entry submitted for blockchain confirmation.");
      await loadGame(activeGame.id);
    } catch (submitError) {
      setError(submitError.message || "Unable to submit lottery entry.");
    } finally {
      setIsEntering(false);
    }
  };

  const handleCopyHash = async () => {
    if (!payoutTxHash) {
      setCopied(false);
      return;
    }

    try {
      await navigator.clipboard.writeText(payoutTxHash);
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

          <h1 className="mt-6 font-['Sora'] text-4xl font-semibold tracking-tight text-auric-300 sm:text-5xl">
            {activeGame?.name || "GameFi Lottery"}
          </h1>
          <p className="mt-3 text-base text-violet-200/70 sm:text-lg">{activeGame?.contract_address || FALLBACK_CONTRACT_ADDRESS}</p>
        </header>

        <div className="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6">
          <StatCard label="Participants" value={loading ? "..." : activeGame?.current_players ?? 0} />
          <StatCard label="Ether to Win" value={loading ? "..." : activeGame?.jackpot_amount_eth ?? "0.00000000"} />
        </div>

        <div className="mt-10">
          <label htmlFor="entryAmount" className="block text-2xl font-semibold text-violet-50">
            Enter Lottery
          </label>

          <p className="mt-2 text-sm text-violet-200/70">
            Submit your entry through ExaEarn. The platform validates the request, broadcasts the contract transaction, and tracks confirmation.
          </p>

          <div className="game-input-wrap mt-4 flex items-center rounded-2xl px-4 py-3 sm:px-5">
            <input
              id="entryAmount"
              type="number"
              min="0"
              step="0.001"
              value={entryEth}
              onChange={(event) => setEntryEth(event.target.value)}
              readOnly
              className="w-full bg-transparent text-2xl font-medium text-violet-50 outline-none placeholder:text-violet-200/45"
              placeholder="0.01"
              aria-label="Entry Ether amount"
            />
            <div className="ml-3 flex h-10 w-10 items-center justify-center rounded-full border border-auric-400/40 bg-cosmic-900/70 text-auric-400">
              <img src={ethereumLogo} alt="Ethereum" className="h-5 w-5 object-contain" />
            </div>
          </div>

          <div className="mt-4 space-y-3">
            <input
              type="text"
              value={walletAddress}
              onChange={(event) => setWalletAddress(event.target.value)}
              className="w-full rounded-2xl border border-violet-300/15 bg-cosmic-950/80 px-4 py-3 text-base text-violet-50 outline-none placeholder:text-violet-200/45"
              placeholder={user?.wallet_address || "Wallet address"}
              aria-label="Wallet address"
            />
          </div>

          {error ? <p className="mt-4 text-sm text-rose-300">{error}</p> : null}
          {success ? <p className="mt-4 text-sm text-emerald-300">{success}</p> : null}

          <div className="mt-8 space-y-3">
            <ActionButton
              isLoading={isEntering}
              loadingText="Entering..."
              disabled={isInvalidEntry || isRefreshing || !walletAddress || !activeGame}
              onClick={handleEnterDraw}
            >
              Enter Lottery
            </ActionButton>
            <ActionButton isLoading={isRefreshing} loadingText="Refreshing..." disabled={isEntering || !activeGame} onClick={handleRefreshResult}>
              <Coins className="h-5 w-5" aria-hidden="true" />
              Refresh Result
            </ActionButton>
          </div>
        </div>

        <section className="winner-proof-panel mt-10 rounded-3xl p-4 sm:p-6">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="inline-flex items-center gap-2 font-['Sora'] text-xl font-semibold text-violet-50">
              <ShieldCheck className="h-5 w-5 text-auric-300" aria-hidden="true" />
              Latest Winner Proof
            </h2>
            <span className="winner-proof-pill">{latestResult ? "Verified Payout" : "Awaiting Draw"}</span>
          </div>

          <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div className="winner-proof-row">
              <span className="winner-proof-label">Winner Wallet</span>
              <span className="winner-proof-value">{latestResult ? shortenWallet(latestResult.winner_wallet) : "Pending"}</span>
            </div>
            <div className="winner-proof-row">
              <span className="winner-proof-label">Prize Amount</span>
              <span className="winner-proof-value text-auric-300">{latestResult ? `${latestResult.jackpot_amount_eth} ETH` : "Pending"}</span>
            </div>
            <div className="winner-proof-row sm:col-span-2">
              <span className="winner-proof-label">Payout Transaction</span>
              <div className="flex items-center gap-2">
                <span className="winner-proof-value">{payoutTxHash ? shortenHash(payoutTxHash) : "Pending"}</span>
                <button
                  type="button"
                  onClick={handleCopyHash}
                  className="winner-proof-copy"
                  aria-label="Copy payout transaction hash"
                  disabled={!payoutTxHash}
                >
                  {copied ? <Check className="h-3.5 w-3.5" aria-hidden="true" /> : <Copy className="h-3.5 w-3.5" aria-hidden="true" />}
                  {copied ? "Copied" : "Copy"}
                </button>
              </div>
            </div>
            <div className="winner-proof-row sm:col-span-2">
              <span className="winner-proof-label">Timestamp</span>
              <span className="winner-proof-value">{latestResult ? formatDrawTime(latestResult.draw_time) : "Pending"}</span>
            </div>
          </div>

          <a
            href={payoutLink}
            target="_blank"
            rel="noreferrer"
            className={`winner-proof-link mt-4 inline-flex items-center gap-2 text-sm ${!payoutTxHash ? "pointer-events-none opacity-50" : ""}`}
          >
            View on Blockchain
            <ExternalLink className="h-4 w-4" aria-hidden="true" />
          </a>
        </section>
      </section>
    </main>
  );
}

export default Game;
