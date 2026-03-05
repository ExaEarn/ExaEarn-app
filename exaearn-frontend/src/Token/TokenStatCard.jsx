function TokenStatCard({ label, value, hint }) {
  return (
    <article className="token-card rounded-2xl p-5 sm:p-6">
      <p className="text-sm font-medium tracking-wide text-violet-200/80">{label}</p>
      <p className="mt-3 text-3xl font-semibold text-auric-300 sm:text-4xl">{value}</p>
      {hint ? <p className="mt-2 text-sm text-violet-100/65">{hint}</p> : null}
    </article>
  );
}

export default TokenStatCard;
