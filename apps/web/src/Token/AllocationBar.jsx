function AllocationBar({ label, percentage, colorClass }) {
  return (
    <div className="rounded-xl border border-violet-300/20 bg-cosmic-900/55 p-4">
      <div className="mb-3 flex items-center justify-between gap-2">
        <p className="text-sm font-medium text-violet-100 sm:text-base">{label}</p>
        <p className="text-base font-semibold text-auric-300 sm:text-lg">{percentage}%</p>
      </div>
      <div className="h-2.5 overflow-hidden rounded-full bg-violet-950/80">
        <div
          className={`allocation-fill h-full rounded-full ${colorClass}`}
          style={{ width: `${percentage}%` }}
          role="progressbar"
          aria-valuenow={percentage}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label={`${label} allocation`}
        />
      </div>
    </div>
  );
}

export default AllocationBar;
