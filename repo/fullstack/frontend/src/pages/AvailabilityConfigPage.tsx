import { Button, DatePicker, Form, InputNumber, Select, Space, Table, message } from 'antd';
import { useEffect, useState } from 'react';
import { appointmentApi } from '../services/appointmentApi';

export function AvailabilityConfigPage() {
  const [items, setItems] = useState<any[]>([]);
  const [form] = Form.useForm();
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const load = async () => {
    const response = await appointmentApi.listAvailability();
    setItems(response.data.items || []);
  };

  useEffect(() => {
    load();
  }, []);

  const addWindow = async () => {
    const values = await form.validateFields();
    await appointmentApi.updateAvailability([values]);
    message.success('Availability saved');
    form.resetFields();
    await load();
  };

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <Form layout="inline" form={form}>
        <Form.Item name="practitioner_id" rules={[{ required: true }]}>
          <InputNumber placeholder="Practitioner ID" min={1} />
        </Form.Item>
        <Form.Item name="weekday" rules={[{ required: true }]}>
          <Select style={{ width: 120 }} options={[0, 1, 2, 3, 4, 5, 6].map((d) => ({ label: `Day ${d}`, value: d }))} />
        </Form.Item>
        <Form.Item name="start_time" rules={[{ required: true }]}>
          <Select style={{ width: 120 }} options={[{ value: '09:00:00', label: '09:00' }, { value: '13:00:00', label: '13:00' }]} />
        </Form.Item>
        <Form.Item name="end_time" rules={[{ required: true }]}>
          <Select style={{ width: 120 }} options={[{ value: '12:00:00', label: '12:00' }, { value: '17:00:00', label: '17:00' }]} />
        </Form.Item>
        <Form.Item name="slot_minutes">
          <InputNumber min={15} max={60} placeholder="Slot mins" />
        </Form.Item>
        <Button type="primary" onClick={addWindow}>Save Window</Button>
      </Form>

      <Space>
        <DatePicker onChange={(_, d) => setFrom(d)} placeholder="From" />
        <DatePicker onChange={(_, d) => setTo(d)} placeholder="To" />
        <Button
          onClick={async () => {
            await appointmentApi.generateSlots(from, to);
            message.success('Slots generated');
          }}
        >
          Generate Slots
        </Button>
      </Space>

      <Table
        rowKey="id"
        dataSource={items}
        columns={[
          { title: 'Practitioner', dataIndex: 'practitioner_id' },
          { title: 'Weekday', dataIndex: 'weekday' },
          { title: 'Start', dataIndex: 'start_time' },
          { title: 'End', dataIndex: 'end_time' },
          { title: 'Slot Min', dataIndex: 'slot_minutes' },
        ]}
      />
    </Space>
  );
}
