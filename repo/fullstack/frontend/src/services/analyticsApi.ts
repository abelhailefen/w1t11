import { apiClient } from './apiClient';

export const analyticsApi = {
  runQuery: (payload: Record<string, unknown>) => apiClient.post('/api/v1/analytics/query', payload),
  saveQuery: (name: string, queryDefinition: Record<string, unknown>) => apiClient.post('/api/v1/analytics/queries/save', { name, query_definition: queryDefinition }),
  listSavedQueries: () => apiClient.get('/api/v1/analytics/queries'),
  createFeature: (name: string, definition: Record<string, unknown>) => apiClient.post('/api/v1/analytics/features', { name, definition }),
  listFeatures: () => apiClient.get('/api/v1/analytics/features'),
  compliance: (from: string, to: string, orgUnit?: number) => apiClient.get('/api/v1/dashboards/compliance', { params: { from, to, org_unit: orgUnit } }),
  trend: (metric: string, from: string, to: string, interval: string) => apiClient.get('/api/v1/dashboards/trend', { params: { metric, from, to, interval } }),
  distribution: (metric: string, from: string, to: string) => apiClient.get('/api/v1/dashboards/distribution', { params: { metric, from, to } }),
  correlation: (metricX: string, metricY: string, from: string, to: string) => apiClient.get('/api/v1/dashboards/correlation', { params: { metricX, metricY, from, to } }),
  exportCsv: (queryId: number) => apiClient.get('/api/v1/reports/export.csv', { params: { query_id: queryId }, responseType: 'blob' }),
  exportPdf: (from: string, to: string, orgUnit?: number) => apiClient.get('/api/v1/reports/export.pdf', { params: { dashboard: 'compliance', from, to, org_unit: orgUnit }, responseType: 'blob' }),
  listOrgUnits: () => apiClient.get('/api/v1/admin/org-units'),
};
