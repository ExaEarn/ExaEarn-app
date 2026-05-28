import { flexRender, getCoreRowModel, useReactTable } from "@tanstack/react-table";

export function PageShell({ title, eyebrow, description, actions, children }) {
  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          {eyebrow ? <p className="text-xs uppercase tracking-[0.32em] text-auric-300/80">{eyebrow}</p> : null}
          <h1 className="mt-2 font-['Sora'] text-3xl font-semibold text-white">{title}</h1>
          {description ? <p className="mt-2 max-w-3xl text-sm text-violet-100/70">{description}</p> : null}
        </div>
        {actions ? <div className="flex flex-wrap gap-3">{actions}</div> : null}
      </div>
      {children}
    </div>
  );
}

export function GlassPanel({ className = "", children }) {
  return (
    <section
      className={`rounded-[28px] border border-white/10 bg-[linear-gradient(180deg,rgba(18,13,34,0.92),rgba(10,8,22,0.86))] p-5 shadow-[0_25px_80px_rgba(10,8,22,0.45)] backdrop-blur-xl ${className}`}
    >
      {children}
    </section>
  );
}

export function Pill({ children, tone = "default" }) {
  const tones = {
    default: "border-white/10 bg-white/5 text-violet-100/75",
    success: "border-emerald-400/20 bg-emerald-400/10 text-emerald-200",
    warning: "border-amber-300/20 bg-amber-300/10 text-amber-200",
    danger: "border-rose-400/20 bg-rose-400/10 text-rose-200",
  };

  return <span className={`rounded-full border px-3 py-1 text-xs font-medium ${tones[tone]}`}>{children}</span>;
}

export function GradientButton({ children, className = "", ...props }) {
  return (
    <button
      type="button"
      className={`rounded-2xl border border-auric-300/40 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-2 text-sm font-semibold text-cosmic-950 shadow-[0_0_30px_rgba(234,185,95,0.25)] transition hover:scale-[1.01] ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}

export function OutlineButton({ children, className = "", ...props }) {
  return (
    <button
      type="button"
      className={`rounded-2xl border border-white/12 bg-white/5 px-4 py-2 text-sm font-medium text-violet-50 transition hover:border-auric-300/50 hover:bg-white/8 ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}

export function StatCard({ label, value, change }) {
  return (
    <GlassPanel className="min-h-[130px]">
      <p className="text-xs uppercase tracking-[0.26em] text-violet-100/45">{label}</p>
      <div className="mt-6 flex items-end justify-between gap-4">
        <h3 className="font-['Sora'] text-3xl font-semibold text-white">{value}</h3>
        <Pill tone="success">{change}</Pill>
      </div>
    </GlassPanel>
  );
}

export function MiniLineChart({ data = [], strokeClass = "stroke-auric-300" }) {
  const max = Math.max(...data, 1);
  const width = 320;
  const height = 120;
  const points = data
    .map((value, index) => {
      const x = (index / Math.max(data.length - 1, 1)) * width;
      const y = height - (value / max) * (height - 12) - 6;
      return `${x},${y}`;
    })
    .join(" ");

  return (
    <svg viewBox={`0 0 ${width} ${height}`} className="h-32 w-full">
      <defs>
        <linearGradient id="exa-line-fill" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor="rgba(249,226,173,0.42)" />
          <stop offset="100%" stopColor="rgba(163,119,255,0.02)" />
        </linearGradient>
      </defs>
      <path d={`M0 ${height} L${points} L${width} ${height} Z`} fill="url(#exa-line-fill)" opacity="0.75" />
      <polyline fill="none" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" points={points} className={strokeClass} />
    </svg>
  );
}

export function DataTable({ columns = [], rows = [] }) {
  const table = useReactTable({
    data: rows,
    columns,
    getCoreRowModel: getCoreRowModel(),
  });

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full border-separate border-spacing-y-3">
        <thead>
          {table.getHeaderGroups().map((headerGroup) => (
            <tr key={headerGroup.id}>
              {headerGroup.headers.map((header) => (
                <th key={header.id} className="px-4 text-left text-xs uppercase tracking-[0.22em] text-violet-100/40">
                  {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                </th>
              ))}
            </tr>
          ))}
        </thead>
        <tbody>
          {table.getRowModel().rows.map((row) => (
            <tr key={row.id} className="rounded-2xl bg-white/[0.03]">
              {row.getVisibleCells().map((cell) => (
                <td key={cell.id} className="rounded-2xl border-y border-white/6 px-4 py-4 text-sm text-violet-50/85">
                  {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
