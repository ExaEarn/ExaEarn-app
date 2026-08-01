import type {
  ApiEnvelope,
  ExaTokenCampaign,
  StakeRequest,
  StakingApyHistory,
  StakingAsset,
  StakingNetworkStatus,
  StakingPosition,
  StakingProduct,
  StakingReward,
  StakingTerms,
  StakingTransaction,
  UnstakeRequest,
  PortfolioRow,
} from "./types";

export type ApiRequest = <T = unknown>(path: string, options?: RequestInit) => Promise<T>;

async function getData<T>(request: ApiRequest, path: string, options: RequestInit = { method: "GET" }): Promise<T> {
  const payload = await request<ApiEnvelope<T>>(path, options);
  return payload.data;
}

export const stakingApi = {
  assets: (request: ApiRequest) => getData<StakingAsset[]>(request, "/api/v1/staking/assets"),
  products: (request: ApiRequest) => getData<StakingProduct[]>(request, "/api/v1/staking/products"),
  product: (request: ApiRequest, slug: string) => getData<StakingProduct>(request, `/api/v1/staking/products/${encodeURIComponent(slug)}`),
  portfolio: (request: ApiRequest) => getData<PortfolioRow[]>(request, "/api/v1/staking/portfolio"),
  positions: (request: ApiRequest) => getData<StakingPosition[]>(request, "/api/v1/staking/positions"),
  position: (request: ApiRequest, publicId: string) => getData<StakingPosition>(request, `/api/v1/staking/positions/${encodeURIComponent(publicId)}`),
  rewards: (request: ApiRequest) => getData<StakingReward[]>(request, "/api/v1/staking/rewards"),
  transactions: (request: ApiRequest) => getData<StakingTransaction[]>(request, "/api/v1/staking/transactions"),
  apyHistory: (request: ApiRequest) => getData<StakingApyHistory[]>(request, "/api/v1/staking/apy-history"),
  terms: (request: ApiRequest) => getData<StakingTerms>(request, "/api/v1/staking/terms"),
  campaigns: (request: ApiRequest) => getData<ExaTokenCampaign[]>(request, "/api/v1/staking/exatoken-campaigns"),
  networkStatuses: (request: ApiRequest) => getData<StakingNetworkStatus[]>(request, "/api/v1/staking/network-statuses"),
  unbondingEstimates: (request: ApiRequest) => getData<StakingAsset[]>(request, "/api/v1/staking/unbonding-estimates"),
  exaTokenRewards: async (request: ApiRequest): Promise<unknown[]> => {
    try {
      return await getData<unknown[]>(request, "/api/v1/staking/exatoken-rewards");
    } catch {
      return [];
    }
  },
  createPosition: (request: ApiRequest, body: StakeRequest) =>
    getData<StakingPosition>(request, "/api/v1/staking/positions", { method: "POST", body: JSON.stringify(body) }),
  unstake: (request: ApiRequest, publicId: string, body: UnstakeRequest) =>
    getData<unknown>(request, `/api/v1/staking/positions/${encodeURIComponent(publicId)}/unstake`, { method: "POST", body: JSON.stringify(body) }),
  claimNative: (request: ApiRequest, publicId: string) =>
    getData<unknown>(request, `/api/v1/staking/positions/${encodeURIComponent(publicId)}/claim-native-rewards`, { method: "POST" }),
  claimExaToken: (request: ApiRequest, publicId: string) =>
    getData<unknown>(request, `/api/v1/staking/positions/${encodeURIComponent(publicId)}/claim-exatoken-rewards`, { method: "POST" }),
  autoCompound: (request: ApiRequest, publicId: string, auto_compound: boolean) =>
    getData<StakingPosition>(request, `/api/v1/staking/positions/${encodeURIComponent(publicId)}/auto-compound`, {
      method: "PATCH",
      body: JSON.stringify({ auto_compound }),
    }),
  acceptTerms: (request: ApiRequest, terms_version: string) =>
    request<{ status: string }>("/api/v1/staking/terms/accept", { method: "POST", body: JSON.stringify({ terms_version }) }),
};
