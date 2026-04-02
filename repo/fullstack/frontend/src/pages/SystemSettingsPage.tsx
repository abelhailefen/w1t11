import { Button, Card, Form, InputNumber, message } from 'antd';
import { useEffect, useState } from 'react';
import { systemSettingsApi } from '../services/systemSettingsApi';

const fields = [
  { key: 'appointment_slot_minutes', label: 'Appointment slot duration (minutes)', min: 5, max: 180 },
  { key: 'appointment_hold_minutes', label: 'Appointment hold timeout (minutes)', min: 1, max: 60 },
  { key: 'duplicate_similarity_threshold', label: 'Duplicate detection threshold (%)', min: 1, max: 100 },
  { key: 'alert_rejection_threshold', label: 'Alert rejection threshold (count)', min: 1, max: 1000 },
  { key: 'alert_rejection_window_hours', label: 'Alert rejection window (hours)', min: 1, max: 168 },
  { key: 'login_lockout_attempts', label: 'Login lockout attempts (count)', min: 1, max: 20 },
  { key: 'login_lockout_duration_minutes', label: 'Login lockout duration (minutes)', min: 1, max: 120 },
];

export function SystemSettingsPage() {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const response = await systemSettingsApi.getAll();
      const items = response.data.items || {};
      const values: Record<string, number> = {};
      fields.forEach((field) => {
        values[field.key] = Number(items[field.key] ?? 0);
      });
      form.setFieldsValue(values);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const save = async () => {
    const values = await form.validateFields();
    const payload: Record<string, string> = {};
    fields.forEach((field) => {
      payload[field.key] = String(values[field.key]);
    });
    setSaving(true);
    try {
      await systemSettingsApi.updateAll(payload);
      message.success('System settings updated');
      await load();
    } finally {
      setSaving(false);
    }
  };

  return (
    <Card title="System Settings" loading={loading}>
      <Form form={form} layout="vertical">
        {fields.map((field) => (
          <Form.Item key={field.key} name={field.key} label={field.label} rules={[{ required: true }]} validateTrigger="onBlur">
            <InputNumber style={{ width: '100%' }} min={field.min} max={field.max} />
          </Form.Item>
        ))}
        <Button type="primary" onClick={save} loading={saving} disabled={saving}>Save Settings</Button>
      </Form>
    </Card>
  );
}
