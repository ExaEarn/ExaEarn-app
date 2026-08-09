export type EntityId = string | number;

export type ApiEnvelope<T> = {
  data: T;
  message?: string;
  status?: string;
};
export type DashboardExperienceKey = "crypto_exchange" | "exaai" | "earn" | "giftcards" | "games" | "exaskills" | "crowdfund" | "nft_marketplace" | "agritech";
export type DashboardPreferences = { mode: "all" | "personalized"; primary_interest: DashboardExperienceKey | null; selected_interests: DashboardExperienceKey[]; hidden_widgets: string[]; widget_order: string[]; onboarding_completed: boolean; };
export type DashboardExperience = { label: string; description: string; route: string; availability: "available" | "partial" | "disabled" | "coming_soon"; };
export type DashboardCriticalAlert = { id: string; kind: string; title: string; message: string; created_at?: string };
export type DashboardPayload = { preferences: DashboardPreferences; experiences: Record<DashboardExperienceKey, DashboardExperience>; state: Partial<Record<DashboardExperienceKey, Record<string, number>>>; critical_alerts: DashboardCriticalAlert[]; };
