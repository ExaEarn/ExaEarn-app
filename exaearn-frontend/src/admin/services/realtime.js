export function connectAdminSocket({ onMessage, onStateChange }) {
  const socketUrl = import.meta.env.VITE_ADMIN_WS_URL?.trim();

  if (socketUrl) {
    const socket = new WebSocket(socketUrl);

    socket.addEventListener("open", () => onStateChange?.("online"));
    socket.addEventListener("close", () => onStateChange?.("closed"));
    socket.addEventListener("error", () => onStateChange?.("error"));
    socket.addEventListener("message", (event) => {
      try {
        onMessage?.(JSON.parse(event.data));
      } catch {
        onMessage?.({ type: "raw", payload: event.data });
      }
    });

    return () => socket.close();
  }

  onStateChange?.("mock");

  const timer = setInterval(() => {
    const statuses = ["online", "warning", "online", "online", "degraded"];

    onMessage?.({
      type: "heartbeat",
      payload: {
        queueDepth: Math.max(6, 18 + Math.round(Math.random() * 6 - 3)),
        websocketClients: Math.max(160, 234 + Math.round(Math.random() * 14 - 7)),
        securityAlerts: Math.max(0, 3 + Math.round(Math.random() * 2 - 1)),
        blockchainLatency: Math.max(120, 201 + Math.round(Math.random() * 20 - 10)),
        feedStatus: statuses[Math.floor(Math.random() * statuses.length)],
      },
    });
  }, 3500);

  return () => clearInterval(timer);
}
