import { useEffect } from "react";
import { useAuth } from "../../context/AuthContext";

const DEFAULT_WIDGET_URL = "https://embed.tawk.to/6af755a0170fb23e0bba3e60/1k7b2d02k";
const SCRIPT_ID = "exaearn-tawk-to-widget";
let widgetPromise;

function getWidgetUrl() {
  const value = import.meta.env.VITE_TAWK_WIDGET_URL?.trim() || DEFAULT_WIDGET_URL;
  try {
    const url = new URL(value);
    return url.protocol === "https:" && url.hostname === "embed.tawk.to" ? url.href : "";
  } catch {
    return "";
  }
}

function getVisitor(user) {
  const email = typeof user?.email === "string" ? user.email.trim() : "";
  if (!email || !email.includes("@")) return null;
  const name = typeof user?.name === "string" ? user.name.trim() : "";
  return { email, ...(name ? { name } : {}) };
}

function applyVisitor(user) {
  const visitor = getVisitor(user);
  const api = window.Tawk_API;
  if (!visitor || !api) return;
  api.visitor = visitor;
  if (typeof api.setAttributes === "function") api.setAttributes(visitor, () => {});
}

export function loadTawkWidget() {
  if (typeof window === "undefined") return Promise.reject(new Error("Live Support is available in a browser only."));
  if (widgetPromise) return widgetPromise;
  const url = getWidgetUrl();
  if (!url) return Promise.reject(new Error("Live Support is not configured. Set VITE_TAWK_WIDGET_URL to the approved tawk.to embed URL."));

  widgetPromise = new Promise((resolve, reject) => {
    const finish = () => {
      window.clearTimeout(timeoutId);
      resolve();
    };
    const fail = () => {
      window.clearTimeout(timeoutId);
      reject(new Error("Live Support could not be loaded. Please check your connection and try again."));
    };
    const timeoutId = window.setTimeout(fail, 15000);
    window.Tawk_API = window.Tawk_API || {};
    const previousOnLoad = window.Tawk_API.onLoad;
    window.Tawk_API.onLoad = () => {
      if (typeof previousOnLoad === "function") previousOnLoad();
      finish();
    };
    window.Tawk_LoadStart = new Date();

    const existingScript = document.getElementById(SCRIPT_ID);
    if (existingScript) {
      if (typeof window.Tawk_API.maximize === "function") finish();
      else existingScript.addEventListener("error", fail, { once: true });
      return;
    }
    const script = document.createElement("script");
    script.id = SCRIPT_ID;
    script.async = true;
    script.src = url;
    script.charset = "UTF-8";
    script.crossOrigin = "anonymous";
    script.addEventListener("error", fail, { once: true });
    document.head.appendChild(script);
  }).catch((error) => {
    widgetPromise = undefined;
    throw error;
  });
  return widgetPromise;
}

export async function openLiveSupport() {
  await loadTawkWidget();
  if (typeof window.Tawk_API?.maximize !== "function") throw new Error("Live Support is still preparing. Please try again in a moment.");
  window.Tawk_API.maximize();
}

export default function TawkToChat() {
  const { user } = useAuth();
  useEffect(() => {
    applyVisitor(user);
    void loadTawkWidget().then(() => applyVisitor(user)).catch(() => {});
  }, [user]);
  useEffect(() => {
    if (!user) window.Tawk_API?.logout?.();
  }, [user]);
  return null;
}