import React from "react";
import { ArrowRight, CheckCircle2, Sparkles } from "lucide-react";
import { composeDashboard } from "./dashboardRegistry";

export default function DashboardComposer({ preferences, state, onOpen }) {
  const experiences = composeDashboard(preferences, state);
  if (!experiences.length) return null;

  const [primaryExperience, ...secondaryExperiences] = experiences;
  const renderMetric = (item) => {
    if (!item.metric) return null;
    const value = Number(item.state?.[item.metric] || 0);
    return (
      <div className="personalized-metric" aria-label={`${item.label} ${item.metricLabel}`}>
        <strong>{value}</strong>
        <span>{value ? item.metricLabel : `No ${item.metricLabel} yet`}</span>
      </div>
    );
  };

  return (
    <section className="personalized-dashboard" aria-label="Personalized dashboard experiences">
      <div className="personalized-heading">
        <span><Sparkles size={14} /> Personalized</span>
        <h2>Your priority workspace</h2>
      </div>

      <div className="personalized-workspace">
        <article className="personalized-primary-card">
          <div className="personalized-primary-copy">
            <div className="personalized-status"><CheckCircle2 size={15} /> Primary focus</div>
            <h3>{primaryExperience.label}</h3>
            <p>{primaryExperience.description}</p>
          </div>
          <div className="personalized-primary-action">
            {renderMetric(primaryExperience)}
            <button type="button" onClick={() => onOpen(primaryExperience.route)}>
              Open <span>{primaryExperience.label}</span><ArrowRight size={15} />
            </button>
          </div>
        </article>

        {secondaryExperiences.length ? (
          <div className="personalized-shortcuts" aria-label="Other selected experiences">
            {secondaryExperiences.map((item) => (
              <button key={item.key} type="button" onClick={() => onOpen(item.route)}>
                <span>
                  <small>{item.availability === "partial" ? "Limited" : "Selected"}</small>
                  <strong>{item.label}</strong>
                </span>
                {renderMetric(item)}
                <ArrowRight size={15} />
              </button>
            ))}
          </div>
        ) : null}
      </div>
    </section>
  );
}
