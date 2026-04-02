import { apiClient } from './apiClient';

export type PractitionerListItem = {
  id: number;
  full_name: string;
  firm: { id: number; name: string };
  license_number: string;
  license_jurisdiction: string;
  contact_email?: string | null;
  contact_phone?: string | null;
  status: string;
  created_at: string;
  updated_at: string;
};

export type FirmItem = {
  id: number;
  name: string;
  address?: string | null;
  status: string;
  created_at: string;
};

export type CredentialFileItem = {
  id: number;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  uploaded_at: string;
};

export const practitionerApi = {
  listPractitioners: (params: Record<string, string | number>) => apiClient.get('/api/v1/practitioners', { params }),
  getPractitioner: (id: number) => apiClient.get<PractitionerListItem>(`/api/v1/practitioners/${id}`),
  createPractitioner: (payload: Record<string, any>) => apiClient.post('/api/v1/practitioners', payload),
  updatePractitioner: (id: number, payload: Record<string, any>) => apiClient.patch(`/api/v1/practitioners/${id}`, payload),
  revealLicense: (id: number, reason: string) => apiClient.post(`/api/v1/practitioners/${id}/license/reveal`, { reason }),
  uploadCredential: (id: number, file: File) => {
    const form = new FormData();
    form.append('file', file);
    return apiClient.post(`/api/v1/practitioners/${id}/credentials/upload`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  listCredentials: (id: number) => apiClient.get<{ items: CredentialFileItem[] }>(`/api/v1/practitioners/${id}/credentials`),
  downloadCredential: (id: number, fileId: number) =>
    apiClient.get(`/api/v1/practitioners/${id}/credentials/${fileId}/download`, { responseType: 'blob' }),

  listFirms: () => apiClient.get<{ items: FirmItem[] }>('/api/v1/admin/firms'),
  createFirm: (payload: Record<string, any>) => apiClient.post('/api/v1/admin/firms', payload),
  updateFirm: (id: number, payload: Record<string, any>) => apiClient.patch(`/api/v1/admin/firms/${id}`, payload),
  deactivateFirm: (id: number) => apiClient.delete(`/api/v1/admin/firms/${id}`),
};
