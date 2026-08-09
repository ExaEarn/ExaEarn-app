import Image from "../../assets/Image";
import LanguageSwitcher from "../language/LanguageSwitcher.jsx";
import { useLanguage } from "../../context/LanguageContext.jsx";

export function ExaAuthShell({ title, subtitle, children }) {
  const { t } = useLanguage();

  return (
    <div className="min-h-screen w-full bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
        <div className="absolute right-4 top-4 z-20 sm:right-6 sm:top-6">
          <LanguageSwitcher compact />
        </div>
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_12%,rgba(214,178,75,0.13),transparent_30%),radial-gradient(circle_at_82%_4%,rgba(56,189,248,0.06),transparent_26%)]" />
        <div className="relative grid w-full max-w-5xl overflow-hidden rounded-[28px] border border-[var(--exa-border)] bg-[rgba(8,9,11,0.84)] shadow-[0_32px_90px_rgba(0,0,0,0.52)] backdrop-blur-xl lg:grid-cols-[0.95fr_1fr]">
          <aside className="hidden border-r border-[var(--exa-border-subtle)] bg-[linear-gradient(145deg,rgba(214,178,75,0.12),rgba(255,255,255,0.018)),var(--exa-bg-secondary)] p-8 lg:flex lg:flex-col lg:justify-between">
            <div>
              <div className="inline-flex h-14 w-14 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]">
                <img src={Image.earn} alt="ExaEarn logo" className="h-8 w-8 object-contain" />
              </div>
              <h2 className="mt-8 max-w-sm text-4xl font-semibold leading-tight text-white">{t("auth.authBrandTitle")}</h2>
              <p className="mt-4 max-w-sm text-sm leading-6 text-[var(--exa-text-muted)]">{t("auth.authBrandText")}</p>
            </div>
            <div className="grid gap-3 text-xs text-[var(--exa-text-muted)]">
              <div className="rounded-2xl border border-[var(--exa-border-subtle)] bg-white/[0.025] p-3">{t("auth.authTrustOne")}</div>
              <div className="rounded-2xl border border-[var(--exa-border-subtle)] bg-white/[0.025] p-3">{t("auth.authTrustTwo")}</div>
            </div>
          </aside>
          <main className="p-5 sm:p-8 lg:p-10">
            <div className="mb-7 flex flex-col items-center text-center lg:hidden">
              <div className="flex h-14 w-14 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]">
                <img src={Image.earn} alt="ExaEarn logo" className="h-8 w-8 object-contain" />
              </div>
            </div>
            <div className="mx-auto w-full max-w-md">
              <div className="mb-6 text-center lg:text-left">
                <h1 className="text-3xl font-semibold tracking-tight text-white">{title}</h1>
                {subtitle ? <p className="mt-2 text-sm leading-6 text-[var(--exa-text-muted)]">{subtitle}</p> : null}
              </div>
              {children}
            </div>
          </main>
        </div>
      </div>
    </div>
  );
}
