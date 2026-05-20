export async function fetchUserTransactions({ apiBaseUrl, token, userId, perPage = 50 }) {
  if (!apiBaseUrl) {
    throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  }

  if (!userId) {
    throw new Error("Missing user id for transaction lookup.");
  }

  const normalizedBase = apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
  const query = new URLSearchParams({ user_id: String(userId), per_page: String(perPage) }).toString();

  const response = await fetch(`${normalizedBase}/api/transactions/user?${query}`, {
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to load transactions (${response.status}).`);
  }

  const payload = await response.json();
  return payload;
}

export async function internalTransfer({ apiBaseUrl, token, fromWallet, toWallet, asset, amount }) {
  if (!apiBaseUrl) {
    throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  }

  const normalizedBase = apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;

  const response = await fetch(`${normalizedBase}/api/wallet/internal-transfer`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ from_wallet: fromWallet, to_wallet: toWallet, asset, amount }),
  });

  if (!response.ok) {
    throw new Error(`Failed to transfer (${response.status}).`);
  }

  const payload = await response.json();
  return payload;
}
