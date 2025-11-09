import axios from "axios";

const client = axios.create({
  baseURL: "/api/v1",
  headers: {
    Accept: "application/json",
  },
});

client.interceptors.request.use((config) => {
  if (typeof window !== "undefined") {
    const token = window.localStorage.getItem("token");
    const organizationId = window.localStorage.getItem("organization_id");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    if (organizationId) {
      config.headers["X-Organization-ID"] = organizationId;
    }
  }
  return config;
});

export default client;
