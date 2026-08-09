import React from "react";
import { ArrowRight, Sparkles } from "lucide-react";
import { composeDashboard } from "./dashboardRegistry";

export default function DashboardComposer({ preferences, state, onOpen }) {
  const experiences = composeDashboard(preferences, state);
  if (!experiences.length) return null;
  return (
    <section className="personalized-dashboard" aria-label="Personalized dashboard experiences">
      <div className="personalized-heading"><span><Sparkles size={14} /> For You</span><h2>Organized around what matters to you</h2></div>
      <div className="personalized-grid">{experiences.map((item) => {
        const value = item.metric ? Number(item.state?.[item.metric] || 0) : null;
        return <article key={item.key} className={item.primary ? "primary" : ""}>
          <div><span>{item.primary ? "Primary experience" : item.availability === "partial" ? "Limited experience" : "Your interest"}</span><h3>{item.label}</h3><p>{item.description}</p></div>
          {value !== null ? <strong>{value}<small>{value ? item.metricLabel : `No ${item.metricLabel} yet`}</small></strong> : null}
          <button onClick={() => onOpen(item.route)}>Open {item.label}<ArrowRight size={15} /></button>
        </article>;
      })}</div>
    </section>
  );
}
