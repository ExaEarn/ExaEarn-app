import React, { useEffect, useMemo, useState } from "react";
import { Check, RotateCcw, X } from "lucide-react";
import { dashboardExperienceRegistry } from "./dashboardRegistry";

export default function DashboardCustomizer({ open, preferences, busy, onClose, onSave, onReset }) {
  const [draft, setDraft] = useState(preferences);

  useEffect(() => {
    if (open) setDraft(preferences);
  }, [preferences, open]);

  const selected = draft?.selected_interests || [];
  const selectedLabel = useMemo(() => {
    if (!selected.length) return "All ExaEarn";
    return selected.map((key) => dashboardExperienceRegistry[key]?.label).filter(Boolean).join(" • ");
  }, [selected]);

  if (!open) return null;

  const toggle = (key) => setDraft((current) => {
    const values = current?.selected_interests || [];
    const next = values.includes(key)
      ? values.filter((item) => item !== key)
      : values.length < 3
        ? [...values, key]
        : values;

    return {
      ...current,
      mode: next.length ? "personalized" : "all",
      selected_interests: next,
      primary_interest: next.includes(current?.primary_interest) ? current.primary_interest : next[0] || null,
    };
  });

  const save = () => {
    const next = {
      ...draft,
      mode: selected.length ? "personalized" : "all",
      primary_interest: selected.includes(draft?.primary_interest) ? draft.primary_interest : selected[0] || null,
      onboarding_completed: true,
    };
    onSave(next);
  };

  return (
    <div className="dashboard-customizer-backdrop" onClick={onClose}>
      <section className="dashboard-customizer" role="dialog" aria-modal="true" aria-labelledby="dashboard-customizer-title" onClick={(event) => event.stopPropagation()}>
        <header className="dashboard-customizer-header">
          <div>
            <span>Personalize</span>
            <h2 id="dashboard-customizer-title">Shape your ExaEarn dashboard</h2>
            <p>Choose up to three ecosystems. Your primary choice changes the dashboard layout itself.</p>
          </div>
          <button type="button" className="dashboard-customizer-close" onClick={onClose} aria-label="Close personalize dashboard">
            <X size={18} />
          </button>
        </header>

        <div className="dashboard-customizer-body">
          <div className="dashboard-choice-summary" aria-live="polite">
            <span>Current focus</span>
            <strong>{selectedLabel}</strong>
            <small>{selected.length}/3 selected</small>
          </div>

          <div className="dashboard-choice-grid">
            {Object.entries(dashboardExperienceRegistry).map(([key, item]) => {
              const active = selected.includes(key);
              const disabled = !active && selected.length >= 3;
              return (
                <button key={key} type="button" className={active ? "active" : ""} onClick={() => toggle(key)} aria-pressed={active} disabled={disabled}>
                  <span>{active ? <Check size={14} /> : null}{item.label}</span>
                  <small>{item.description}</small>
                  {item.availability === "partial" ? <em>Limited experience</em> : null}
                </button>
              );
            })}
          </div>

          {selected.length ? (
            <div className="primary-choice">
              <label htmlFor="primary-interest">Primary ecosystem</label>
              <select id="primary-interest" value={draft.primary_interest || selected[0]} onChange={(event) => setDraft({ ...draft, primary_interest: event.target.value })}>
                {selected.map((key) => <option key={key} value={key}>{dashboardExperienceRegistry[key].label}</option>)}
              </select>
            </div>
          ) : null}
        </div>

        <footer className="dashboard-customizer-footer">
          <button type="button" className="reset" onClick={onReset} disabled={busy}>
            <RotateCcw size={15} /> Reset
          </button>
          <button type="button" className="save" onClick={save} disabled={busy}>
            {busy ? "Saving..." : "Save dashboard"}
          </button>
        </footer>
      </section>
    </div>
  );
}
