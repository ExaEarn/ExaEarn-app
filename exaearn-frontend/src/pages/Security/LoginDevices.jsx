import { useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  Laptop,
  LogOut,
  ShieldCheck,
  Smartphone,
  Tablet,
} from "lucide-react";

const currentDevice = {
  id: "current-1",
  type: "mobile",
  name: "Android 15 - Chrome Browser",
  ip: "102.***.***.44",
  location: "Lagos, Nigeria",
  lastActive: "Just now",
  status: "Active",
};

const initialOtherDevices = [
  {
    id: "dev-1",
    type: "desktop",
    name: "Windows 11 - Edge 133",
    ip: "154.***.***.19",
    location: "London, UK",
    lastActive: "Mar 5, 2026 08:12",
    status: "Active",
  },
  {
    id: "dev-2",
    type: "tablet",
    name: "iPadOS - Safari 18",
    ip: "41.***.***.70",
    location: "Abuja, Nigeria",
    lastActive: "Mar 4, 2026 21:06",
    status: "Idle",
  },
  {
    id: "dev-3",
    type: "desktop",
    name: "Ubuntu - Firefox 136",
    ip: "93.***.***.121",
    location: "Berlin, Germany",
    lastActive: "Mar 4, 2026 17:42",
    status: "Suspicious",
  },
];

const activitySeed = [
  { id: "act-1", event: "Login successful", device: "Android - Chrome", time: "Mar 5, 2026 09:01" },
  { id: "act-2", event: "New device session created", device: "Windows - Edge", time: "Mar 5, 2026 08:12" },
  { id: "act-3", event: "Suspicious login flagged", device: "Ubuntu - Firefox", time: "Mar 4, 2026 17:42" },
];

function LoginDevices({ onBack }) {
  const [devices, setDevices] = useState(initialOtherDevices);
  const [activityLog, setActivityLog] = useState(activitySeed);
  const [confirm, setConfirm] = useState(null);
  const [code, setCode] = useState("");
  const [toast, setToast] = useState("");

  const hasOtherDevices = devices.length > 0;

  const showToast = (message) => {
    setToast(message);
    setTimeout(() => setToast(""), 2200);
  };

  const suspiciousCount = useMemo(() => devices.filter((item) => item.status === "Suspicious").length, [devices]);

  const requestSingleLogout = (device) => {
    setConfirm({ mode: "single", deviceId: device.id, label: `Log out ${device.name}?` });
  };

  const requestLogoutAll = () => {
    setCode("");
    setConfirm({ mode: "all", label: "Log out all other devices?" });
  };

  const performConfirm = () => {
    if (!confirm) return;
    if (confirm.mode === "single") {
      const target = devices.find((item) => item.id === confirm.deviceId);
      setDevices((prev) => prev.filter((item) => item.id !== confirm.deviceId));
      if (target) {
        setActivityLog((prev) => [
          {
            id: `act-${Date.now()}`,
            event: "Session logged out",
            device: target.name,
            time: new Date().toLocaleString(),
          },
          ...prev,
        ]);
      }
      showToast("Device logged out.");
    } else {
      if (code.length < 6) {
        showToast("Enter valid 2FA code.");
        return;
      }
      setDevices([]);
      setActivityLog((prev) => [
        {
          id: `act-${Date.now()}`,
          event: "All other sessions logged out",
          device: "Multiple devices",
          time: new Date().toLocaleString(),
        },
        ...prev,
      ]);
      showToast("All other devices logged out.");
    }
    setConfirm(null);
  };

  return (
    <main className="min-h-screen bg-[#070B14] text-white">
      <header
        className="sticky top-0 z-30 border-b border-[#D4AF37]/20 bg-gradient-to-r from-[#121A2A]/95 via-[#0E1524]/95 to-[#0A0F1D]/95 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-4xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#E6EAF2] hover:border-[#D4AF37]/60"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[#F8F1DE] sm:text-xl">Login Devices</h1>
              <p className="text-xs text-[#B8C0CF] sm:text-sm">Manage active sessions across your devices</p>
            </div>
          </div>
        </div>
      </header>

      <section className="mx-auto w-full max-w-4xl space-y-4 px-4 pb-8 pt-5 sm:px-6">
        <article className="rounded-2xl border border-[#D4AF37]/45 bg-[#101827]/90 p-4 shadow-[0_12px_26px_rgba(0,0,0,0.3)]">
          <p className="mb-3 text-xs uppercase tracking-[0.12em] text-[#F3D88F]">Current Device</p>
          <DeviceRow device={currentDevice} current />
          <p className="mt-2 text-xs text-[#AAB3C3]">This device cannot be removed.</p>
        </article>

        <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-base font-semibold text-[#F8F1DE]">Other Active Devices</h2>
            {suspiciousCount ? <span className="rounded-full border border-[#EF4444]/40 bg-[#EF4444]/10 px-2 py-0.5 text-[11px] text-[#FCA5A5]">{suspiciousCount} suspicious</span> : null}
          </div>
          {hasOtherDevices ? (
            <div className="max-h-72 space-y-2 overflow-y-auto pr-1">
              {devices.map((device) => (
                <div key={device.id} className="rounded-xl border border-white/10 bg-[#0C1424] p-3">
                  <div className="flex items-start justify-between gap-3">
                    <DeviceRow device={device} />
                    <button
                      type="button"
                      onClick={() => requestSingleLogout(device)}
                      className="inline-flex items-center gap-1 rounded-lg border border-[#EF4444]/35 bg-[#EF4444]/10 px-2 py-1 text-xs text-[#FCA5A5] hover:border-[#EF4444]/55"
                    >
                      <LogOut className="h-3.5 w-3.5" />
                      Log Out
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="rounded-xl border border-white/10 bg-[#0C1424] p-5 text-center">
              <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full border border-[#D4AF37]/35 bg-[#D4AF37]/10 text-[#D4AF37]">
                <ShieldCheck className="h-5 w-5" />
              </div>
              <p className="text-sm text-[#D7DDEA]">No other active sessions found.</p>
            </div>
          )}
        </article>

        <article className="rounded-2xl border border-[#EF4444]/35 bg-[#EF4444]/10 p-4">
          <p className="flex items-start gap-2 text-sm text-[#FECACA]">
            <AlertTriangle className="mt-0.5 h-4 w-4 text-[#F87171]" />
            If you see an unknown device, log it out immediately.
          </p>
          <button
            type="button"
            onClick={requestLogoutAll}
            className="mt-3 rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] px-4 py-2 text-sm font-semibold text-[#111827]"
          >
            Log Out All Other Devices
          </button>
        </article>

        <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Activity Log Preview</h2>
          <div className="space-y-2">
            {activityLog.slice(0, 3).map((item) => (
              <div key={item.id} className="rounded-xl border border-white/10 bg-[#0C1424] p-3 text-sm">
                <p className="text-[#DDE3EE]">{item.event}</p>
                <p className="text-xs text-[#98A1B2]">{item.device} • {item.time}</p>
              </div>
            ))}
          </div>
          <button type="button" className="mt-3 rounded-lg border border-white/15 bg-[#111827] px-3 py-1.5 text-xs text-[#D7DDEA]">
            View Full Security Activity Log
          </button>
        </article>
      </section>

      {confirm ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-sm rounded-2xl border border-white/15 bg-[#0E1524] p-4">
            <h3 className="text-base font-semibold text-[#F8F1DE]">Confirm Action</h3>
            <p className="mt-2 text-sm text-[#B8C0CF]">{confirm.label}</p>
            {confirm.mode === "all" ? (
              <div className="mt-3">
                <label className="mb-1 block text-xs text-[#AAB3C3]">2FA Verification Code</label>
                <input
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, "").slice(0, 6))}
                  placeholder="Enter 6-digit code"
                  className="h-10 w-full rounded-xl border border-white/15 bg-[#111827] px-3 text-sm outline-none focus:border-[#D4AF37]/60"
                />
              </div>
            ) : null}
            <div className="mt-4 grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setConfirm(null)}
                className="rounded-lg border border-white/15 bg-[#111827] py-2 text-sm text-[#D7DDEA]"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={performConfirm}
                className="rounded-lg bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] py-2 text-sm font-semibold text-[#111827]"
              >
                Confirm
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {toast ? (
        <div className="fixed right-4 top-24 z-50 rounded-xl border border-[#22C55E]/35 bg-[#22C55E]/12 px-3 py-2 text-xs text-[#BBF7D0] shadow-lg">
          {toast}
        </div>
      ) : null}
    </main>
  );
}

function DeviceRow({ device, current = false }) {
  const Icon = device.type === "desktop" ? Laptop : device.type === "tablet" ? Tablet : Smartphone;
  const statusTone =
    device.status === "Active"
      ? "text-[#86EFAC]"
      : device.status === "Suspicious"
      ? "text-[#FCA5A5]"
      : "text-[#FDE68A]";

  return (
    <div className="flex gap-3">
      <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#D4AF37]/25 bg-[#D4AF37]/10 text-[#D4AF37]">
        <Icon className="h-5 w-5" />
      </span>
      <div className="text-sm">
        <p className="font-medium text-[#E6EAF2]">{device.name}</p>
        <p className="text-xs text-[#98A1B2]">{device.ip} • {device.location}</p>
        <p className="text-xs text-[#98A1B2]">Last Active: {device.lastActive}</p>
        <p className={`mt-1 text-xs font-medium ${statusTone}`}>● {device.status}</p>
        {current ? <p className="text-xs text-[#AAB3C3]">Protected session</p> : null}
      </div>
    </div>
  );
}

export default LoginDevices;
