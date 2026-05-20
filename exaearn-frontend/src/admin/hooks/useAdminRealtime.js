import { useEffect, useState } from "react";
import { connectAdminSocket } from "../services/realtime";
import { useAdminStore } from "../store/useAdminStore";

export function useAdminRealtime() {
  const heartbeat = useAdminStore((state) => state.heartbeat);
  const updateHeartbeat = useAdminStore((state) => state.updateHeartbeat);
  const pushNotification = useAdminStore((state) => state.pushNotification);
  const [connectionState, setConnectionState] = useState("connecting");

  useEffect(() => {
    return connectAdminSocket({
      onStateChange: setConnectionState,
      onMessage: (event) => {
        if (event.type === "heartbeat") {
          updateHeartbeat(event.payload);
        }

        if (event.type === "alert" && event.payload) {
          pushNotification({
            id: Date.now(),
            title: event.payload.message,
            level: event.payload.level ?? "info",
            time: "now",
          });
        }
      },
    });
  }, [pushNotification, updateHeartbeat]);

  return { ...heartbeat, connectionState };
}
