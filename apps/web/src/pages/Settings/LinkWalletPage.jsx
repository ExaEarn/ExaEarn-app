import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Link2,
  Network,
  Pentagon,
  Send,
  ShieldCheck,
  Sparkles,
  Wallet,
  X,
} from "lucide-react";

const walletOptions = [
  { id: "phantom", name: "Phantom", description: "Solana wallet for fast Web3 access", icon: Sparkles, glow: "from-violet-500/35 to-indigo-400/20" },
  { id: "metamask", name: "MetaMask", description: "Ethereum and EVM network wallet", icon: Pentagon, glow: "from-orange-400/30 to-violet-500/20" },
  { id: "trust", name: "Trust Wallet", description: "Multi-chain wallet with mobile support", icon: ShieldCheck, glow: "from-cyan-400/35 to-blue-500/20" },
  { id: "walletconnect", name: "WalletConnect", description: "Connect with secure QR protocol", icon: Network, glow: "from-blue-400/35 to-violet-500/20" },
  { id: "solflare", name: "Solflare", description: "Solana-native wallet for DeFi and NFTs", icon: Send, glow: "from-fuchsia-400/30 to-cyan-400/20" },
  { id: "other", name: "Other Wallet", description: "Link another compatible Web3 wallet", icon: Wallet, glow: "from-violet-400/30 to-cyan-400/20" },
];

const storageKey = "exaearn-linked-wallet";

function LinkWalletPage({ onBack }) {
  const [selectedWallet, setSelectedWallet] = useState("metamask");
  const [connectedWallet, setConnectedWallet] = useState(() => {
    try {
      const raw = localStorage.getItem(storageKey);
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  });
  const [showConnectModal, setShowConnectModal] = useState(false);
  const [showManualModal, setShowManualModal] = useState(false);
  const [manualAddress, setManualAddress] = useState("");
  const [connecting, setConnecting] = useState(false);
  const [toast, setToast] = useState("");

  const selectedWalletMeta = useMemo(
    () => walletOptions.find((wallet) => wallet.id === selectedWallet) || walletOptions[0],
    [selectedWallet]
  );

  const showToast = (message) => {
    setToast(message);
    setTimeout(() => setToast(""), 2400);
  };

  const connectSelectedWallet = async () => {
    if (connecting) return;
    setConnecting(true);
    await new Promise((resolve) => setTimeout(resolve, 900));
    const payload = {
      id: selectedWalletMeta.id,
      name: selectedWalletMeta.name,
      address: "0x4a9b...0c51",
    };
    setConnectedWallet(payload);
    localStorage.setItem(storageKey, JSON.stringify(payload));
    setShowConnectModal(false);
    setConnecting(false);
    showToast("Wallet connected successfully.");
  };

  const verifyManualAddress = async () => {
    const trimmed = manualAddress.trim();
    if (trimmed.length < 10 || connecting) return;
    setConnecting(true);
    await new Promise((resolve) => setTimeout(resolve, 700));
    const payload = {
      id: "manual",
      name: "Manual Wallet",
      address: `${trimmed.slice(0, 6)}...${trimmed.slice(-4)}`,
    };
    setConnectedWallet(payload);
    localStorage.setItem(storageKey, JSON.stringify(payload));
    setShowManualModal(false);
    setManualAddress("");
    setConnecting(false);
    showToast("Wallet address verified and linked.");
  };

  return (
    <main className="relative min-h-screen overflow-hidden bg-gradient-to-br from-[#0B0F1F] via-[#140B2D] to-[#1C0F3F] text-white">
      <div className="pointer-events-none absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.18)_1px,transparent_0)] [background-size:22px_22px]" />
      <div className="pointer-events-none absolute inset-0 opacity-10 [background-image:linear-gradient(rgba(139,92,246,.20)_1px,transparent_1px),linear-gradient(90deg,rgba(56,189,248,.18)_1px,transparent_1px)] [background-size:76px_76px]" />
      <div className="pointer-events-none absolute -left-20 top-24 h-48 w-48 rounded-full bg-violet-500/25 blur-3xl" />
      <div className="pointer-events-none absolute -right-20 bottom-24 h-48 w-48 rounded-full bg-cyan-400/20 blur-3xl" />

      <header
        className="sticky top-0 z-30 border-b border-violet-300/20 bg-cosmic-900/80 backdrop-blur-xl"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto flex w-full max-w-3xl items-center justify-between px-4 pb-3 pt-3 sm:px-6">
          <button
            type="button"
            onClick={onBack}
            className="rounded-xl border border-violet-300/30 bg-cosmic-800/70 p-2 text-violet-100 shadow-[0_0_16px_rgba(139,92,246,0.35)] transition hover:border-cyan-300/55 hover:text-cyan-200"
            aria-label="Back"
          >
            <ArrowLeft className="h-5 w-5" />
          </button>
          <h1 className="text-lg font-semibold text-violet-50">Link Wallet</h1>
          <span className="rounded-xl border border-cyan-300/35 bg-cyan-300/10 p-2 text-cyan-200 shadow-[0_0_14px_rgba(34,211,238,0.3)]">
            <ShieldCheck className="h-4 w-4" />
          </span>
        </div>
        <div className="h-px w-full bg-gradient-to-r from-transparent via-violet-300/60 to-transparent" />
      </header>

      <section
        className="relative mx-auto w-full max-w-3xl px-4 pb-28 pt-4 sm:px-6"
        style={{ paddingBottom: "calc(108px + env(safe-area-inset-bottom))" }}
      >
        <article className="mb-4 rounded-3xl border border-violet-300/25 bg-cosmic-900/65 p-5 text-center shadow-cosmic-card backdrop-blur-xl">
          <div className="mx-auto mb-3 inline-flex h-14 w-14 items-center justify-center rounded-full border border-cyan-300/45 bg-cyan-300/10 shadow-[0_0_26px_rgba(34,211,238,0.3)]">
            <Link2 className="h-6 w-6 text-cyan-200" />
          </div>
          <h2 className="text-lg font-semibold text-violet-50">Connect Your Web3 Wallet</h2>
          <p className="mx-auto mt-2 max-w-md text-sm text-violet-100/70">
            Securely link your wallet to access EXA rewards, token transactions, and blockchain verification.
          </p>
        </article>

        <article className="mb-4 rounded-3xl border border-violet-300/25 bg-cosmic-900/65 p-4 shadow-cosmic-card">
          <h3 className="mb-3 text-sm font-semibold text-violet-50">Wallet Options</h3>
          <div className="space-y-2">
            {walletOptions.map((wallet) => {
              const active = wallet.id === selectedWallet;
              const Icon = wallet.icon;
              return (
                <button
                  key={wallet.id}
                  type="button"
                  onClick={() => setSelectedWallet(wallet.id)}
                  className={`group flex w-full items-center justify-between rounded-2xl border px-3 py-3 text-left transition ${
                    active
                      ? "border-cyan-300/45 bg-cosmic-800/90 shadow-[0_0_22px_rgba(34,211,238,0.22)]"
                      : "border-violet-300/20 bg-cosmic-800/65 hover:-translate-y-0.5 hover:border-violet-200/40"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className={`inline-flex h-10 w-10 items-center justify-center rounded-full border border-violet-200/30 bg-gradient-to-br ${wallet.glow} shadow-[inset_0_0_16px_rgba(34,211,238,0.18)]`}>
                      <Icon className="h-4.5 w-4.5 text-violet-50" />
                    </span>
                    <span>
                      <span className="block text-sm font-medium text-violet-100">{wallet.name}</span>
                      <span className="block text-xs text-violet-100/60">{wallet.description}</span>
                    </span>
                  </div>
                  <ArrowRight className={`h-4 w-4 ${active ? "text-cyan-200" : "text-violet-100/55"}`} />
                </button>
              );
            })}
          </div>
        </article>

        <button
          type="button"
          onClick={() => setShowConnectModal(true)}
          className="mb-3 w-full rounded-full bg-gradient-to-r from-cyan-500 via-blue-500 to-violet-500 py-3 text-sm font-semibold text-white shadow-[0_0_30px_rgba(34,211,238,0.32)] transition hover:scale-[1.01]"
        >
          Connect Wallet
        </button>

        <button
          type="button"
          onClick={() => setShowManualModal(true)}
          className="mb-4 w-full text-center text-sm font-medium text-cyan-200 underline underline-offset-4 transition hover:text-cyan-100"
        >
          Link via Wallet Address Instead
        </button>

        <article className="mb-4 rounded-2xl border border-violet-300/25 bg-cosmic-900/65 p-4">
          <ul className="space-y-2 text-sm text-violet-100/80">
            <li className="flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-cyan-200" />
              Blockchain Secured
            </li>
            <li className="flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-cyan-200" />
              No Private Keys Stored
            </li>
            <li className="flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-cyan-200" />
              Instant Verification
            </li>
          </ul>
        </article>

        {connectedWallet ? (
          <article className="rounded-2xl border border-emerald-300/35 bg-emerald-500/10 p-4 shadow-[0_0_24px_rgba(16,185,129,0.18)]">
            <p className="text-xs uppercase tracking-[0.12em] text-emerald-200/85">Connected Wallet</p>
            <div className="mt-2 flex items-center justify-between">
              <div>
                <p className="text-sm font-semibold text-violet-50">{connectedWallet.name}</p>
                <p className="text-xs text-violet-100/70">{connectedWallet.address}</p>
              </div>
              <span className="inline-flex items-center gap-1 rounded-full border border-emerald-300/35 bg-emerald-400/15 px-2 py-1 text-xs font-medium text-emerald-200">
                <CheckCircle2 className="h-3.5 w-3.5" />
                Connected
              </span>
            </div>
          </article>
        ) : null}
      </section>

      {showConnectModal ? (
        <div className="fixed inset-0 z-50 flex items-end bg-black/55 sm:items-center sm:justify-center">
          <button type="button" className="absolute inset-0" onClick={() => setShowConnectModal(false)} />
          <div className="relative w-full rounded-t-3xl border border-violet-300/30 bg-cosmic-900 p-5 shadow-[0_0_36px_rgba(34,211,238,0.2)] sm:max-w-sm sm:rounded-3xl">
            <h3 className="text-base font-semibold text-violet-50">Secure Connection</h3>
            <p className="mt-1 text-sm text-violet-100/70">
              Connect to <span className="text-cyan-200">{selectedWalletMeta.name}</span> and approve the session request.
            </p>
            <div className="mt-4 flex items-center justify-center">
              <span className="inline-flex h-12 w-12 animate-spin items-center justify-center rounded-full border-2 border-cyan-300/45 border-t-transparent text-cyan-200">
                <Network className="h-5 w-5" />
              </span>
            </div>
            <button
              type="button"
              onClick={connectSelectedWallet}
              disabled={connecting}
              className="mt-4 w-full rounded-xl bg-gradient-to-r from-cyan-500 to-violet-500 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
            >
              {connecting ? "Connecting..." : "Approve Connection"}
            </button>
          </div>
        </div>
      ) : null}

      {showManualModal ? (
        <div className="fixed inset-0 z-50 flex items-end bg-black/55 sm:items-center sm:justify-center">
          <button type="button" className="absolute inset-0" onClick={() => setShowManualModal(false)} />
          <div className="relative w-full rounded-t-3xl border border-violet-300/30 bg-cosmic-900 p-5 shadow-[0_0_36px_rgba(139,92,246,0.24)] sm:max-w-sm sm:rounded-3xl">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-base font-semibold text-violet-50">Link via Address</h3>
              <button type="button" onClick={() => setShowManualModal(false)} className="text-violet-100/70">
                <X className="h-4 w-4" />
              </button>
            </div>
            <label className="block">
              <span className="mb-1 block text-xs text-violet-100/65">Wallet Address</span>
              <input
                value={manualAddress}
                onChange={(event) => setManualAddress(event.target.value)}
                placeholder="0x..."
                className="h-11 w-full rounded-xl border border-violet-300/25 bg-cosmic-800 px-3 text-sm text-violet-100 outline-none transition focus:border-cyan-300/70 focus:shadow-[0_0_18px_rgba(34,211,238,0.2)]"
              />
            </label>
            <button
              type="button"
              onClick={verifyManualAddress}
              disabled={connecting || manualAddress.trim().length < 10}
              className="mt-4 w-full rounded-xl bg-gradient-to-r from-cyan-500 to-violet-500 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
            >
              {connecting ? "Verifying..." : "Verify Address"}
            </button>
          </div>
        </div>
      ) : null}

      {toast ? (
        <div className="fixed right-4 top-24 z-50 rounded-xl border border-emerald-300/40 bg-emerald-500/15 px-3 py-2 text-xs text-emerald-100 shadow-lg">
          {toast}
        </div>
      ) : null}
    </main>
  );
}

export default LinkWalletPage;
