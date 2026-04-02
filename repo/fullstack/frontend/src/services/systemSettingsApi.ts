import { apiClient } from './apiClient';

export const systemSettingsApi = {
  getAll: () => apiClient.get('/api/v1/admin/settings'),
  updateAll: (items: Record<string, string>) => apiClient.put('/api/v1/admin/settings', { items }),
};
