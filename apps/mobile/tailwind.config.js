/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./App.{js,jsx,ts,tsx}", "./src/**/*.{js,jsx,ts,tsx}"],
  presets: [require("nativewind/preset")],
  theme: {
    extend: {
      colors: {
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
      fontFamily: {
        space: ["SpaceGrotesk_400Regular"],
        sora: ["Sora_600SemiBold"],
      },
    },
  },
  plugins: [],
};
