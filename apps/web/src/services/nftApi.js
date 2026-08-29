function normalizeBaseUrl(apiBaseUrl) {
  if (!apiBaseUrl) throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  return apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
}

async function apiRequest({ apiBaseUrl, token, path, method = "GET", body, idempotencyKey }) {
  const response = await fetch(`${normalizeBaseUrl(apiBaseUrl)}${path}`, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {}),
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });

  let payload = {};
  try { payload = await response.json(); } catch { payload = {}; }
  if (!response.ok) throw new Error(payload?.message || `Request failed (${response.status}).`);
  return payload;
}

async function uploadRequest({ apiBaseUrl, token, path, formData }) {
  const response = await fetch(`${normalizeBaseUrl(apiBaseUrl)}${path}`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  let payload = {};
  try { payload = await response.json(); } catch { payload = {}; }
  if (!response.ok) throw new Error(payload?.message || `Upload failed (${response.status}).`);
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

export const mintFinancialNft = ({ apiBaseUrl, token, payload }) => apiRequest({ apiBaseUrl, token, path: "/api/nft/mint", method: "POST", body: payload, idempotencyKey: payload?.idempotency_key || `nft-mint-${Date.now()}` });
export const upgradeFinancialNft = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/upgrade`, method: "POST", body: payload });
export const subscribeToFinancialNft = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/subscriptions`, method: "POST", body: payload });
export const createNftListing = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/listings`, method: "POST", body: payload, idempotencyKey: payload?.idempotency_key || `nft-list-${nftId}-${Date.now()}` });
export const buyNftListing = ({ apiBaseUrl, token, listingId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/listings/${listingId}/buy`, method: "POST", body: payload, idempotencyKey: payload?.idempotency_key || `nft-buy-${listingId}-${Date.now()}` });
export const createNftAuction = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/auctions`, method: "POST", body: payload });
export const placeNftBid = ({ apiBaseUrl, token, auctionId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/auctions/${auctionId}/bids`, method: "POST", body: payload });
export const reportNft = ({ apiBaseUrl, token, nftId, payload }) => apiRequest({ apiBaseUrl, token, path: `/api/nft/assets/${nftId}/reports`, method: "POST", body: payload });

export function uploadNftMedia({ apiBaseUrl, token, file, nftId, collectionId, mediaType = "IMAGE", visibility = "PUBLIC", name }) {
  const formData = new FormData();
  formData.append("file", file);
  if (nftId) formData.append("nft_id", String(nftId));
  if (collectionId) formData.append("collection_id", String(collectionId));
  formData.append("media_type", mediaType);
  formData.append("visibility", visibility);
  if (name) formData.append("name", name);

  return uploadRequest({ apiBaseUrl, token, path: "/api/nft/media", formData });
}
