export async function affiliateRequest(request, path, options = {}) {
  const payload = await request(path, options);
  return payload?.data ?? payload;
}

export const affiliateApi = {
  overview: (request, period = "30d") => affiliateRequest(request, `/api/affiliate/overview?period=${encodeURIComponent(period)}`),
  referrals: (request, params = {}) => {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") query.set(key, String(value));
    });
    const suffix = query.toString() ? `?${query.toString()}` : "";
    return affiliateRequest(request, `/api/affiliate/referrals${suffix}`);
  },
  earnings: (request, params = {}) => {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") query.set(key, String(value));
    });
    const suffix = query.toString() ? `?${query.toString()}` : "";
    return affiliateRequest(request, `/api/affiliate/earnings${suffix}`);
  },
  tools: (request) => affiliateRequest(request, "/api/affiliate/tools"),
  payouts: (request) => affiliateRequest(request, "/api/affiliate/payouts"),
  requestPayout: (request, body) => affiliateRequest(request, "/api/affiliate/payouts", {
    method: "POST",
    body: JSON.stringify(body),
  }),
};
