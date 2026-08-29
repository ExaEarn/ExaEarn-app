import { useEffect, useState } from "react";
import { Check, RotateCcw, X } from "lucide-react";
import { goalOptions, interestOptions } from "../onboarding/experienceRecommendation.js";
import { normalizeHomePreferences } from "../home/homePersonalization";

export default function DashboardCustomizer({ open, preferences, busy, onClose, onSave, onReset }) {
  const [draft, setDraft] = useState(() => normalizeHomePreferences(preferences));
  useEffect(() => { if (open) setDraft(normalizeHomePreferences(preferences)); }, [open, preferences]);
  if (!open) return null;
  const interests = draft.interests || [];
  const toggleInterest = (key) => setDraft((current) => {
    const values = current.interests || [];
    const next = values.includes(key) ? values.filter((item) => item !== key) : values.length < 3 ? [...values, key] : values;
    return { ...current, interests: next };
  });

  return <div className="dashboard-customizer-backdrop" onClick={onClose}>
    <section className="dashboard-customizer" role="dialog" aria-modal="true" aria-labelledby="dashboard-customizer-title" onClick={(event) => event.stopPropagation()}>
      <header className="dashboard-customizer-header"><div><span>Personalization</span><h2 id="dashboard-customizer-title">Experience & Personalization</h2><p>Choose how dense Home feels and which products should be easier to discover. Financial eligibility and account safeguards never change.</p></div><button type="button" className="dashboard-customizer-close" onClick={onClose} aria-label="Close personalization"><X size={18} /></button></header>
      <div className="dashboard-customizer-body">
        <div className="dashboard-choice-summary"><span>Experience mode</span><div className="experience-mode-control">{["lite", "pro"].map((mode) => <button type="button" key={mode} className={draft.selected_mode === mode ? "active" : ""} onClick={() => setDraft({ ...draft, selected_mode: mode })}>{mode === "lite" ? "Lite" : "Pro"}</button>)}</div><small>{draft.selected_mode === "pro" ? "Markets and advanced tools first" : "Essential actions and guided discovery first"}</small></div>
        <div className="primary-choice"><label htmlFor="primary-goal">Main goal</label><select id="primary-goal" value={draft.primary_goal || "explore"} onChange={(event) => setDraft({ ...draft, primary_goal: event.target.value })}>{goalOptions.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select></div>
        <div><p className="dashboard-customizer-label">Interests <small>{interests.length}/3</small></p><div className="dashboard-choice-grid">{interestOptions.map((item) => { const active = interests.includes(item.id); return <button key={item.id} type="button" className={active ? "active" : ""} onClick={() => toggleInterest(item.id)} aria-pressed={active}><span>{active ? <Check size={14} /> : null}{item.label}</span></button>; })}</div></div>
      </div>
      <footer className="dashboard-customizer-footer"><button type="button" className="reset" onClick={onReset} disabled={busy}><RotateCcw size={15} />Reset</button><button type="button" className="save" onClick={() => onSave({ ...draft, mode: "personalized", onboarding_completed: true, onboarding_version: 4 })} disabled={busy}>{busy ? "Saving..." : "Save changes"}</button></footer>
    </section>
  </div>;
}
