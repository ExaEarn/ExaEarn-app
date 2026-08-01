import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { stakingApi, type ApiRequest } from "./stakingApi";
import type {
  ExaTokenCampaign,
  PortfolioRow,
  StakingApyHistory,
  StakingAsset,
  StakingNetworkStatus,
  StakingPosition,
  StakingProduct,
  StakingReward,
  StakingTerms,
  StakingTransaction,
} from "./types";
import { EXCLUDED_NATIVE_STAKING_SYMBOLS, mapApiError } from "./stakingUtils";

export type StakingData = {
  assets: StakingAsset[];
  products: StakingProduct[];
  portfolio: PortfolioRow[];
  positions: StakingPosition[];
  rewards: StakingReward[];
  transactions: StakingTransaction[];
  apyHistory: StakingApyHistory[];
  terms: StakingTerms | null;
  campaigns: ExaTokenCampaign[];
  networkStatuses: StakingNetworkStatus[];
  loading: boolean;
  refreshing: boolean;
  error: string;
  lastUpdated: Date | null;
  refresh: () => Promise<void>;
};

export function useStakingData(request: ApiRequest): StakingData {
  const [assets, setAssets] = useState<StakingAsset[]>([]);
  const [products, setProducts] = useState<StakingProduct[]>([]);
  const [portfolio, setPortfolio] = useState<PortfolioRow[]>([]);
  const [positions, setPositions] = useState<StakingPosition[]>([]);
  const [rewards, setRewards] = useState<StakingReward[]>([]);
  const [transactions, setTransactions] = useState<StakingTransaction[]>([]);
  const [apyHistory, setApyHistory] = useState<StakingApyHistory[]>([]);
  const [terms, setTerms] = useState<StakingTerms | null>(null);
  const [campaigns, setCampaigns] = useState<ExaTokenCampaign[]>([]);
  const [networkStatuses, setNetworkStatuses] = useState<StakingNetworkStatus[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);
  const hasLoaded = useRef(false);

  const isAuthenticationError = (value: unknown): boolean => {
    const candidate = value as { status?: number; message?: string };
    return candidate?.status === 401 || /authentication required/i.test(String(candidate?.message ?? ""));
  };

  const refresh = useCallback(async () => {
    const firstLoad = !hasLoaded.current;
    setLoading(firstLoad);
    setRefreshing(!firstLoad);
    setError("");
    const results = await Promise.allSettled([
      stakingApi.assets(request),
      stakingApi.products(request),
      stakingApi.portfolio(request),
      stakingApi.positions(request),
      stakingApi.rewards(request),
      stakingApi.transactions(request),
      stakingApi.apyHistory(request),
      stakingApi.terms(request),
      stakingApi.campaigns(request),
      stakingApi.networkStatuses(request),
      stakingApi.exaTokenRewards(request),
    ]);

    const catalogReady = results[0].status === "fulfilled" || results[1].status === "fulfilled";
    const rejected = results.find((result, index) => {
      if (result.status !== "rejected") return false;
      const isPrivateEndpoint = [2, 3, 4, 5, 10].includes(index);
      return !(catalogReady && isPrivateEndpoint && isAuthenticationError(result.reason));
    });

    if (rejected?.status === "rejected") {
      setError(mapApiError(rejected.reason));
    }

    if (results[0].status === "fulfilled") setAssets(results[0].value.filter((asset) => !EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(asset.symbol.toUpperCase())));
    if (results[1].status === "fulfilled") setProducts(results[1].value.filter((product) => !EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(product.symbol.toUpperCase())));
    if (results[2].status === "fulfilled") setPortfolio(results[2].value.filter((row) => !EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(row.symbol.toUpperCase())));
    if (results[3].status === "fulfilled") setPositions(results[3].value.filter((position) => !EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(position.symbol.toUpperCase())));
    if (results[4].status === "fulfilled") setRewards(results[4].value.filter((reward) => !EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(reward.symbol.toUpperCase())));
    if (results[5].status === "fulfilled") setTransactions(results[5].value.filter((transaction) => !EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(transaction.symbol.toUpperCase())));
    if (results[6].status === "fulfilled") setApyHistory(results[6].value.filter((row) => !EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(row.symbol.toUpperCase())));
    if (results[7].status === "fulfilled") setTerms(results[7].value);
    if (results[8].status === "fulfilled") setCampaigns(results[8].value);
    if (results[9].status === "fulfilled") setNetworkStatuses(results[9].value.filter((status) => !EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(status.symbol.toUpperCase())));
    hasLoaded.current = true;
    setLastUpdated(new Date());
    setLoading(false);
    setRefreshing(false);
  }, [request]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return useMemo(
    () => ({
      assets,
      products,
      portfolio,
      positions,
      rewards,
      transactions,
      apyHistory,
      terms,
      campaigns,
      networkStatuses,
      loading,
      refreshing,
      error,
      lastUpdated,
      refresh,
    }),
    [assets, products, portfolio, positions, rewards, transactions, apyHistory, terms, campaigns, networkStatuses, loading, refreshing, error, lastUpdated, refresh],
  );
}
