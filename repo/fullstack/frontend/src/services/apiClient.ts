import axios from 'axios';
import { getToken } from './authStore';

let unauthorizedHandler: (() => void) | null = null;

export function setUnauthorizedHandler(handler: () => void) {
  unauthorizedHandler = handler;
}

const apiBaseUrl =
  (typeof process !== 'undefined' ? process.env?.VITE_API_BASE_URL : undefined) ||
  'http://localhost:8000';

export const apiClient = axios.create({
  baseURL: apiBaseUrl,
  timeout: 10000,
});

apiClient.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401 && unauthorizedHandler) {
      unauthorizedHandler();
    }

    return Promise.reject(error);
  }
);
