import { BadgeCheck, FileSignature, Hourglass } from "lucide-react";

const steps = [
  { label: "Trial Request", status: "Completed" },
  { label: "Contract Status", status: "In Review" },
  { label: "NFT Contract Minted", status: "Pending" },
  { label: "Offer Pending", status: "Pending" },
  { label: "Offer Accepted", status: "Pending" },
];

function ContractPanel({ onOpenInitiateContract }) {
  return (
    <section className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Smart Contract Trial</p>
          <h2 className="mt-1 text-lg font-semibold text-violet-50">Trial & Contract Flow</h2>
        </div>
        <div className="flex h-9 w-9 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70 text-auric-300">
          <FileSignature className="h-4 w-4" aria-hidden="true" />
        </div>
      </div>

      <div className="mt-4 space-y-2">
        {steps.map((step) => (
          <div key={step.label} className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs">
            <span className="text-violet-100/70">{step.label}</span>
            <span className="inline-flex items-center gap-1 text-auric-300">
              {step.status === "Completed" ? <BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" /> : <Hourglass className="h-3.5 w-3.5" aria-hidden="true" />}
              {step.status}
            </span>
          </div>
        ))}
      </div>

      <button
        type="button"
        onClick={onOpenInitiateContract}
        className="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-3 text-xs font-semibold text-[#0B0B0B] transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow"
      >
        Initiate Trial Contract
      </button>
    </section>
  );
}

export default ContractPanel;
