import { Card, DatePicker, Radio, Select, Space, Tag } from 'antd';
import { useEffect, useState } from 'react';
import { BookingDrawer } from '../components/BookingDrawer';
import { appointmentApi, SlotItem } from '../services/appointmentApi';

export function CalendarWorkbenchPage() {
  const [slots, setSlots] = useState<SlotItem[]>([]);
  const [selectedSlot, setSelectedSlot] = useState<SlotItem | null>(null);
  const [viewMode, setViewMode] = useState<'week' | 'day'>('week');
  const [practitionerId, setPractitionerId] = useState(1);
  const [dateFrom, setDateFrom] = useState(new Date().toISOString().slice(0, 10));
  const [dateTo, setDateTo] = useState(new Date(Date.now() + 6 * 86400000).toISOString().slice(0, 10));

  const load = async () => {
    const response = await appointmentApi.listSlots(practitionerId, dateFrom, dateTo);
    setSlots(response.data.items || []);
  };

  useEffect(() => {
    load();
  }, [practitionerId, dateFrom, dateTo]);

  return (
    <Card title="Calendar Workbench">
      <Space style={{ marginBottom: 16 }} wrap>
        <Radio.Group value={viewMode} onChange={(e) => setViewMode(e.target.value)} options={[{ label: 'Week', value: 'week' }, { label: 'Day', value: 'day' }]} />
        <Select value={practitionerId} onChange={setPractitionerId} style={{ width: 160 }} options={[1, 2, 3, 4, 5].map((id) => ({ label: `Practitioner ${id}`, value: id }))} />
        <DatePicker value={null as any} onChange={(_, date) => setDateFrom(date)} placeholder="From" />
        <DatePicker value={null as any} onChange={(_, date) => setDateTo(date)} placeholder="To" />
      </Space>
      <Space direction="vertical" style={{ width: '100%' }}>
        {slots.map((slot) => (
          <Card key={slot.id} size="small" onClick={() => slot.available_count > 0 && setSelectedSlot(slot)} style={{ cursor: slot.available_count > 0 ? 'pointer' : 'default' }}>
            <Space>
              <Tag color={slot.available_count > 0 ? 'green' : 'default'}>{slot.available_count > 0 ? 'available' : 'full'}</Tag>
              <span>{slot.practitioner.full_name}</span>
              <span>{slot.location.name}</span>
              <span>{new Date(slot.start_at).toLocaleString()}</span>
            </Space>
          </Card>
        ))}
      </Space>
      <BookingDrawer open={!!selectedSlot} slot={selectedSlot} onClose={() => setSelectedSlot(null)} onBooked={load} />
    </Card>
  );
}
