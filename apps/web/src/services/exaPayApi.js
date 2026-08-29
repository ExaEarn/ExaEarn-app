function unwrap(payload) {
  return payload?.data ?? payload;
}

export async function getMerchants(request) {
  return unwrap(await request("/api/exapay/merchants", { method: "GET" }));
}

export async function applyForMerchant(request, body) {
  return unwrap(await request("/api/exapay/merchants", {
    method: "POST",
    body: JSON.stringify(body),
  }));
}

export async function getMerchantOverview(request, merchantId) {
  return unwrap(await request(`/api/exapay/merchants/${merchantId}/overview`, { method: "GET" }));
}

export async function getMerchantPayments(request, merchantId) {
  return unwrap(await request(`/api/exapay/merchants/${merchantId}/payments`, { method: "GET" }));
}

export async function createPaymentIntent(request, merchantId, body) {
  return unwrap(await request(`/api/exapay/merchants/${merchantId}/payment-intents`, {
    method: "POST",
    body: JSON.stringify(body),
  }));
}

export async function createPaymentLink(request, merchantId, body) {
  return unwrap(await request(`/api/exapay/merchants/${merchantId}/payment-links`, {
    method: "POST",
    body: JSON.stringify(body),
  }));
}

export async function getPaymentLinks(request, merchantId) {
  return unwrap(await request(`/api/exapay/merchants/${merchantId}/payment-links`, { method: "GET" }));
}

export async function createMerchantApiKey(request, merchantId, body) {
  return unwrap(await request(`/api/exapay/merchants/${merchantId}/api-keys`, {
    method: "POST",
    body: JSON.stringify(body),
  }));
}
