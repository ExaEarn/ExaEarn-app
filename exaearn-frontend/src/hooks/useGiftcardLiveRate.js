import { useCallback, useEffect, useMemo, useState } from "react";
import { fetchGiftcardRate, lockGiftcardRate } from "../services/giftcardApi";

function normalizeBrand(brand) {
  return String(brand || "").trim().toLowerCase().replace(/\s+/g, "_");
}

export function useGiftcardLiveRate({ apiBaseUrl, token, brand, value, transactionType }) {
  const [rate, setRate] = useState(null);
  const [rateLock, setRateLock] = useState(null);
  const [secondsRemaining, setSecondsRemaining] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const normalizedBrand = useMemo(() => normalizeBrand(brand), [brand]);
  const numericValue = Number(value) > 0 ? Number(value) : 0;

  const refreshRate = useCallback(async () => {
    if (!apiBaseUrl || !normalizedBrand || numericValue <= 0) {
      setRate(null);
      return null;
    }

    setLoading(true);
    setError("");
    try {
      const payload = await fetchGiftcardRate({
        apiBaseUrl,
        token,
        brand: normalizedBrand,
        value: numericValue,
      });
      const nextRate = payload?.data || null;
      setRate(nextRate);
      return nextRate;
    } catch (requestError) {
      setError(requestError.message || "Unable to fetch best rate.");
      return null;
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl, normalizedBrand, numericValue, token]);

  const lockRate = useCallback(async () => {
    if (!normalizedBrand || numericValue <= 0) {
      throw new Error("Select a valid giftcard and amount before locking a rate.");
    }

    const payload = await lockGiftcardRate({
      apiBaseUrl,
      token,
      brand: normalizedBrand,
      value: numericValue,
      transactionType,
    });
    const lock = payload?.data;
    setRateLock(lock);
    setRate(lock?.rates || rate);
    setSecondsRemaining(lock?.seconds_remaining || lock?.rates?.lock_duration || 0);
    return lock;
  }, [apiBaseUrl, normalizedBrand, numericValue, rate, token, transactionType]);

  useEffect(() => {
    setRateLock(null);
    setSecondsRemaining(0);
    refreshRate();
  }, [refreshRate]);

  useEffect(() => {
    const interval = window.setInterval(() => {
      if (!rateLock) {
        refreshRate();
      }
    }, 30000);

    return () => window.clearInterval(interval);
  }, [rateLock, refreshRate]);

  useEffect(() => {
    if (!rateLock) {
      return undefined;
    }

    const interval = window.setInterval(() => {
      setSecondsRemaining((current) => {
        if (current <= 1) {
          setRateLock(null);
          refreshRate();
          return 0;
        }
        return current - 1;
      });
    }, 1000);

    return () => window.clearInterval(interval);
  }, [rateLock, refreshRate]);

  return {
    rate,
    rateLock,
    secondsRemaining,
    loading,
    error,
    refreshRate,
    lockRate,
    isLocked: Boolean(rateLock && secondsRemaining > 0),
  };
}

export function formatNaira(value) {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

export function formatLockTime(seconds) {
  const safeSeconds = Math.max(0, Number(seconds || 0));
  const mins = Math.floor(safeSeconds / 60).toString().padStart(2, "0");
  const secs = Math.floor(safeSeconds % 60).toString().padStart(2, "0");
  return `${mins}:${secs}`;
}
