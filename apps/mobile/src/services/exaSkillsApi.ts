type MobileRequest = <T>(path: string, options?: RequestInit & { headers?: Record<string, string> }) => Promise<T>;

export type ExaSkillsCourse = {
  id: number | string;
  slug?: string;
  title?: string;
  description?: string;
  price?: string | number;
  settlement_asset?: string;
  difficulty?: string;
  category?: { name?: string; slug?: string };
  lessons?: Array<{ id: number; title?: string; content?: string; duration_seconds?: number; order_index?: number; metadata?: Record<string, unknown> }>;
  quiz?: { questions?: Array<{ id: number; question?: string; options?: string[] }> };
};

function unwrapArray(payload: unknown): ExaSkillsCourse[] {
  const data = (payload as { data?: unknown })?.data;
  if (Array.isArray((data as { data?: unknown })?.data)) return (data as { data: ExaSkillsCourse[] }).data;
  if (Array.isArray(data)) return data as ExaSkillsCourse[];
  return [];
}

export async function fetchExaSkillsHome(request: MobileRequest) {
  return request<Record<string, unknown>>("/api/exaskills/home");
}

export async function fetchExaSkillsCourses(request: MobileRequest, query = "") {
  const payload = await request(`/api/exaskills/courses${query ? `?search=${encodeURIComponent(query)}` : "?per_page=30"}`);
  return unwrapArray(payload);
}

export async function fetchExaSkillsCourse(request: MobileRequest, course: string | number) {
  const payload = await request<{ data?: ExaSkillsCourse }>(`/api/exaskills/courses/${course}`);
  return payload.data;
}

export async function enrollExaSkillsCourse(request: MobileRequest, course: string | number) {
  return request<Record<string, unknown>>(`/api/exaskills/courses/${course}/enroll`, {
    method: "POST",
    headers: { "Idempotency-Key": `mobile-exaskills-enroll-${course}` },
  });
}

export async function purchaseExaSkillsCourse(request: MobileRequest, course: string | number) {
  return request<Record<string, unknown>>(`/api/exaskills/courses/${course}/purchase`, {
    method: "POST",
    headers: { "Idempotency-Key": `mobile-exaskills-purchase-${course}-${Date.now()}` },
  });
}

export async function completeExaSkillsLesson(request: MobileRequest, course: string | number, lesson: number, watchSeconds = 0) {
  return request<Record<string, unknown>>(`/api/exaskills/courses/${course}/lessons/${lesson}/complete`, {
    method: "POST",
    body: JSON.stringify({ watch_seconds: watchSeconds }),
  });
}

export async function submitExaSkillsAssessment(request: MobileRequest, course: string | number, answers: Record<string, string>) {
  return request<Record<string, unknown>>(`/api/exaskills/courses/${course}/assessment/attempts`, {
    method: "POST",
    headers: { "Idempotency-Key": `mobile-exaskills-assessment-${course}-${Date.now()}` },
    body: JSON.stringify({ answers }),
  });
}

export async function fetchExaSkillsDashboard(request: MobileRequest) {
  return request<Record<string, unknown>>("/api/exaskills/dashboard");
}

export async function fetchExaSkillsSubscription(request: MobileRequest) {
  return request<Record<string, unknown>>("/api/exaskills/subscriptions/current");
}

export async function subscribeExaSkills(request: MobileRequest, planCode: string) {
  return request<Record<string, unknown>>("/api/exaskills/subscriptions", {
    method: "POST",
    headers: { "Idempotency-Key": `mobile-exaskills-subscription-${planCode}-${Date.now()}` },
    body: JSON.stringify({ plan_code: planCode, billing_cycle: "monthly" }),
  });
}
