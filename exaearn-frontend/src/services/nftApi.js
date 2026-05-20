function normalizeBaseUrl(apiBaseUrl) {
  if (!apiBaseUrl) throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  return apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
}

async function apiRequest({ apiBaseUrl, token, path, method = "GET", body }) {
  const response = await fetch(`${normalizeBaseUrl(apiBaseUrl)}${path}`, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });

  let payload = {};
  try { payload = await response.json(); } catch { payload = {}; }
  if (!response.ok) throw new Error(payload?.message || `Request failed (${response.status}).`);
  return payload;
}

export const fetchNftDashboard = ({ apiBaseUrl, token }) => apiRequest({ apiBaseUrl, token, path: "/api/nft/dashboard" });
export const fetchMyNfts = ({ apiBaseUrl, token }) => apiRequest({ apiBaseUrl, token, path: "/api/nft/my-assets" });
export const fetchNftCollections = ({ apiBaseUrl, token }) => apiRequest({ apiBaseUrl, token, path: "/api/nft/collections" });

export function fetchNftMarketplace({ apiBaseUrl, token, params = {} }) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "" && value !== "all") query.set(key, String(value));
  });
  const suffix = query.toString() ? `?${query.toString()}` : "";
  return apiRequest({ apiBaseUrl, token, path: `/api/nft/marketplace${suffix}` });
}

export const mintFinancialNft = ({ apiBaseUrl, token, payload }) => apiRequest({ apiBaseUrl, token, path: "/api/nft/mint", method: "POST", body: payload });
export const upgradeFinancialNft = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/upgrade`, method: "POST", body: payload });
export const subscribeToFinancialNft = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/subscriptions`, method: "POST", body: payload });
export const createNftListing = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/listings`, method: "POST", body: payload });
export const buyNftListing = ({ apiBaseUrl, token, listingId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/listings/${listingId}/buy`, method: "POST", body: payload });
export const createNftAuction = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/auctions`, method: "POST", body: payload });
export const placeNftBid = ({ apiBaseUrl, token, auctionId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/auctions/${auctionId}/bids`, method: "POST", body: payload });
