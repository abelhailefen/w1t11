import { apiClient } from './apiClient';

export const questionApi = {
  list: (params: Record<string, any>) => apiClient.get('/api/v1/questions', { params }),
  create: (payload: Record<string, any>) => apiClient.post('/api/v1/questions', payload),
  detail: (id: number) => apiClient.get(`/api/v1/questions/${id}`),
  update: (id: number, payload: Record<string, any>) => apiClient.patch(`/api/v1/questions/${id}`, payload),
  publish: (id: number, ackDuplicates = false) => apiClient.post(`/api/v1/questions/${id}/publish`, { ack_duplicates: ackDuplicates }),
  changeStatus: (id: number, status: string) => apiClient.patch(`/api/v1/questions/${id}/status`, { status }),
  versions: (id: number) => apiClient.get(`/api/v1/questions/${id}/versions`),
  rollback: (id: number, targetVersionNo: number, password: string, justification: string) =>
    apiClient.post(`/api/v1/questions/${id}/rollback`, { target_version_no: targetVersionNo, password, justification }),
  importFile: (file: File) => {
    const form = new FormData();
    form.append('file', file);
    return apiClient.post('/api/v1/questions/import', form, { headers: { 'Content-Type': 'multipart/form-data' } });
  },
  exportFile: (params: Record<string, any>) => apiClient.get('/api/v1/questions/export', { params, responseType: 'blob' }),
  listTags: () => apiClient.get('/api/v1/admin/question-tags'),
  listCategories: () => apiClient.get('/api/v1/admin/question-categories'),
};
