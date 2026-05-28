export async function placeFuturesOrder({ apiBaseUrl, payload }) {
  if (!apiBaseUrl) {
    throw new Error('Missing VITE_API_URL. Set it in your .env file.');
  }

  const normalizedBase = apiBaseUrl.endsWith('/') ? apiBaseUrl.slice(0, -1) : apiBaseUrl;

  const response = await fetch(`${normalizedBase}/api/futures/orders`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    throw new Error(payload.message || `Failed to place futures order (${response.status}).`);
  }

  return response.json();
}

export async function validateFuturesOrder({ apiBaseUrl, payload }) {
  if (!apiBaseUrl) {
    throw new Error('Missing VITE_API_URL. Set it in your .env file.');
  }

  const normalizedBase = apiBaseUrl.endsWith('/') ? apiBaseUrl.slice(0, -1) : apiBaseUrl;

  const response = await fetch(`${normalizedBase}/api/futures/orders/validate`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    throw new Error(payload.message || `Failed to validate futures order (${response.status}).`);
  }

  return response.json();
}

export async function fetchFuturesMarginStatus({ apiBaseUrl }) {
  if (!apiBaseUrl) {
    throw new Error('Missing VITE_API_URL. Set it in your .env file.');
  }

  const normalizedBase = apiBaseUrl.endsWith('/') ? apiBaseUrl.slice(0, -1) : apiBaseUrl;

  const response = await fetch(`${normalizedBase}/api/futures/margin/status`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
    },
    credentials: 'include',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch futures margin status (${response.status}).`);
  }

  return response.json();
}
