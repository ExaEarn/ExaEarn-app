import { useEffect, useState } from "react";
import Image from "../assets/Image";

function SplashScreen() {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const frame = requestAnimationFrame(() => setIsVisible(true));
    return () => cancelAnimationFrame(frame);
  }, []);

  return (
    <div className="min-h-screen w-full bg-gradient-to-br from-black via-[#140a24] to-[#220c3d] text-white">
      <div
        className={`flex min-h-screen flex-col items-center justify-center px-4 transition-opacity duration-700 ${isVisible ? "opacity-100" : "opacity-0"}`}
      >
        <div className="relative flex h-32 w-32 items-center justify-center rounded-3xl border border-auric-300/60 bg-cosmic-900/70 shadow-[0_0_45px_rgba(168,85,247,0.35)]">
          <div className="absolute inset-0 rounded-3xl bg-gradient-to-br from-violet-400/15 via-transparent to-auric-300/20" />
          <img src={Image.earn} alt="ExaEarn logo" className="relative z-10 h-16 w-16 object-contain" />
        </div>

        <h1 className="mt-6 font-['Sora'] text-4xl font-semibold tracking-tight text-violet-50">ExaEarn</h1>
        <div className="mt-3 h-1 w-40 overflow-hidden rounded-full bg-violet-300/20">
          <div className="h-full w-full animate-pulse rounded-full bg-gradient-to-r from-violet-400 via-fuchsia-400 to-auric-300" />
        </div>
      </div>
    </div>
  );
}

export default SplashScreen;
