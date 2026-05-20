import { useCallback, useMemo, useState } from "react";

function shortAddress(address) {
  if (!address) return "";
  return `${address.slice(0, 6)}...${address.slice(-4)}`;
}

export function useWeb3Wallet() {
  const [address, setAddress] = useState("");
  const [chainId, setChainId] = useState("");
  const [status, setStatus] = useState("idle");
  const [error, setError] = useState("");

  const isConnected = Boolean(address);

  const connectMetaMask = useCallback(async () => {
    if (!window.ethereum) {
      setError("MetaMask was not detected. Install MetaMask to continue.");
      setStatus("error");
      return null;
    }

    setStatus("connecting");
    setError("");
    try {
      const accounts = await window.ethereum.request({ method: "eth_requestAccounts" });
      const connectedAddress = Array.isArray(accounts) ? accounts[0] : "";
      const activeChainId = await window.ethereum.request({ method: "eth_chainId" });
      setAddress(connectedAddress || "");
      setChainId(activeChainId || "");
      setStatus("connected");
      return connectedAddress || null;
    } catch (err) {
      setStatus("error");
      setError(err?.message || "Wallet connection failed.");
      return null;
    }
  }, []);

  const connectWalletConnect = useCallback(() => {
    setError("WalletConnect SDK is not configured in this frontend build yet.");
    setStatus("error");
    return null;
  }, []);

  const signMessage = useCallback(
    async (message) => {
      if (!window.ethereum || !address) {
        throw new Error("Connect wallet first.");
      }

      const signature = await window.ethereum.request({
        method: "personal_sign",
        params: [message, address],
      });

      return signature;
    },
    [address]
  );

  const disconnect = useCallback(() => {
    setAddress("");
    setChainId("");
    setStatus("idle");
    setError("");
  }, []);

  return useMemo(
    () => ({
      address,
      shortAddress: shortAddress(address),
      chainId,
      status,
      error,
      isConnected,
      connectMetaMask,
      connectWalletConnect,
      signMessage,
      disconnect,
    }),
    [address, chainId, connectMetaMask, connectWalletConnect, disconnect, error, isConnected, signMessage, status]
  );
}

