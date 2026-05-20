import axios from "axios";

const rawApiBaseUrl = import.meta.env.VITE_ADMIN_API_URL?.trim() || import.meta.env.VITE_API_URL?.trim() || "http://127.0.0.1:8000";
const apiBaseUrl = rawApiBaseUrl.replace(/\/+$/, "");
const baseURL = apiBaseUrl.includes("/api/admin")
  ? apiBaseUrl
  : apiBaseUrl.endsWith("/api")
  ? `${apiBaseUrl}/admin`
  : `${apiBaseUrl}/api/admin`;

export const adminHttp = axios.create({
  baseURL,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

adminHttp.interceptors.request.use((config) => {
  const token = localStorage.getItem("exaearn-admin-token");

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});
