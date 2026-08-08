export function unwrap(payload) {
  return payload?.data ?? payload ?? {};
}

export function createFiatWithdrawalApi(request) {
  return {
    async meta(currency = "USD") {
      return unwrap(await request(`/api/fiat-withdrawals/meta?currency=${encodeURIComponent(currency)}`, { method: "GET", timeoutMs: 20000 }));
    },
    async banks({ country, currency }) {
      const payload = unwrap(await request(`/api/fiat-withdrawals/banks?country=${encodeURIComponent(country)}&currency=${encodeURIComponent(currency)}`, { method: "GET", timeoutMs: 10000 }));
      return Array.isArray(payload.items) ? payload.items : [];
    },
    async resolveAccount(body) {
      return unwrap(await request("/api/fiat-withdrawals/resolve-account", { method: "POST", body: JSON.stringify(body) }));
    },
    async quote(body) {
      return unwrap(await request("/api/fiat-withdrawals/quote", { method: "POST", body: JSON.stringify(body) }));
    },
    async beneficiaries(currency) {
      const suffix = currency ? `?currency=${encodeURIComponent(currency)}` : "";
      const payload = unwrap(await request(`/api/fiat-withdrawals/beneficiaries${suffix}`, { method: "GET", timeoutMs: 10000 }));
      return Array.isArray(payload.items) ? payload.items : [];
    },
    async createIntent(body, idempotencyKey) {
      const payload = unwrap(await request("/api/fiat-withdrawals/intents", {
        method: "POST",
        headers: idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {},
        body: JSON.stringify(body),
      }));
      return payload.intent ?? payload;
    },
    async challenge(uuid, method) {
      return unwrap(await request(`/api/fiat-withdrawals/intents/${uuid}/verification-challenges`, {
        method: "POST",
        body: JSON.stringify({ method }),
      }));
    },
    async verify(uuid, body) {
      const payload = unwrap(await request(`/api/fiat-withdrawals/intents/${uuid}/verify`, {
        method: "POST",
        body: JSON.stringify(body),
      }));
      return payload.intent ?? payload;
    },
    async history() {
      const payload = unwrap(await request("/api/fiat-withdrawals/history", { method: "GET", timeoutMs: 10000 }));
      return Array.isArray(payload.items) ? payload.items : [];
    },
  };
}
