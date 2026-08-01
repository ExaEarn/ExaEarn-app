const normalizeBaseUrl = (apiBaseUrl = "") => apiBaseUrl.replace(/\/+$/, "");

async function request({ apiBaseUrl, token, path, method = "GET", body, headers = {} }) {
  const response = await fetch(`${normalizeBaseUrl(apiBaseUrl)}${path}`, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body: body ? JSON.stringify(body) : undefined,
    credentials: "include",
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok || payload?.status === "error") {
    throw new Error(payload?.message || `Request failed (${response.status})`);
  }

  return payload;
}

export const getExaSkillsHome = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaskills/home" });
export const getExaSkillsCourses = ({ apiBaseUrl, token, query = "" }) => request({ apiBaseUrl, token, path: `/api/exaskills/courses${query}` });
export const getExaSkillsCourse = ({ apiBaseUrl, token, course }) => request({ apiBaseUrl, token, path: `/api/exaskills/courses/${course}` });
export const enrollExaSkillsCourse = ({ apiBaseUrl, token, course, idempotencyKey }) =>
  request({
    apiBaseUrl,
    token,
    path: `/api/exaskills/courses/${course}/enroll`,
    method: "POST",
    headers: idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {},
  });
export const purchaseExaSkillsCourse = ({ apiBaseUrl, token, course, idempotencyKey }) =>
  request({
    apiBaseUrl,
    token,
    path: `/api/exaskills/courses/${course}/purchase`,
    method: "POST",
    headers: idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {},
  });
export const fundExaSkillsChallenge = ({ apiBaseUrl, token, challenge, idempotencyKey }) =>
  request({
    apiBaseUrl,
    token,
    path: `/api/exaskills/challenges/${challenge}/fund`,
    method: "POST",
    headers: idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {},
  });export const getExaSkillsDashboard = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaskills/dashboard" });
export const submitInstructorApplication = ({ apiBaseUrl, token, body }) =>
  request({ apiBaseUrl, token, path: "/api/exaskills/instructors/apply", method: "POST", body });
export const submitExaSkillsChallenge = ({ apiBaseUrl, token, challenge, body }) =>
  request({ apiBaseUrl, token, path: `/api/exaskills/challenges/${challenge}/submissions`, method: "POST", body });
export const applyExaSkillsOpportunity = ({ apiBaseUrl, token, opportunity, body }) =>
  request({ apiBaseUrl, token, path: `/api/exaskills/opportunities/${opportunity}/applications`, method: "POST", body });
export const verifyExaSkillsCredential = ({ apiBaseUrl, credential }) =>
  request({ apiBaseUrl, path: `/api/exaskills/verify/${credential}` });

