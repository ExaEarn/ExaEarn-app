import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  Bell,
  CircleHelp,
  Lock,
  LogOut,
  ShieldCheck,
  Smartphone,
  UserCheck,
  Users,
} from "lucide-react";

function ProfilePage({ onBack, user, onLogout, onOpenSettings, onOpenVerification, onOpenReferral, onOpenNotifications, onOpenHelpSupport, onOpenAbout, onOpenChangePassword, onOpenLoginDevices }) {
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(true);

  const displayName = user?.name?.trim() || "ExaEarn User";
  const initials = useMemo(() => {
    const parts = displayName.split(" ").filter(Boolean).slice(0, 2);
    if (!parts.length) return "EX";
    return parts.map((part) => part[0].toUpperCase()).join("");
  }, [displayName]);

  const actionItems = [
    { title: "Security Settings", icon: ShieldCheck, action: onOpenSettings },
    { title: "Verification (KYC)", icon: UserCheck, action: onOpenVerification },
    { title: "Referral Program", icon: Users, action: onOpenReferral },
    { title: "Notifications", icon: Bell, action: onOpenNotifications },
    { title: "Help & Support", icon: CircleHelp, action: onOpenHelpSupport },
    { title: "About ExaEarn", icon: BadgeCheck, action: onOpenAbout },
  ];

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#07050f] via-[#130a23] to-[#1d1134] text-violet-50">
      <div className="mx-auto w-full max-w-sm px-3 pb-8 pt-4 sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-4xl">
        <div className="rounded-3xl border border-violet-200/10 bg-cosmic-900/65 p-4 shadow-cosmic-card backdrop-blur-xl sm:p-5">
          <div className="mb-4 flex items-center gap-3 sm:mb-6">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-violet-200/20 bg-cosmic-800/70 p-2 text-violet-100 transition-all duration-300 hover:border-auric-300/65 hover:text-auric-300 active:scale-95"
              aria-label="Back to dashboard"
            >
              <ArrowLeft className="h-5 w-5" />
            </button>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-violet-200/60">Web3 Identity</p>
              <h1 className="font-['Sora'] text-xl font-semibold text-violet-50 sm:text-2xl">ExaEarn Profile</h1>
            </div>
          </div>

          <section className="relative mb-5 overflow-hidden rounded-3xl border border-violet-200/15 bg-gradient-to-br from-cosmic-800/95 via-cosmic-700/85 to-cosmic-900/95 p-4 shadow-cosmic-glow sm:p-6">
            <div className="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-auric-400/15 blur-3xl" />
            <div className="pointer-events-none absolute -left-8 bottom-0 h-28 w-28 rounded-full bg-cosmic-500/35 blur-3xl" />

            <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-4">
                <div className="flex h-16 w-16 items-center justify-center rounded-full border border-auric-300/45 bg-gradient-to-br from-cosmic-500 via-cosmic-600 to-auric-500 text-lg font-semibold text-white shadow-[0_0_24px_rgba(234,185,95,0.38)]">
                  {user?.picture ? (
                    <img src={user.picture} alt={`${displayName} avatar`} className="h-full w-full rounded-full object-cover" />
                  ) : (
                    initials
                  )}
                </div>
                <div>
                  <p className="font-['Sora'] text-lg font-semibold text-white">{displayName}</p>
                  <p className="text-sm text-violet-100/70">EXA...9K2D</p>
                  <div className="mt-1 inline-flex items-center gap-1 rounded-full border border-auric-300/45 bg-auric-400/10 px-2 py-0.5 text-xs font-semibold text-auric-300">
                    <BadgeCheck className="h-3.5 w-3.5" />
                    Verified
                  </div>
                </div>
              </div>
              <button
                type="button"
                className="rounded-xl border border-auric-300/70 bg-gradient-to-r from-cosmic-500 via-cosmic-400 to-auric-500 px-4 py-2 text-sm font-semibold text-cosmic-900 shadow-button-glow transition-all duration-300 hover:scale-[1.02] hover:brightness-110 active:scale-[0.98]"
              >
                Edit Profile
              </button>
            </div>
          </section>

          <section className="mb-5 rounded-2xl border border-violet-200/10 bg-cosmic-800/55 p-4 shadow-[0_12px_35px_rgba(4,4,10,0.55)] sm:p-5">
            <h2 className="font-['Sora'] text-sm font-semibold uppercase tracking-[0.12em] text-violet-100/70">Account Overview</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <InfoItem label="Email address" value={user?.email || "exa.user@exaearn.io"} />
              <InfoItem label="Phone number" value="+1 (415) *** **92" />
              <InfoItem label="Account Status" value="Active / Verified" />
              <InfoItem label="Member since" value="February 2025" />
            </div>
          </section>

          <section className="mb-5 rounded-2xl border border-violet-200/10 bg-cosmic-800/55 p-2 sm:p-3">
            {actionItems.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  type="button"
                  key={item.title}
                  onClick={item.action}
                  className="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition-all duration-300 hover:bg-cosmic-700/65 hover:shadow-[0_0_25px_rgba(127,70,212,0.24)] active:scale-[0.99]"
                >
                  <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg border border-cosmic-400/45 bg-cosmic-500/15 text-cosmic-400 transition-colors duration-300 group-hover:border-auric-300/55 group-hover:text-auric-300">
                      <Icon className="h-4 w-4" />
                    </span>
                    <span className="text-sm font-medium text-violet-100">{item.title}</span>
                  </div>
                  <ArrowRight className="h-4 w-4 text-violet-200/55 transition-all duration-300 group-hover:translate-x-1 group-hover:text-auric-300" />
                </button>
              );
            })}
          </section>

          <section className="mb-6 rounded-2xl border border-violet-200/10 bg-cosmic-800/55 p-4 sm:p-5">
            <h2 className="font-['Sora'] text-sm font-semibold uppercase tracking-[0.12em] text-violet-100/70">Security</h2>
            <div className="mt-4 space-y-3">
              <SecurityRow icon={Lock} label="Change Password" onClick={onOpenChangePassword} />
              <button
                type="button"
                onClick={() => setTwoFactorEnabled((prev) => !prev)}
                className="group flex w-full items-center justify-between rounded-xl border border-violet-200/10 bg-cosmic-900/55 px-3 py-3 transition-all duration-300 hover:border-cosmic-400/45 active:scale-[0.99]"
              >
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 items-center justify-center rounded-lg border border-cosmic-400/45 bg-cosmic-500/15 text-cosmic-400">
                    <ShieldCheck className="h-4 w-4" />
                  </span>
                  <span className="text-sm font-medium text-violet-100">Two-Factor Authentication</span>
                </div>
                <span
                  className={`relative h-6 w-11 rounded-full transition-colors duration-300 ${
                    twoFactorEnabled ? "bg-gradient-to-r from-cosmic-500 to-auric-500" : "bg-violet-200/20"
                  }`}
                >
                  <span
                    className={`absolute top-1 h-4 w-4 rounded-full bg-white shadow transition-all duration-300 ${
                      twoFactorEnabled ? "left-6" : "left-1"
                    }`}
                  />
                </span>
              </button>
              <SecurityRow icon={Smartphone} label="Login Devices" onClick={onOpenLoginDevices} />
            </div>
            <p className="mt-4 rounded-xl border border-auric-300/30 bg-auric-300/10 px-3 py-2 text-xs text-auric-200">
              Your account security is protected within the ExaEarn ecosystem.
            </p>
          </section>

          <button
            type="button"
            onClick={onLogout}
            className="w-full rounded-xl border border-red-400/35 bg-red-400/5 px-4 py-3 text-sm font-semibold text-red-200 transition-all duration-300 hover:border-red-300/60 hover:bg-red-400/10 hover:shadow-[0_0_20px_rgba(248,113,113,0.2)] active:scale-[0.99]"
          >
            <span className="inline-flex items-center gap-2">
              <LogOut className="h-4 w-4" />
              Logout
            </span>
          </button>
        </div>
      </div>
    </div>
  );
}

function InfoItem({ label, value }) {
  return (
    <div className="rounded-xl border border-violet-200/10 bg-cosmic-900/55 px-3 py-2.5">
      <p className="text-[11px] uppercase tracking-[0.12em] text-violet-100/55">{label}</p>
      <p className="mt-1 text-sm font-medium text-violet-50">{value}</p>
    </div>
  );
}

function SecurityRow({ icon: Icon, label, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="group flex w-full items-center justify-between rounded-xl border border-violet-200/10 bg-cosmic-900/55 px-3 py-3 transition-all duration-300 hover:border-cosmic-400/45 active:scale-[0.99]"
    >
      <div className="flex items-center gap-3">
        <span className="flex h-9 w-9 items-center justify-center rounded-lg border border-cosmic-400/45 bg-cosmic-500/15 text-cosmic-400">
          <Icon className="h-4 w-4" />
        </span>
        <span className="text-sm font-medium text-violet-100">{label}</span>
      </div>
      <ArrowRight className="h-4 w-4 text-violet-200/55 transition-all duration-300 group-hover:translate-x-1 group-hover:text-auric-300" />
    </button>
  );
}

export default ProfilePage;
