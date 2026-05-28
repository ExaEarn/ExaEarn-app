function SkeletonRow() {
  return (
    <div className="mx-4 mt-2 rounded-2xl border border-white/10 bg-[#0f1720] p-3">
      <div className="animate-pulse">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-full bg-white/10" />
            <div>
              <div className="h-3 w-24 rounded bg-white/10" />
              <div className="mt-2 h-2.5 w-16 rounded bg-white/10" />
            </div>
          </div>
          <div className="h-5 w-16 rounded-full bg-white/10" />
        </div>
        <div className="mt-3 flex items-center justify-between">
          <div className="h-3 w-24 rounded bg-white/10" />
          <div className="h-3 w-20 rounded bg-white/10" />
        </div>
      </div>
    </div>
  );
}

export default SkeletonRow;
