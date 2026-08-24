function unwrap(payload) {
  return payload?.data ?? payload;
}

export async function getCardProducts(request) {
  return unwrap(await request("/api/cards/products", { method: "GET" }));
}

export async function getCards(request) {
  return unwrap(await request("/api/cards", { method: "GET" }));
}

export async function issueCard(request, body) {
  return unwrap(await request("/api/cards", {
    method: "POST",
    headers: { "Idempotency-Key": body.idempotencyKey },
    body: JSON.stringify({ product_code: body.productCode, nickname: body.nickname || undefined }),
  }));
}

export async function createFundingQuote(request, cardUuid, body) {
  return unwrap(await request(`/api/cards/${cardUuid}/funding-quotes`, {
    method: "POST",
    body: JSON.stringify({ source_asset: body.sourceAsset, amount: body.amount }),
  }));
}

export async function fundCard(request, body) {
  return unwrap(await request("/api/cards/funding-requests", {
    method: "POST",
    headers: { "Idempotency-Key": body.idempotencyKey },
    body: JSON.stringify({ quote_uuid: body.quoteUuid, test_behavior: body.testBehavior || undefined }),
  }));
}

export async function unloadCard(request, cardUuid, body) {
  return unwrap(await request(`/api/cards/${cardUuid}/unload`, {
    method: "POST",
    headers: { "Idempotency-Key": body.idempotencyKey },
    body: JSON.stringify({ amount: body.amount }),
  }));
}

export async function getCardTransactions(request, cardUuid) {
  return unwrap(await request(`/api/cards/${cardUuid}/transactions`, { method: "GET" }));
}

export async function getCardAuthorizations(request, cardUuid) {
  return unwrap(await request(`/api/cards/${cardUuid}/authorizations`, { method: "GET" }));
}

export async function freezeCard(request, cardUuid, reason) {
  return unwrap(await request(`/api/cards/${cardUuid}/freeze`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  }));
}

export async function unfreezeCard(request, cardUuid, reason) {
  return unwrap(await request(`/api/cards/${cardUuid}/unfreeze`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  }));
}

export async function reportCardLostOrStolen(request, cardUuid, reason) {
  return unwrap(await request(`/api/cards/${cardUuid}/report-lost-stolen`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  }));
}

export async function getCardDetailsToken(request, cardUuid) {
  return unwrap(await request(`/api/cards/${cardUuid}/details-token`, { method: "POST" }));
}

export async function updateCardControls(request, cardUuid, controls) {
  return unwrap(await request(`/api/cards/${cardUuid}/controls`, {
    method: "PUT",
    body: JSON.stringify(controls),
  }));
}

export async function updateCardLimits(request, cardUuid, limits) {
  return unwrap(await request(`/api/cards/${cardUuid}/limits`, {
    method: "PUT",
    body: JSON.stringify(limits),
  }));
}

export async function getCardRealtimeReplay(request, afterSequence = 0) {
  return unwrap(await request(`/api/cards/realtime/replay?after_sequence=${encodeURIComponent(afterSequence)}`, { method: "GET" }));
}
