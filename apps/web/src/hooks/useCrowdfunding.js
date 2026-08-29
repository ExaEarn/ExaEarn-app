import { useCallback, useEffect, useMemo, useState } from "react";
import {
  contributeToCampaign,
  createCampaign,
  createSpendingRequest,
  fetchCampaignDetails,
  fetchCampaignComments,
  fetchCampaignLogs,
  fetchCampaigns,
  createCampaignComment,
  finalizeSpendingRequest,
  refundCampaignContribution,
  reportCampaignComment,
  fetchBackerCrowdfundingDashboard,
  fetchCreatorCrowdfundingDashboard,
  voteSpendingRequest,
} from "../services/crowdfundingApi";
import { campaignData as mockCampaigns } from "../pages/Crowdfunding/campaignData";

const STATUS_MAP = ["active", "funded", "failed", "completed", "frozen"];
const canUseMockCampaigns = import.meta.env.DEV || import.meta.env.VITE_ENABLE_CROWDFUNDING_MOCKS === "true";

function mapMockCampaign(campaign) {
  const progress = Math.min((campaign.raised / Math.max(campaign.target, 1)) * 100, 100);
  const status = campaign.daysRemaining <= 0 ? "failed" : progress >= 100 ? "funded" : "active";
  return {
    id: campaign.id,
    title: campaign.title,
    description: campaign.description,
    category: campaign.category,
    goal_amount: campaign.target,
    raised_amount: campaign.raised,
    deadline: new Date(Date.now() + campaign.daysRemaining * 86400000).toISOString(),
    status,
    manager_wallet: "0x0000000000000000000000000000000000000000",
    contract_address: "",
    contributor_count: campaign.metrics?.backers || 0,
    spending_requests: [],
    contributions: [],
    logs: campaign.activity.map((line, index) => ({
      id: `${campaign.id}-log-${index + 1}`,
      action: "campaign.update",
      data: line,
      created_at: new Date(Date.now() - (index + 1) * 7200000).toISOString(),
    })),
  };
}

function toArrayPayload(payload) {
  if (Array.isArray(payload?.data?.data)) return payload.data.data;
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload)) return payload;
  return [];
}

function normalizeCampaign(input) {
  return {
    id: input.id,
    title: input.title || "Untitled campaign",
    description: input.description || "",
    category: input.category || "General",
    asset: input.asset || input.currency || "USDT",
    classification: input.classification || "PROJECT_SUPPORT",
    goal_amount: Number(input.goal_amount ?? input.funding_goal ?? input.target ?? 0),
    raised_amount: Number(input.raised_amount ?? input.raised ?? 0),
    deadline: input.deadline || input.ends_at || new Date().toISOString(),
    status: STATUS_MAP.includes(String(input.status || "").toLowerCase())
      ? String(input.status).toLowerCase()
      : "active",
    manager_wallet: input.manager_wallet || input.wallet_address || "",
    contract_address: input.contract_address || "",
    contributor_count: Number(input.contributor_count ?? input.backers_count ?? 0),
    spending_requests: Array.isArray(input.spending_requests) ? input.spending_requests : [],
    contributions: Array.isArray(input.contributions) ? input.contributions : [],
    logs: Array.isArray(input.logs) ? input.logs : [],
  };
}

export function useCrowdfunding({ apiBaseUrl, token, wallet, poll = true }) {
  const [campaigns, setCampaigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [txState, setTxState] = useState({ status: "idle", hash: "", message: "" });
  const [dataSource, setDataSource] = useState("api");

  const loadCampaigns = useCallback(async () => {
    if (!apiBaseUrl) {
      setCampaigns(canUseMockCampaigns ? mockCampaigns.map(mapMockCampaign) : []);
      setDataSource(canUseMockCampaigns ? "mock" : "api");
      setError(canUseMockCampaigns ? "Missing API base URL, using local campaign dataset." : "Crowdfunding API is not configured.");
      setLoading(false);
      return;
    }

    setLoading(true);
    setError("");
    try {
      const payload = await fetchCampaigns({ apiBaseUrl, token, params: { per_page: 40 } });
      const rows = toArrayPayload(payload).map(normalizeCampaign);
      setCampaigns(rows);
      setDataSource("api");
    } catch (err) {
      setCampaigns(canUseMockCampaigns ? mockCampaigns.map(mapMockCampaign) : []);
      setDataSource(canUseMockCampaigns ? "mock" : "api");
      setError(err?.message || "Unable to load campaigns from backend.");
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl, token]);

  useEffect(() => {
    loadCampaigns();
  }, [loadCampaigns]);

  useEffect(() => {
    if (!poll) return undefined;
    const intervalId = setInterval(() => {
      loadCampaigns();
    }, 14000);
    return () => clearInterval(intervalId);
  }, [loadCampaigns, poll]);

  const byId = useMemo(() => {
    const result = {};
    campaigns.forEach((campaign) => {
      result[campaign.id] = campaign;
    });
    return result;
  }, [campaigns]);

  const withTx = useCallback(async (handler) => {
    setTxState({ status: "pending", hash: "", message: "Confirm transaction in wallet" });
    try {
      const payload = await handler();
      const hash = payload?.tx_hash || payload?.data?.tx_hash || "";
      setTxState({ status: "confirmed", hash, message: "Transaction confirmed" });
      return payload;
    } catch (err) {
      setTxState({ status: "failed", hash: "", message: err?.message || "Transaction failed" });
      throw err;
    }
  }, []);

  const createCampaignFlow = useCallback(
    async (payload) => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before creating campaign.");
      if (!wallet?.isConnected) throw new Error("Connect wallet before creating campaign.");

      const signature = await wallet.signMessage(`Create ExaEarn campaign: ${payload.title}`);

      const response = await withTx(() =>
        createCampaign({
          apiBaseUrl,
          token,
          payload: {
            ...payload,
            manager_wallet: wallet.address,
            signature,
          },
        })
      );

      await loadCampaigns();
      return response;
    },
    [apiBaseUrl, loadCampaigns, token, wallet, withTx]
  );

  const contributeFlow = useCallback(
    async ({ campaignId, amount, currency }) => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before funding campaigns.");
      if (!wallet?.isConnected) throw new Error("Connect wallet to contribute.");
      const signature = await wallet.signMessage(`Contribute ${amount} ${currency} to campaign ${campaignId}`);

      const response = await withTx(() =>
        contributeToCampaign({
          apiBaseUrl,
          token,
          campaignId,
          payload: {
            amount,
            currency,
            contributor_wallet: wallet.address,
            signature,
          },
        })
      );

      await loadCampaigns();
      return response;
    },
    [apiBaseUrl, loadCampaigns, token, wallet, withTx]
  );

  const createRequestFlow = useCallback(
    async ({ campaignId, title, description, amount, vendorWallet }) => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before creating requests.");
      if (!wallet?.isConnected) throw new Error("Connect wallet to create spending request.");

      const signature = await wallet.signMessage(`Create spending request for ${campaignId}: ${title}`);

      const response = await withTx(() =>
        createSpendingRequest({
          apiBaseUrl,
          token,
          campaignId,
          payload: {
            title,
            description,
            amount,
            vendor_wallet: vendorWallet,
            manager_wallet: wallet.address,
            signature,
          },
        })
      );
      await loadCampaigns();
      return response;
    },
    [apiBaseUrl, loadCampaigns, token, wallet, withTx]
  );

  const voteRequestFlow = useCallback(
    async ({ requestId, vote }) => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before voting.");
      if (!wallet?.isConnected) throw new Error("Connect wallet to vote.");

      const signature = await wallet.signMessage(`Vote ${vote} on request ${requestId}`);

      const response = await withTx(() =>
        voteSpendingRequest({
          apiBaseUrl,
          token,
          requestId,
          payload: {
            vote,
            voter_wallet: wallet.address,
            signature,
          },
        })
      );
      await loadCampaigns();
      return response;
    },
    [apiBaseUrl, loadCampaigns, token, wallet, withTx]
  );

  const finalizeRequestFlow = useCallback(
    async ({ requestId }) => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before finalizing requests.");
      if (!wallet?.isConnected) throw new Error("Connect wallet to finalize request.");

      const signature = await wallet.signMessage(`Finalize request ${requestId}`);

      const response = await withTx(() =>
        finalizeSpendingRequest({
          apiBaseUrl,
          token,
          requestId,
          payload: {
            manager_wallet: wallet.address,
            signature,
          },
        })
      );
      await loadCampaigns();
      return response;
    },
    [apiBaseUrl, loadCampaigns, token, wallet, withTx]
  );

  const refundFlow = useCallback(
    async ({ campaignId }) => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before requesting refunds.");
      if (!wallet?.isConnected) throw new Error("Connect wallet to trigger refund.");

      const signature = await wallet.signMessage(`Request refund on campaign ${campaignId}`);

      const response = await withTx(() =>
        refundCampaignContribution({
          apiBaseUrl,
          token,
          campaignId,
          payload: {
            contributor_wallet: wallet.address,
            signature,
          },
        })
      );
      await loadCampaigns();
      return response;
    },
    [apiBaseUrl, loadCampaigns, token, wallet, withTx]
  );

  const commentsFlow = useCallback(
    async (campaignId) => {
      if (!apiBaseUrl) return { data: [] };
      return fetchCampaignComments({ apiBaseUrl, token, campaignId });
    },
    [apiBaseUrl, token]
  );

  const createCommentFlow = useCallback(
    async ({ campaignId, body, type = "COMMENT", parentId = null }) => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before posting comments.");
      const response = await createCampaignComment({
        apiBaseUrl,
        token,
        campaignId,
        payload: { body, type, parent_id: parentId },
      });
      await loadCampaigns();
      return response;
    },
    [apiBaseUrl, loadCampaigns, token]
  );

  const reportCommentFlow = useCallback(
    async ({ commentId, reason = "other" }) => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before reporting comments.");
      return reportCampaignComment({ apiBaseUrl, token, commentId, reason });
    },
    [apiBaseUrl, token]
  );

  const creatorDashboardFlow = useCallback(
    async () => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before loading creator dashboard.");
      return fetchCreatorCrowdfundingDashboard({ apiBaseUrl, token });
    },
    [apiBaseUrl, token]
  );

  const backerDashboardFlow = useCallback(
    async () => {
      if (!apiBaseUrl) throw new Error("Set VITE_API_URL before loading pledge history.");
      return fetchBackerCrowdfundingDashboard({ apiBaseUrl, token });
    },
    [apiBaseUrl, token]
  );

  const campaignContext = useCallback(
    async (campaignId) => {
      if (!apiBaseUrl || dataSource === "mock") {
        return byId[campaignId] || null;
      }

      const [campaignPayload, logsPayload] = await Promise.all([
        fetchCampaignDetails({ apiBaseUrl, token, campaignId }),
        fetchCampaignLogs({ apiBaseUrl, token, campaignId }),
      ]);

      const row = normalizeCampaign(campaignPayload?.data || campaignPayload || {});
      row.logs = toArrayPayload(logsPayload);
      return row;
    },
    [apiBaseUrl, byId, dataSource, token]
  );

  return {
    campaigns,
    byId,
    loading,
    error,
    txState,
    dataSource,
    refresh: loadCampaigns,
    campaignContext,
    createCampaignFlow,
    contributeFlow,
    commentsFlow,
    createCommentFlow,
    createRequestFlow,
    voteRequestFlow,
    finalizeRequestFlow,
    refundFlow,
    reportCommentFlow,
    creatorDashboardFlow,
    backerDashboardFlow,
  };
}

