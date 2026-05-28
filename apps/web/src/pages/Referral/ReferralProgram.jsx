import { useEffect, useMemo, useState } from "react";
import {
  CheckCircle2,
  Clock3,
  Copy,
  Gift,
  Link as LinkIcon,
  QrCode,
  Share2,
  ShieldCheck,
  Sparkles,
  Star,
  Trophy,
  UserPlus,
  Users,
} from "lucide-react";

const tierRows = [
  { name: "Tier 1", metric: "Trading Fees", rate: 20 },
  { name: "Tier 2", metric: "Staking Rewards", rate: 12 },
  { name: "Tier 3", metric: "Swap Fees", rate: 8 },
];

const referralSeed = Array.from({ length: 24 }).map((_, index) => ({
  id: `ref-${index + 1}`,
  user: `user${String(index + 1038).padStart(4, "0")}@mail.com`,
  status: index % 3 === 0 ? "Inactive" : "Active",
  joined: `2026-0${(index % 8) + 1}-${String((index % 27) + 1).padStart(2, "0")}`,
  reward: (42 + index * 7.4).toFixed(2),
}));

function ReferralProgram({ onBack, user }) {
  const [referralCode, setReferralCode] = useState("");
  const [copied, setCopied] = useState(false);
  const [visibleCount, setVisibleCount] = useState(8);
  const [totalReferrals, setTotalReferrals] = useState(124);
  const [totalRewards, setTotalRewards] = useState(9432.45);
  const [pendingRewards, setPendingRewards] = useState(284.38);
  const [currentTier] = useState("Tier 2");
  const [tierProgress, setTierProgress] = useState(0);

  const bonusDeadline = useMemo(() => {
    const deadline = new Date();
    deadline.setDate(deadline.getDate() + 6);
    deadline.setHours(deadline.getHours() + 9);
    return deadline;
  }, []);
  const [countdown, setCountdown] = useState("");

  useEffect(() => {
    const savedCode = localStorage.getItem("exaearn-referral-code");
    if (savedCode) {
      setReferralCode(savedCode);
      return;
    }

    const userSeed = user?.name?.replace(/\s+/g, "").slice(0, 4).toUpperCase() || "EXA";
    const randomPart = Math.random().toString(36).slice(2, 8).toUpperCase();
    const newCode = `${userSeed}${randomPart}`;
    setReferralCode(newCode);
    localStorage.setItem("exaearn-referral-code", newCode);
  }, [user]);

  useEffect(() => {
    const interval = setInterval(() => {
      setTotalReferrals((prev) => prev + (Math.random() > 0.85 ? 1 : 0));
      setTotalRewards((prev) => Number((prev + Math.random() * 0.35).toFixed(2)));
      setPendingRewards((prev) => Number(Math.max(0, prev + (Math.random() - 0.45) * 0.25).toFixed(2)));
    }, 4200);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    const target = 72;
    let progress = 0;
    const timer = setInterval(() => {
      progress += 3;
      setTierProgress(Math.min(target, progress));
      if (progress >= target) clearInterval(timer);
    }, 28);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    const timer = setInterval(() => {
      const now = new Date().getTime();
      const diff = bonusDeadline.getTime() - now;
      if (diff <= 0) {
        setCountdown("00d 00h 00m 00s");
        return;
      }
      const d = Math.floor(diff / (1000 * 60 * 60 * 24));
      const h = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const m = Math.floor((diff / (1000 * 60)) % 60);
      const s = Math.floor((diff / 1000) % 60);
      setCountdown(`${String(d).padStart(2, "0")}d ${String(h).padStart(2, "0")}h ${String(m).padStart(2, "0")}m ${String(s).padStart(2, "0")}s`);
    }, 1000);
    return () => clearInterval(timer);
  }, [bonusDeadline]);

  const referralLink = `https://app.exaearn.io/signup?ref=${referralCode}`;
  const visibleHistory = referralSeed.slice(0, visibleCount);

  const copyReferralLink = async () => {
    try {
      await navigator.clipboard.writeText(referralLink);
      setCopied(true);
      setTimeout(() => setCopied(false), 1800);
    } catch (error) {
      console.error("Copy failed", error);
    }
  };

  const shareTo = (platform) => {
    const text = encodeURIComponent(`Join me on ExaEarn and start earning rewards. Use my referral link: ${referralLink}`);
    const targetUrl =
      platform === "whatsapp"
        ? `https://wa.me/?text=${text}`
        : platform === "telegram"
        ? `https://t.me/share/url?url=${encodeURIComponent(referralLink)}&text=${text}`
        : `https://twitter.com/intent/tweet?text=${text}`;
    window.open(targetUrl, "_blank", "noopener,noreferrer");
  };

  return (
    <main className="min-h-screen bg-[#070B14] text-white">
      <section className="relative overflow-hidden border-b border-[#D4AF37]/20 bg-gradient-to-br from-[#131B2A] via-[#0E1524] to-[#0A0F1D] px-4 pb-6 pt-5 sm:px-6">
        <div className="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full bg-[#D4AF37]/18 blur-3xl" />
        <div className="mx-auto w-full max-w-6xl">
          <button
            type="button"
            onClick={onBack}
            className="mb-3 rounded-xl border border-white/15 bg-[#111827] px-3 py-1.5 text-sm text-[#E6EAF2] hover:border-[#D4AF37]/60"
          >
            Back
          </button>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h1 className="text-2xl font-semibold text-[#F8F1DE] sm:text-3xl">Referral Program</h1>
              <p className="mt-1 text-sm text-[#B8C0CF]">Earn rewards by inviting others to ExaEarn</p>
            </div>
            <div className="relative hidden h-14 w-14 items-center justify-center rounded-2xl border border-[#D4AF37]/35 bg-[#D4AF37]/12 text-[#D4AF37] sm:flex">
              <Sparkles className="h-6 w-6 animate-pulse" />
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto w-full max-w-6xl px-4 pb-28 pt-5 sm:px-6">
        <section className="rounded-2xl border border-white/10 bg-[#101827]/75 p-4 shadow-[0_16px_28px_rgba(0,0,0,0.3)] backdrop-blur">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard label="Total Referrals" value={`${totalReferrals}`} icon={Users} />
            <MetricCard label="Total Rewards Earned" value={`${totalRewards.toLocaleString()} EXA`} icon={Gift} accent />
            <MetricCard label="Pending Rewards" value={`${pendingRewards.toLocaleString()} EXA`} icon={Clock3} />
            <MetricCard label="Referral Tier Level" value={currentTier} icon={Trophy} accent />
          </div>
        </section>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Your Referral Link</h2>
          <div className="grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
            <div>
              <div className="rounded-xl border border-white/15 bg-[#0C1424] p-3">
                <p className="text-xs text-[#98A1B2]">Unique Link</p>
                <p className="mt-1 break-all text-sm text-[#E6EAF2]">{referralLink}</p>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    onClick={copyReferralLink}
                    className="inline-flex items-center gap-1 rounded-lg border border-white/15 bg-[#111827] px-3 py-1.5 text-xs"
                  >
                    <Copy className="h-3.5 w-3.5" />
                    {copied ? "Copied" : "Copy"}
                  </button>
                  <div className="rounded-lg border border-[#D4AF37]/35 bg-[#D4AF37]/10 px-3 py-1.5 text-xs text-[#F3D88F]">
                    Code: {referralCode}
                  </div>
                </div>
              </div>

              <div className="mt-3 flex flex-wrap gap-2">
                <ShareButton label="WhatsApp" onClick={() => shareTo("whatsapp")} />
                <ShareButton label="Telegram" onClick={() => shareTo("telegram")} />
                <ShareButton label="Twitter/X" onClick={() => shareTo("twitter")} />
                <button
                  type="button"
                  className="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] px-4 py-2 text-xs font-semibold text-[#111827]"
                >
                  <UserPlus className="h-3.5 w-3.5" />
                  Invite Now
                </button>
              </div>
            </div>

            <div className="rounded-xl border border-[#D4AF37]/25 bg-[#0C1424] p-3">
              <div className="mb-2 flex items-center gap-2 text-sm text-[#E6EAF2]">
                <QrCode className="h-4 w-4 text-[#D4AF37]" />
                QR Code
              </div>
              <PseudoQr value={referralLink} />
            </div>
          </div>
        </section>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Commission Structure</h2>
          <div className="overflow-hidden rounded-xl border border-white/10">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#0E1728] text-[#AEB7C7]">
                <tr>
                  <th className="px-3 py-2">Tier</th>
                  <th className="px-3 py-2">Source</th>
                  <th className="px-3 py-2">Rate</th>
                </tr>
              </thead>
              <tbody>
                {tierRows.map((row) => (
                  <tr key={row.name} className={`${row.name === currentTier ? "bg-[#D4AF37]/12" : "bg-[#0C1424]"} border-t border-white/10`}>
                    <td className="px-3 py-2 text-[#E6EAF2]">{row.name}</td>
                    <td className="px-3 py-2 text-[#AEB7C7]">{row.metric}</td>
                    <td className="px-3 py-2 text-[#F3D88F]">{row.rate}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mt-3 rounded-xl border border-white/10 bg-[#0C1424] p-3">
            <div className="mb-1 flex items-center justify-between text-xs text-[#AEB7C7]">
              <span>{currentTier} progress to Tier 3</span>
              <span>{tierProgress}%</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-[#1B2638]">
              <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] to-[#E7C96C] transition-all duration-500" style={{ width: `${tierProgress}%` }} />
            </div>
          </div>
        </section>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Referral History</h2>
          <div className="max-h-72 overflow-y-auto rounded-xl border border-white/10 bg-[#0C1424]">
            {visibleHistory.map((item) => (
              <article key={item.id} className="grid grid-cols-[1.35fr_0.8fr_0.9fr_0.7fr] gap-2 border-b border-white/8 px-3 py-2 text-xs last:border-b-0">
                <p className="truncate text-[#D7DDEA]">{item.user}</p>
                <p className={item.status === "Active" ? "text-[#86EFAC]" : "text-[#9CA3AF]"}>{item.status}</p>
                <p className="text-[#AEB7C7]">{item.joined}</p>
                <p className="text-right text-[#F3D88F]">{item.reward} EXA</p>
              </article>
            ))}
          </div>
          {visibleCount < referralSeed.length ? (
            <button
              type="button"
              onClick={() => setVisibleCount((prev) => Math.min(prev + 8, referralSeed.length))}
              className="mt-3 rounded-lg border border-white/15 bg-[#111827] px-3 py-1.5 text-xs text-[#D7DDEA]"
            >
              Load more
            </button>
          ) : null}
        </section>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">How It Works</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <StepCard icon={LinkIcon} title="Step 1" desc="Share your referral link" />
            <StepCard icon={UserPlus} title="Step 2" desc="Friend registers using your link" />
            <StepCard icon={CheckCircle2} title="Step 3" desc="Friend completes KYC" />
            <StepCard icon={Gift} title="Step 4" desc="You earn automated rewards" />
          </div>
        </section>

        <section className="mt-4 rounded-2xl border border-[#D4AF37]/35 bg-[#D4AF37]/12 p-4 shadow-[0_0_24px_rgba(212,175,55,0.22)]">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="inline-flex items-center gap-1 rounded-full border border-[#D4AF37]/45 bg-[#0F172A] px-2 py-0.5 text-[11px] text-[#F3D88F]">
                <Star className="h-3 w-3" />
                Limited-time bonus
              </p>
              <h3 className="mt-1 text-base font-semibold text-[#F8F1DE]">2x referral multiplier campaign</h3>
              <p className="text-xs text-[#D8C488]">Countdown: {countdown || "Loading..."}</p>
            </div>
            <div className="rounded-xl bg-[#0F172A] px-3 py-2 text-sm font-semibold text-[#F3D88F]">Multiplier: 2.0x</div>
          </div>
        </section>

        <section className="mt-4 space-y-2 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <p className="flex items-center gap-2 text-sm text-[#D7DDEA]">
            <ShieldCheck className="h-4 w-4 text-[#D4AF37]" />
            Referral rewards are automatically distributed via smart contract.
          </p>
          <p className="text-xs text-[#FCA5A5]">ExaEarn will never manually request payment for referral rewards.</p>
          <div className="text-xs text-[#9CA3AF]">
            Anti-fraud controls: self-referral prevention, referral tree tracking, and suspicious activity detection are active.
          </div>
        </section>
      </div>

      <div
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[#D4AF37]/20 bg-[#0A0F1D]/95 p-3 backdrop-blur md:hidden"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <button
          type="button"
          className="w-full rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] py-3 text-sm font-semibold text-[#111827] shadow-[0_10px_22px_rgba(212,175,55,0.28)]"
        >
          Invite Now
        </button>
      </div>
    </main>
  );
}

function MetricCard({ label, value, icon: Icon, accent = false }) {
  return (
    <article className="rounded-2xl border border-white/10 bg-[#0C1424] p-3">
      <div className="mb-2 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#D4AF37]/25 bg-[#D4AF37]/10 text-[#D4AF37]">
        <Icon className="h-4 w-4" />
      </div>
      <p className="text-[11px] uppercase tracking-[0.08em] text-[#98A1B2]">{label}</p>
      <p className={`mt-1 text-lg font-semibold ${accent ? "text-[#F3D88F]" : "text-[#E6EAF2]"}`}>{value}</p>
    </article>
  );
}

function ShareButton({ label, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="inline-flex items-center gap-1 rounded-lg border border-white/15 bg-[#111827] px-3 py-1.5 text-xs text-[#D7DDEA]"
    >
      <Share2 className="h-3.5 w-3.5 text-[#D4AF37]" />
      {label}
    </button>
  );
}

function StepCard({ icon: Icon, title, desc }) {
  return (
    <article className="rounded-2xl border border-white/10 bg-[#0C1424] p-3">
      <div className="mb-2 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#D4AF37]/25 bg-[#D4AF37]/10 text-[#D4AF37]">
        <Icon className="h-4 w-4" />
      </div>
      <p className="text-sm font-semibold text-[#E6EAF2]">{title}</p>
      <p className="mt-1 text-xs text-[#AEB7C7]">{desc}</p>
    </article>
  );
}

function PseudoQr({ value }) {
  const grid = 21;
  const seed = useMemo(() => {
    let hash = 0;
    for (let i = 0; i < value.length; i += 1) {
      hash = (hash << 5) - hash + value.charCodeAt(i);
      hash |= 0;
    }
    return Math.abs(hash);
  }, [value]);

  const matrix = useMemo(() => {
    return Array.from({ length: grid * grid }).map((_, idx) => {
      const x = idx % grid;
      const y = Math.floor(idx / grid);
      const corner = (x < 7 && y < 7) || (x > 13 && y < 7) || (x < 7 && y > 13);
      if (corner) {
        const frame = x % 7 === 0 || y % 7 === 0 || x % 7 === 6 || y % 7 === 6;
        const center = x % 7 >= 2 && x % 7 <= 4 && y % 7 >= 2 && y % 7 <= 4;
        return frame || center;
      }
      return ((seed + x * 17 + y * 31 + idx * 13) % 7) < 3;
    });
  }, [seed]);

  return (
    <div className="mx-auto grid w-[182px] grid-cols-[repeat(21,minmax(0,1fr))] gap-[2px] rounded-lg bg-white p-2">
      {matrix.map((on, idx) => (
        <span key={idx} className={`h-[6px] w-[6px] rounded-[1px] ${on ? "bg-black" : "bg-white"}`} />
      ))}
    </div>
  );
}

export default ReferralProgram;
