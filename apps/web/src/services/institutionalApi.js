export async function fetchInstitutionalOverview(request) {
  return request("/api/institutional/overview", { method: "GET" });
}

export async function fetchInstitutionalApplications(request) {
  return request("/api/institutional/applications", { method: "GET" });
}

export async function applyForInstitutional(request, payload) {
  return request("/api/institutional/apply", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function createInstitutionalSubaccount(request, payload) {
  return request("/api/institutional/subaccounts", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function createInstitutionalTransfer(request, payload) {
  return request("/api/institutional/transfers", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function fetchMarketMakerOverview(request) {
  return request("/api/institutional/market-making/overview", { method: "GET" });
}

export async function applyForMarketMakerProgram(request, payload) {
  return request("/api/institutional/market-making/apply", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function fetchMarketMakerBots(request) {
  return request("/api/institutional/market-making/bots", { method: "GET" });
}

export async function fetchMarketMakerBotStrategies(request) {
  return request("/api/institutional/market-making/bots/strategies", { method: "GET" });
}

export async function createMarketMakerBotStrategy(request, payload) {
  return request("/api/institutional/market-making/bots/strategies", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function createMarketMakerBot(request, payload) {
  return request("/api/institutional/market-making/bots", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function runMarketMakerBotShadow(request, botUuid, payload) {
  return request(`/api/institutional/market-making/bots/${botUuid}/shadow`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function startMarketMakerBot(request, botUuid, payload) {
  return request(`/api/institutional/market-making/bots/${botUuid}/start`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function fetchOtcRfqs(request) {
  return request("/api/institutional/otc/rfqs", { method: "GET" });
}

export async function requestOtcQuote(request, payload) {
  return request("/api/institutional/otc/rfqs", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function acceptOtcQuote(request, rfqUuid, payload) {
  return request(`/api/institutional/otc/rfqs/${rfqUuid}/accept`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}
