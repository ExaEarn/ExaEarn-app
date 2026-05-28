/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,ts,jsx,tsx}"],
  theme: {
    extend: {
      colors: {
        exa: {
          bg: "#0b0f18",
          surface: "#0f1720",
          "primary-start": "#6b2cff",
          "primary-end": "#ffb900",
          accent: "#ffb900",
          muted: "#9aa4b2",
          positive: "#18c06c",
          negative: "#ff5b6b",
        },
        cosmic: {
          950: "#07050f",
          900: "#0f0a1d",
          800: "#1a1230",
          700: "#2a1b46",
          600: "#4c2a7a",
          500: "#7f46d4",
          400: "#a377ff",
        },
        auric: {
          500: "#eab95f",
          400: "#f4cf7e",
          300: "#f9e2ad",
        },
      },
      boxShadow: {
        "cosmic-card": "0 18px 48px rgba(6, 4, 15, 0.65), inset 0 1px 0 rgba(255, 255, 255, 0.08)",
        "cosmic-glow": "0 0 32px rgba(127, 70, 212, 0.35), 0 0 58px rgba(234, 185, 95, 0.18)",
        "button-glow": "0 0 26px rgba(234, 185, 95, 0.46), 0 0 45px rgba(127, 70, 212, 0.32)",
      },
      keyframes: {
        "float-in": {
          "0%": { opacity: "0", transform: "translateY(16px) scale(0.97)" },
          "100%": { opacity: "1", transform: "translateY(0) scale(1)" },
        },
        "pulse-ring": {
          "0%, 100%": { boxShadow: "0 0 20px rgba(234,185,95,0.2), 0 0 30px rgba(127,70,212,0.2)" },
          "50%": { boxShadow: "0 0 28px rgba(234,185,95,0.35), 0 0 44px rgba(127,70,212,0.35)" },
        },
        spin: {
          to: { transform: "rotate(360deg)" },
        },
      },
      animation: {
        "float-in": "float-in 420ms ease-out",
        "pulse-ring": "pulse-ring 2.2s ease-in-out infinite",
        spin: "spin 0.9s linear infinite",
      },
    },
  },
  plugins: [],
};
