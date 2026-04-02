import { apiClient } from './apiClient';

export type CredentialQueueItem = {
  id: number;
  practitioner: {
    id: number;
    full_name: string;
    firm: { id: number; name: string };
  };
  current_state: string;
  created_at: string;
  updated_at: string;
};

export type CredentialVersionItem = {
  id: number;
  version_no: number;
  state: string;
  payload_json: Record<string, unknown> | null;
  rejection_comment?: string | null;
  created_by: { id: number; username: string };
  created_at: string;
  files: Array<{
    id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    uploaded_at: string;
  }>;
};

export const credentialApi = {
  createSubmission: (practitionerId: number, payload: Record<string, unknown> = {}) =>
    apiClient.post('/api/v1/credentials', { practitioner_id: practitionerId, payload }),
  submit: (id: number, payload: Record<string, unknown> = {}) =>
    apiClient.post(`/api/v1/credentials/${id}/submit`, { payload }),
  startReview: (id: number) => apiClient.post(`/api/v1/credentials/${id}/start-review`),
  approve: (id: number) => apiClient.post(`/api/v1/credentials/${id}/approve`),
  reject: (id: number, comment: string) => apiClient.post(`/api/v1/credentials/${id}/reject`, { comment }),
  requestResubmission: (id: number) => apiClient.post(`/api/v1/credentials/${id}/request-resubmission`),
  queue: (state?: string) => apiClient.get<{ items: CredentialQueueItem[] }>('/api/v1/credentials/queue', { params: state ? { state } : {} }),
  versions: (id: number) => apiClient.get<{ submission: CredentialQueueItem; items: CredentialVersionItem[] }>(`/api/v1/credentials/${id}/versions`),
  rollback: (id: number, targetVersionNo: number, password: string, justification: string) =>
    apiClient.post(`/api/v1/credentials/${id}/rollback`, {
      target_version_no: targetVersionNo,
      password,
      justification,
    }),
};
