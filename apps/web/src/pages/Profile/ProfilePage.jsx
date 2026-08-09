import { useState } from "react";
import ProfileIdentity from '../../components/profile/ProfileIdentity.jsx';

import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  Bell,
  CircleHelp,
  FileText,
  Lock,
  LogOut,
  ShieldCheck,
  Smartphone,
  UserCheck,
  Users,
} from "lucide-react";

function ProfilePage({ onBack, user, onLogout, onOpenSettings, onOpenVerification, onOpenProfileAppearance, onOpenReferral, onOpenNotifications, onOpenHelpSupport, onOpenAbout, onOpenChangePassword, onOpenLoginDevices, onOpenActivityLogs }) {
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(true);

  const displayName = user?.name?.trim() || "ExaEarn User";

  const actionItems = [
    { title: "Profile Appearance", icon: UserCheck, action: onOpenProfileAppearance },
    { title: "Security Settings", icon: ShieldCheck, action: onOpenSettings },
    { title: "Verification (KYC)", icon: UserCheck, action: onOpenVerification },
    { title: "Referral Program", icon: Users, action: onOpenReferral },
    { title: "Notifications", icon: Bell, action: onOpenNotifications },
    { title: "Help & Support", icon: CircleHelp, action: onOpenHelpSupport },
    { title: "About ExaEarn", icon: BadgeCheck, action: onOpenAbout },
  ];

  return (
    <div className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <div className="mx-auto w-full max-w-sm px-3 pb-8 pt-4 sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-4xl">
        <div className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur-xl sm:p-5">
          <div className="mb-4 flex items-center gap-3 sm:mb-6">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] p-2 text-[var(--exa-text-secondary)] transition-all duration-300 hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)] active:scale-95"
              aria-label="Back to dashboard"
            >
              <ArrowLeft className="h-5 w-5" />
            </button>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-[var(--exa-text-muted)]">Web3 Identity</p>
              <h1 className="font-['Sora'] text-xl font-semibold text-[var(--exa-text-primary)] sm:text-2xl">ExaEarn Profile</h1>
            </div>
          </div>

          <section className="relative mb-5 overflow-hidden rounded-3xl border border-[var(--exa-border)] bg-gradient-to-br from-[var(--exa-surface-elevated)] via-[var(--exa-surface)] to-[var(--exa-bg-tertiary)] p-4 shadow-[var(--exa-shadow-panel)] sm:p-6">
            <div className="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-[var(--exa-gold-surface)] blur-3xl" />
            <div className="pointer-events-none absolute -left-8 bottom-0 h-28 w-28 rounded-full bg-[var(--exa-gold-surface)] blur-3xl" />

            <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-4">
                <ProfileIdentity user={user} size="lg" alt={`${displayName} profile`} className="shadow-[0_0_24px_rgba(234,185,95,0.28)]" />
                <div>
                  <p className="font-['Sora'] text-lg font-semibold text-white">{displayName}</p>
                  <p className="text-sm text-[var(--exa-text-secondary)]">{user?.unique_user_id || "UID pending"}</p>
                  <div className="mt-1 inline-flex items-center gap-1 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2 py-0.5 text-xs font-semibold text-[var(--exa-gold-light)]">
                    <BadgeCheck className="h-3.5 w-3.5" />
                    KYC Level {user?.verification?.kyc_level ?? user?.kyc_level ?? 0}
                  </div>
                </div>
              </div>
              <button
                type="button"
                onClick={onOpenProfileAppearance}
                className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition-all duration-300 hover:scale-[1.02] hover:brightness-110 active:scale-[0.98]"
              >
                Profile Appearance
              </button>
            </div>
          </section>

          <section className="mb-5 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 shadow-[0_12px_35px_rgba(4,4,10,0.55)] sm:p-5">
            <h2 className="font-['Sora'] text-sm font-semibold uppercase tracking-[0.12em] text-[var(--exa-text-secondary)]">Account Overview</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <InfoItem label="Email address" value={user?.email || "exa.user@exaearn.io"} />
              <InfoItem label="Phone number" value="+1 (415) *** **92" />
              <InfoItem label="Account Status" value="Active / Verified" />
              <InfoItem label="Member since" value="February 2025" />
            </div>
          </section>

          <section className="mb-5 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-2 sm:p-3">
            {actionItems.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  type="button"
                  key={item.title}
                  onClick={item.action}
                  className="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition-all duration-300 hover:bg-[var(--exa-surface-hover)] hover:shadow-[var(--exa-shadow-soft)] active:scale-[0.99]"
                >
                  <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)] transition-colors duration-300 group-hover:border-[var(--exa-border-active)] group-hover:text-[var(--exa-gold-light)]">
                      <Icon className="h-4 w-4" />
                    </span>
                    <span className="text-sm font-medium text-[var(--exa-text-secondary)]">{item.title}</span>
                  </div>
                  <ArrowRight className="h-4 w-4 text-[var(--exa-text-muted)] transition-all duration-300 group-hover:translate-x-1 group-hover:text-[var(--exa-gold-light)]" />
                </button>
              );
            })}
          </section>

          <section className="mb-6 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 sm:p-5">
            <h2 className="font-['Sora'] text-sm font-semibold uppercase tracking-[0.12em] text-[var(--exa-text-secondary)]">Security</h2>
            <div className="mt-4 space-y-3">
              <SecurityRow icon={Lock} label="Change Password" onClick={onOpenChangePassword} />
              <SecurityRow icon={Smartphone} label="Login Devices" onClick={onOpenLoginDevices} />
              <SecurityRow icon={FileText} label="Activity Log" onClick={onOpenActivityLogs} />
              <button
                type="button"
                onClick={() => setTwoFactorEnabled((prev) => !prev)}
                className="group flex w-full items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 py-3 transition-all duration-300 hover:border-[var(--exa-border)] active:scale-[0.99]"
              >
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]">
                    <ShieldCheck className="h-4 w-4" />
                  </span>
                  <span className="text-sm font-medium text-[var(--exa-text-secondary)]">Two-Factor Authentication</span>
                </div>
                <span
                  className={`relative h-6 w-11 rounded-full transition-colors duration-300 ${
                    twoFactorEnabled ? "bg-gradient-to-r from-[var(--exa-gold-dark)] to-[var(--exa-gold)]" : "bg-[var(--exa-surface-hover)]"
                  }`}
                >
                  <span
                    className={`absolute top-1 h-4 w-4 rounded-full bg-white shadow transition-all duration-300 ${
                      twoFactorEnabled ? "left-6" : "left-1"
                    }`}
                  />
                </span>
              </button>
            </div>
            <p className="mt-4 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-3 py-2 text-xs text-[var(--exa-gold-light)]">
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
    <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 py-2.5">
      <p className="text-[11px] uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">{label}</p>
      <p className="mt-1 text-sm font-medium text-[var(--exa-text-primary)]">{value}</p>
    </div>
  );
}

function SecurityRow({ icon: Icon, label, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="group flex w-full items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 py-3 transition-all duration-300 hover:border-[var(--exa-border)] active:scale-[0.99]"
    >
      <div className="flex items-center gap-3">
        <span className="flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]">
          <Icon className="h-4 w-4" />
        </span>
        <span className="text-sm font-medium text-[var(--exa-text-secondary)]">{label}</span>
      </div>
      <ArrowRight className="h-4 w-4 text-[var(--exa-text-muted)] transition-all duration-300 group-hover:translate-x-1 group-hover:text-[var(--exa-gold-light)]" />
    </button>
  );
}

export default ProfilePage;
