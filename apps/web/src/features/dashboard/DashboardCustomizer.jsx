import React, { useEffect, useState } from "react";
import { Check, RotateCcw, X } from "lucide-react";
import { dashboardExperienceRegistry } from "./dashboardRegistry";

export default function DashboardCustomizer({ open, preferences, busy, onClose, onSave, onReset }) {
  const [draft, setDraft] = useState(preferences);
  useEffect(() => setDraft(preferences), [preferences, open]);
  if (!open) return null;
  const selected = draft.selected_interests || [];
  const toggle = (key) => setDraft((current) => {
    const values = current.selected_interests || [];
    const next = values.includes(key) ? values.filter((item) => item !== key) : values.length < 3 ? [...values, key] : values;
    return { ...current, mode: next.length ? "personalized" : "all", selected_interests: next, primary_interest: next.includes(current.primary_interest) ? current.primary_interest : next[0] || null };
  });
  return (
    <div className="dashboard-customizer-backdrop" onClick={onClose}>
      <section className="dashboard-customizer" role="dialog" aria-modal="true" aria-labelledby="dashboard-customizer-title" onClick={(event) => event.stopPropagation()}>
        <header><div><span>For You</span><h2 id="dashboard-customizer-title">Personalize your dashboard</h2><p>Choose up to three experiences. Your primary choice gets the strongest placement.</p></div><button onClick={onClose} aria-label="Close"><X size={18} /></button></header>
        <div className="dashboard-choice-grid">{Object.entries(dashboardExperienceRegistry).map(([key, item]) => {
          const active = selected.includes(key);
          return <button key={key} className={active ? "active" : ""} onClick={() => toggle(key)} aria-pressed={active}><span>{active ? <Check size={14} /> : null}{item.label}</span><small>{item.description}</small>{item.availability === "partial" ? <em>Limited experience</em> : null}</button>;
        })}</div>
        {selected.length ? <div className="primary-choice"><label htmlFor="primary-interest">Primary experience</label><select id="primary-interest" value={draft.primary_interest || selected[0]} onChange={(event) => setDraft({ ...draft, primary_interest: event.target.value })}>{selected.map((key) => <option key={key} value={key}>{dashboardExperienceRegistry[key].label}</option>)}</select></div> : null}
        <footer><button className="reset" onClick={onReset} disabled={busy}><RotateCcw size={15} /> Reset to All ExaEarn</button><button className="save" onClick={() => onSave({ ...draft, onboarding_completed: true })} disabled={busy}>{busy ? "Saving…" : "Save dashboard"}</button></footer>
      </section>
    </div>
  );
}
