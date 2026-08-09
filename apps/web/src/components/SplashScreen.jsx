import React, { useEffect, useState } from "react";
import Image from "../assets/Image";

function SplashScreen() {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const frame = requestAnimationFrame(() => setIsVisible(true));
    return () => cancelAnimationFrame(frame);
  }, []);

  return (
    <div className="min-h-screen w-full bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <div
        className={`flex min-h-screen flex-col items-center justify-center px-4 transition-opacity duration-700 ${isVisible ? "opacity-100" : "opacity-0"}`}
      >
        <div className="relative flex h-32 w-32 items-center justify-center rounded-3xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-panel)]">
          <div className="absolute inset-0 rounded-3xl bg-gradient-to-br from-[var(--exa-gold-surface)] via-transparent to-[var(--exa-surface-hover)]" />
          <img src={Image.earn} alt="ExaEarn logo" className="relative z-10 h-16 w-16 object-contain" />
        </div>

        <h1 className="mt-6 font-['Sora'] text-4xl font-semibold tracking-tight text-[var(--exa-text-primary)]">ExaEarn</h1>
        <div className="mt-3 h-1 w-40 overflow-hidden rounded-full bg-[var(--exa-surface-hover)]">
          <div className="h-full w-full animate-pulse rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)]" />
        </div>
      </div>
    </div>
  );
}

export default SplashScreen;
