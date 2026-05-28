export default function UserProfileForm({ profile, onSave }) {
  return (
    <section className="rounded-2xl border border-white/10 bg-[#0f172a] p-4">
      <h3 className="text-sm font-semibold text-white">User Profile</h3>
      <p className="mt-2 text-xs text-slate-300">Skill: {profile?.skill_level || "unknown"} | Risk: {profile?.risk_tolerance || "unknown"}</p>
      <button type="button" onClick={onSave} className="mt-3 rounded bg-[#D4AF37] px-3 py-2 text-xs font-semibold text-black">Sync Profile</button>
    </section>
  );
}
