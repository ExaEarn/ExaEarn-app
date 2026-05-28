import axios from "axios";
import { getAdminApiBaseUrl } from "../config/apiConfig";

const apiBaseUrl = getAdminApiBaseUrl();
const baseURL = apiBaseUrl.includes("/api/admin")
  ? apiBaseUrl
  : !apiBaseUrl
  ? "/api/admin"
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
