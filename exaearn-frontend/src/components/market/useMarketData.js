import { useEffect, useMemo, useState } from "react";
import samplePairs from "../../data/market.sample.json";

export function useMarketData() {
  const [loading, setLoading] = useState(true);
  const [offline, setOffline] = useState(!navigator.onLine);
  const [pairs, setPairs] = useState([]);

  useEffect(() => {
    const timer = setTimeout(() => {
      setPairs(samplePairs);
      setLoading(false);
    }, 650);

    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    const handleOnline = () => setOffline(false);
    const handleOffline = () => setOffline(true);

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);
    return () => {
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, []);

  const enrichedPairs = useMemo(() => {
    return pairs.map((item) => {
      const [base, quote] = item.pair.split("/");
      return {
        ...item,
        base,
        quote,
      };
    });
  }, [pairs]);

  return { loading, offline, pairs: enrichedPairs, setPairs };
}

export default useMarketData;
