import { Button, Select, Space, Table, message } from 'antd';
import { useEffect, useState } from 'react';
import { appointmentApi } from '../services/appointmentApi';

export function AppointmentListPage() {
  const [items, setItems] = useState<any[]>([]);
  const [rescheduleSlotId, setRescheduleSlotId] = useState<number>(0);

  const load = async () => {
    const response = await appointmentApi.listAppointments();
    setItems(response.data.items || []);
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <Table
      rowKey="id"
      dataSource={items}
      columns={[
        { title: 'Practitioner', render: (_, row) => row.practitioner?.full_name },
        { title: 'Location', render: (_, row) => row.location?.name },
        { title: 'Date/Time', render: (_, row) => `${row.start_at || ''}` },
        { title: 'Status', dataIndex: 'state' },
        {
          title: 'Actions',
          render: (_, row) => (
            <Space>
              {row.state === 'CONFIRMED' && row.reschedule_count < 2 ? (
                <>
                  <Select style={{ width: 120 }} placeholder="Slot ID" onChange={(v) => setRescheduleSlotId(v)} options={[...Array(20)].map((_, i) => ({ value: i + 1, label: `${i + 1}` }))} />
                  <Button
                    onClick={async () => {
                      if (!rescheduleSlotId) return;
                      await appointmentApi.reschedule(row.id, rescheduleSlotId);
                      message.success('Rescheduled');
                      await load();
                    }}
                  >
                    Reschedule
                  </Button>
                </>
              ) : null}
              {row.state !== 'CANCELLED' ? (
                <Button
                  danger
                  onClick={async () => {
                    await appointmentApi.cancel(row.id);
                    message.success('Cancelled');
                    await load();
                  }}
                >
                  Cancel
                </Button>
              ) : null}
            </Space>
          ),
        },
      ]}
    />
  );
}
