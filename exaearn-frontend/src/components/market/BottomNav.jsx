import { BarChart3, Gem, Handshake, Home, Wallet } from "lucide-react";

const navItems = [
  { id: "home", label: "Home", icon: Home },
  { id: "markets", label: "Markets", icon: BarChart3, active: true },
  { id: "trade", label: "Trade", icon: Gem },
  { id: "futures", label: "Futures", icon: Handshake },
  { id: "assets", label: "Assets", icon: Wallet },
];

function BottomNav({ onHome, onTrade, onFutures }) {
  return (
    <nav className="border-t border-white/10 bg-[#0f1720]/95 px-2 py-2 backdrop-blur">
      <div className="grid grid-cols-5 gap-1">
        {navItems.map((item) => {
          const Icon = item.icon;
          return (
            <button
              key={item.id}
              type="button"
              onClick={
                item.id === "home"
                  ? onHome
                  : item.id === "trade"
                  ? onTrade
                  : item.id === "futures"
                  ? onFutures
                  : undefined
              }
              className="flex flex-col items-center gap-1 rounded-lg py-1.5"
              aria-label={`Open ${item.label}`}
            >
              <span
                className={`inline-flex h-7 w-7 items-center justify-center rounded-full ${
                  item.active
                    ? "bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white shadow-[0_0_18px_rgba(255,185,0,0.35)]"
                    : "bg-[#111d2b] text-[#9aa4b2]"
                }`}
              >
                <Icon className="h-4 w-4" />
              </span>
              <span className={`text-[10px] font-medium ${item.active ? "text-white" : "text-[#9aa4b2]"}`}>{item.label}</span>
            </button>
          );
        })}
      </div>
    </nav>
  );
}

export default BottomNav;
