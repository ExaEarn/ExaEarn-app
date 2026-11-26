import { getSimulatedOfflineHistory } from "./exaPayOfflineSimulator";

const PAGE_SIZE = 30;
const HISTORY_SOURCES = [
  { key: "wallet", path: "/api/transactions/mine", cursor: true },
  { key: "exchange", path: "/api/trade/history" },
  { key: "convert", path: "/api/swap/history" },
  { key: "giftcard", path: "/api/giftcard/orders/mine" },
  { key: "p2p", path: "/api/p2p/trades/mine", cursor: true },
  { key: "card", path: "/api/cards/transactions/mine", cursor: true },
  { key: "staking", path: "/api/v1/staking/transactions", cursor: true },
];

function apiUrl(apiBaseUrl, path) {
  if (!apiBaseUrl) throw new Error("The ExaEarn API is not configured.");
  return `${apiBaseUrl.replace(/\/+$/, "")}${path}`;
}

async function getJson(url, token, signal) {
  const response = await fetch(url, {
    signal,
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  if (!response.ok) {
    if (response.status === 401 || response.status === 403) {
      throw new Error("Your session has expired. Sign in again to view transaction history.");
    }
    throw new Error(`Transaction history request failed (${response.status}).`);
  }
  return response.json();
}

export async function fetchUserTransactions({
  apiBaseUrl,
  token,
  userId,
  filters = {},
  progress = {},
  perPage = PAGE_SIZE,
  signal,
}) {
  const nextProgress = { ...progress };
  const results = await Promise.allSettled(HISTORY_SOURCES.map(async (source) => {
    const previous = progress[source.key];
    if (previous && !previous.hasMore) return { source, rows: [], state: previous };
    const params = new URLSearchParams({ per_page: String(perPage) });
    if (source.cursor) {
      params.set("cursor_mode", "1");
      if (previous?.cursor) params.set("cursor", previous.cursor);
    } else {
      params.set("page", String((previous?.page || 0) + 1));
    }
    if (source.key === "wallet") {
      for (const [key, value] of Object.entries(filters)) {
        if (key === "type" && ["transfer", "card_payment", "convert", "p2p_trade", "staking", "giftcard"].includes(String(value).toLowerCase())) continue;
        if (value !== undefined && value !== null && String(value).trim() !== "") params.set(key, String(value).trim());
      }
    }
    const payload = await getJson(apiUrl(apiBaseUrl, `${source.path}?${params}`), token, signal);
    const nested = payload?.data;
    const page = nested && !Array.isArray(nested) && typeof nested === "object" ? nested : {};
    const rows = Array.isArray(nested) ? nested : Array.isArray(page.data) ? page.data : Array.isArray(page.items) ? page.items : [];
    const meta = payload?.meta || page.meta || {};
    const pageNumber = Number(page.current_page || previous?.page + 1 || 1);
    const lastPage = Number(page.last_page);
    const hasMore = source.cursor
      ? Boolean(meta.has_more)
      : Number.isFinite(lastPage) ? pageNumber < lastPage : Boolean(page.next_page_url) || rows.length >= perPage;
    const state = {
      page: pageNumber,
      cursor: meta.next_cursor || null,
      hasMore,
    };
    return { source, rows, state };
  }));

  const errors = [];
  const data = [];
  let successfulSources = 0;
  results.forEach((result, index) => {
    const source = HISTORY_SOURCES[index];
    if (result.status === "rejected") {
      if (result.reason?.name === "AbortError") return;
      errors.push(`${source.key}: ${result.reason?.message || "Unable to load source."}`);
      nextProgress[source.key] = progress[source.key] || { page: 0, cursor: null, hasMore: true };
      return;
    }
    successfulSources += 1;
    nextProgress[source.key] = result.value.state;
    data.push(...result.value.rows.map((row) => ({ ...row, __history_source: source.key })));
  });
  if (successfulSources === 0) throw new Error("Could not load transaction history from any source.");
  data.push(...getSimulatedOfflineHistory(userId));
  data.sort((left, right) => new Date(right.created_at || right.executed_at || right.timestamp || 0) - new Date(left.created_at || left.executed_at || left.timestamp || 0));
  return {
    data,
    meta: {
      progress: nextProgress,
      has_more: HISTORY_SOURCES.some((source) => nextProgress[source.key]?.hasMore),
      errors,
    },
  };
}

export async function fetchWalletAssetRegistry({ apiBaseUrl, token, signal }) {
  const payload = await getJson(apiUrl(apiBaseUrl, "/api/wallet/balances"), token, signal);
  const rows = Array.isArray(payload?.data)
    ? payload.data
    : Array.isArray(payload?.data?.data)
      ? payload.data.data
      : [];
  const options = new Map();
  for (const row of rows) {
    const currency = String(row?.currency ?? row?.asset ?? row?.symbol ?? "").trim().toUpperCase();
    if (!currency) continue;
    const decimalsValue = Number(row?.decimals ?? row?.precision ?? row?.asset_precision);
    const network = String(row?.network ?? row?.chain ?? "").trim();
    options.set(currency, {
      currency,
      decimals: Number.isInteger(decimalsValue) && decimalsValue >= 0 && decimalsValue <= 18 ? decimalsValue : null,
      network,
    });
  }
  return [...options.values()].sort((left, right) => left.currency.localeCompare(right.currency));
}

export async function internalTransfer({ apiBaseUrl, token, fromWallet, toWallet, asset, amount }) {
  const response = await fetch(apiUrl(apiBaseUrl, "/api/wallet/internal-transfer"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ from_wallet: fromWallet, to_wallet: toWallet, asset, amount }),
  });
  if (!response.ok) throw new Error(`Failed to transfer (${response.status}).`);
  return response.json();
}
