import { apiClient } from './apiClient';

export const governanceApi = {
  auditLogs: (params: Record<string, unknown>) => apiClient.get('/api/v1/audit/logs', { params }),
  alerts: (params: Record<string, unknown> = {}) => apiClient.get('/api/v1/alerts', { params }),
  acknowledgeAlert: (id: number) => apiClient.post(`/api/v1/alerts/${id}/acknowledge`),
};
