import { useCallback, useEffect, useState } from "react";

export function usePersonalizedContent(request, enabled = true) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(Boolean(enabled));

  const refresh = useCallback(async () => {
    if (!enabled) return;
    setLoading(true);
    try {
      const payload = await request("/api/personalized-content/dashboard");
      setItems(Array.isArray(payload?.data) ? payload.data : []);
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [enabled, request]);

  useEffect(() => { refresh(); }, [refresh]);

  const interact = useCallback(async (item, interaction, context = {}) => {
    if (!item?.id) return;
    const eventUuid = globalThis.crypto?.randomUUID?.();
    try {
      await request(`/api/personalized-content/${item.id}/${interaction}`, { method: "POST", body: JSON.stringify({ event_uuid: eventUuid, surface: "DASHBOARD", ...context }) });
    } catch {
      // Discovery telemetry never blocks product navigation.
    }
    if (interaction === "dismiss") setItems((current) => current.filter((candidate) => candidate.id !== item.id));
  }, [request]);

  return { items, loading, refresh, interact };
}
