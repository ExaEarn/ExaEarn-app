const LOCAL_KEY = 'exaearn_dashboard_preferences';
export const defaultDashboardPreferences = { mode: 'all', primary_interest: null, selected_interests: [], hidden_widgets: [], widget_order: [], onboarding_completed: false };
function localPreferences() { try { return { ...defaultDashboardPreferences, ...JSON.parse(localStorage.getItem(LOCAL_KEY) || '{}') }; } catch { return defaultDashboardPreferences; } }
export async function loadDashboard(request, demo = false) { if (demo) return { preferences: localPreferences(), state: {} }; try { return (await request('/api/dashboard')).data; } catch { return { preferences: localPreferences(), state: {} }; } }
export async function saveDashboard(request, preferences, demo = false) { localStorage.setItem(LOCAL_KEY, JSON.stringify(preferences)); if (demo) return preferences; return (await request('/api/preferences/dashboard', { method: 'PUT', body: JSON.stringify(preferences) })).data; }
export async function resetDashboard(request, demo = false) { localStorage.removeItem(LOCAL_KEY); if (!demo) await request('/api/preferences/dashboard', { method: 'DELETE' }); return defaultDashboardPreferences; }
