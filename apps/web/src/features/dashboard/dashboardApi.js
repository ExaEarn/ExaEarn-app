const LOCAL_KEY = 'exaearn_dashboard_preferences';
export const defaultDashboardPreferences = { mode: 'all', primary_interest: null, selected_interests: [], hidden_widgets: [], widget_order: [], onboarding_completed: false };

function localPreferences() {
  try {
    return { ...defaultDashboardPreferences, ...JSON.parse(localStorage.getItem(LOCAL_KEY) || '{}') };
  } catch {
    return defaultDashboardPreferences;
  }
}

function storePreferences(preferences) {
  try {
    localStorage.setItem(LOCAL_KEY, JSON.stringify(preferences));
  } catch {
    // localStorage may be unavailable in private contexts.
  }
}

export async function loadDashboard(request, demo = false) {
  if (demo) return { preferences: localPreferences(), state: {} };
  try {
    return (await request('/api/dashboard')).data;
  } catch {
    return { preferences: localPreferences(), state: {} };
  }
}

export async function saveDashboard(request, preferences, demo = false) {
  storePreferences(preferences);
  if (demo) return preferences;
  try {
    const payload = await request('/api/preferences/dashboard', { method: 'PUT', body: JSON.stringify(preferences), timeoutMs: 8000 });
    return payload?.data || preferences;
  } catch {
    return preferences;
  }
}

export async function resetDashboard(request, demo = false) {
  try {
    localStorage.removeItem(LOCAL_KEY);
  } catch {
    // ignore localStorage failures
  }
  if (!demo) {
    try {
      await request('/api/preferences/dashboard', { method: 'DELETE', timeoutMs: 8000 });
    } catch {
      // keep the local reset responsive even when backend sync is unavailable.
    }
  }
  return defaultDashboardPreferences;
}
