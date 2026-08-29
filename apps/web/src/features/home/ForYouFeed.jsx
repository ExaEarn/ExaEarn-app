import { useEffect, useState } from "react";
import { ArrowLeft, Bookmark, ChevronRight, Compass, X } from "lucide-react";

export default function ForYouFeed({ request, onBack, onNavigate }) {
  const [items, setItems] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);

  const load = async (nextPage, append = false) => {
    setLoading(true);
    try {
      const payload = await request(`/api/personalized-content/feed?page=${nextPage}`);
      const feed = payload?.data;
      setItems((current) => append ? [...current, ...(feed?.data || [])] : (feed?.data || []));
      setPage(feed?.current_page || nextPage); setLastPage(feed?.last_page || 1);
    } catch { if (!append) setItems([]); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(1); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const action = async (item, kind) => {
    try { await request(`/api/personalized-content/${item.id}/${kind}`, { method: "POST", body: JSON.stringify({ event_uuid: crypto.randomUUID(), surface: "FOR_YOU" }) }); } catch { /* non-blocking */ }
    if (kind === "dismiss") setItems((current) => current.filter((entry) => entry.id !== item.id));
    if (kind === "click" && item.cta_route) onNavigate(item.cta_route, item.cta_payload);
  };

  return <main className="fy-page">
    <header className="fy-header"><button type="button" onClick={onBack} aria-label="Back"><ArrowLeft /></button><div><small>DISCOVER</small><h1>For You</h1></div></header>
    <p className="fy-intro">Eligible updates and opportunities ranked from your ExaEarn preferences.</p>
    <section className="fy-grid" aria-live="polite">
      {loading && !items.length ? [1,2,3].map((key) => <div className="fy-skeleton" key={key} />) : null}
      {!loading && !items.length ? <div className="fy-empty"><Compass /><strong>Nothing new right now</strong><span>Your full ExaEarn services remain available from the dashboard.</span></div> : null}
      {items.map((item) => <article className="fy-card" key={item.id}>
        <div className="fy-card-top"><span>{item.badge || item.type.replaceAll("_", " ")}</span><div><button type="button" onClick={() => action(item, "save")} aria-label="Save"><Bookmark /></button><button type="button" onClick={() => action(item, "dismiss")} aria-label="Not interested"><X /></button></div></div>
        <h2>{item.title}</h2>{item.subtitle ? <h3>{item.subtitle}</h3> : null}<p>{item.body}</p>
        {item.cta_route ? <button type="button" className="fy-cta" onClick={() => action(item, "click")}>{item.cta_label || "View Details"}<ChevronRight /></button> : null}
        <small className="fy-source">{item.source_type === "EXTERNAL" ? item.source_provider || "Third-party source" : "ExaEarn"}</small>
      </article>)}
    </section>
    {page < lastPage ? <button type="button" className="fy-more" disabled={loading} onClick={() => load(page + 1, true)}>{loading ? "Loading..." : "Load more"}</button> : null}
  </main>;
}
