import { apiClient } from './apiClient';

export type SlotItem = {
  id: number;
  practitioner: { id: number; full_name: string };
  location: { id: number; name: string };
  start_at: string;
  end_at: string;
  capacity: number;
  available_count: number;
  status: string;
};

export const appointmentApi = {
  listAvailability: () => apiClient.get<{ items: any[] }>('/api/v1/availability'),
  updateAvailability: (windows: any[]) => apiClient.put('/api/v1/availability', { windows }),
  generateSlots: (dateFrom: string, dateTo: string, practitionerId?: number) =>
    apiClient.post('/api/v1/appointments/slots/generate', { date_from: dateFrom, date_to: dateTo, practitioner_id: practitionerId }),
  listSlots: (practitionerId: number, dateFrom: string, dateTo: string) =>
    apiClient.get<{ items: SlotItem[] }>('/api/v1/appointments/slots', {
      params: { practitioner_id: practitionerId, date_from: dateFrom, date_to: dateTo },
    }),
  hold: (slotId: number) => apiClient.post<{ appointment_id: number }>('/api/v1/appointments/hold', { slot_id: slotId }),
  book: (appointmentId: number) => apiClient.post('/api/v1/appointments/book', { appointment_id: appointmentId }),
  reschedule: (appointmentId: number, newSlotId: number) => apiClient.post(`/api/v1/appointments/${appointmentId}/reschedule`, { new_slot_id: newSlotId }),
  cancel: (appointmentId: number) => apiClient.post(`/api/v1/appointments/${appointmentId}/cancel`),
  calendar: (week: string, practitionerId?: number) => apiClient.get('/api/v1/appointments/calendar', { params: { week, practitioner_id: practitionerId } }),
  listAppointments: () => apiClient.get<{ items: any[] }>('/api/v1/appointments'),
  listLocations: () => apiClient.get<{ items: any[] }>('/api/v1/admin/locations'),
};
