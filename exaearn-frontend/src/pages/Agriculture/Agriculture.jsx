import { ArrowLeft, LandPlot } from "lucide-react";
import LandCard from "./components/LandCard";
import LeasePanel from "./components/LeasePanel";
import logo from "../../assets/images/exaearn-logo.png";

const parcelData = [
  {
    id: "parcel-1",
    name: "Green Valley Farm",
    size: "10 Acres",
    location: "California",
    availability: 65,
    theme: "emerald",
  },
  {
    id: "parcel-2",
    name: "Sunrise Field",
    size: "8 Acres",
    location: "Texas",
    availability: 40,
    theme: "gold",
  },
  {
    id: "parcel-3",
    name: "Harvest Plains",
    size: "15 Acres",
    location: "Iowa",
    availability: 75,
    theme: "violet",
  },
  {
    id: "parcel-4",
    name: "Cedar Ridge",
    size: "12 Acres",
    location: "Colorado",
    availability: 55,
    theme: "emerald",
  },
];

const feeCards = [
  {
    label: "Tokenization Fee",
    value: "2%",
    detail: "NFT minting, compliance checks, and registry onboarding.",
  },
  {
    label: "Transfer Fee",
    value: "1.5%",
    detail: "Secondary trades routed to the protocol treasury.",
  },
  {
    label: "Leasing Subscription",
    value: "Monthly Plan",
    detail: "Subscription access for farmer leasing and support.",
  },
];

function Agriculture({ onBack, onOpenSubscribe, onOpenAcquireShare }) {
  return (
    <div className="min-h-screen text-white exa-bg app-shell">
      <div className="container w-full max-w-sm px-3 pt-4 pb-6 mx-auto sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-5xl xl:max-w-6xl">
        <div className="p-4 shadow-xl glass-card rounded-3xl sm:p-6">
          <header className="mb-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70">
                  <img src={logo} alt="ExaEarn" className="h-6 w-6 object-contain" />
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.25em] text-auric-300/70">ExaEarn</p>
                  <p className="text-sm text-violet-100/70">Agriculture</p>
                </div>
              </div>
              {onBack ? (
                <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  Back
                </button>
              ) : null}
            </div>

            <div className="mt-5 rounded-3xl border border-violet-300/20 bg-gradient-to-br from-cosmic-900/80 via-cosmic-800/70 to-cosmic-900/90 p-5 shadow-cosmic-glow">
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70 text-auric-300">
                  <LandPlot className="h-5 w-5" aria-hidden="true" />
                </div>
                <div>
                  <h1 className="font-['Sora'] text-3xl font-semibold text-violet-50 sm:text-4xl">
                    NFT & Tokenized
                    <span className="text-auric-300"> Land Ownership</span>
                  </h1>
                  <p className="mt-3 text-sm leading-relaxed text-violet-100/75 sm:text-base">
                    Secure blockchain land registry and subscription investment for agriculture.
                  </p>
                  <button
                    type="button"
                    onClick={onOpenSubscribe}
                    className="mt-4 rounded-xl border border-auric-300/75 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-2 text-xs font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow"
                  >
                    Subscribe Now
                  </button>
                </div>
              </div>
            </div>
          </header>

          <section className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-2xl border border-violet-300/20 bg-cosmic-900/65 p-4">
              <p className="text-sm font-semibold text-violet-50">Land Disputes Solved</p>
              <p className="mt-2 text-xs text-violet-100/70">
                Blockchain-based land titles to prevent conflicts.
              </p>
            </div>
            <div className="rounded-2xl border border-violet-300/20 bg-cosmic-900/65 p-4">
              <p className="text-sm font-semibold text-violet-50">Invest in Farmland</p>
              <p className="mt-2 text-xs text-violet-100/70">Own fractional shares & lease to farmers.</p>
            </div>
          </section>

          <section className="mt-6">
            <div className="flex items-center justify-between">
              <h2 className="font-['Sora'] text-lg font-semibold text-violet-50">Available Land Parcels</h2>
              <div className="flex items-center gap-1 text-auric-300">
                <span className="h-1.5 w-1.5 rounded-full bg-auric-300" />
                <span className="h-1.5 w-1.5 rounded-full bg-auric-300/40" />
              </div>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
              {parcelData.map((parcel) => (
                <LandCard key={parcel.id} parcel={parcel} onAcquireShare={onOpenAcquireShare} />
              ))}
            </div>
          </section>

          <section className="mt-6">
            <LeasePanel onSubscribe={onOpenSubscribe} />
          </section>

          <section className="mt-6">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-violet-300/20 bg-cosmic-900/65 px-4 py-3 text-xs text-violet-100/70">
              {feeCards.map((fee, index) => (
                <div key={fee.label} className="flex items-center gap-2">
                  <span className="font-semibold text-violet-50">{fee.label}</span>
                  <span className="text-auric-300">{fee.value}</span>
                  {index < feeCards.length - 1 ? <span className="text-violet-100/30">|</span> : null}
                </div>
              ))}
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

export default Agriculture;
