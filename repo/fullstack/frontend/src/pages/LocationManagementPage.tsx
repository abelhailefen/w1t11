import { Button, Form, Input, InputNumber, Modal, Space, Table, message } from 'antd';
import { useEffect, useState } from 'react';
import { appointmentApi } from '../services/appointmentApi';

export function LocationManagementPage() {
  const [items, setItems] = useState<any[]>([]);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<any | null>(null);
  const [form] = Form.useForm();

  const load = async () => {
    const response = await appointmentApi.listLocations();
    setItems(response.data.items || []);
  };

  useEffect(() => {
    load();
  }, []);

  const save = async () => {
    const values = await form.validateFields();
    if (editing) {
      await (await import('../services/apiClient')).apiClient.patch(`/api/v1/admin/locations/${editing.id}`, values);
      message.success('Location updated');
    } else {
      await (await import('../services/apiClient')).apiClient.post('/api/v1/admin/locations', values);
      message.success('Location created');
    }
    setOpen(false);
    setEditing(null);
    form.resetFields();
    await load();
  };

  return (
    <>
      <Button type="primary" onClick={() => setOpen(true)} style={{ marginBottom: 12 }}>Add Location</Button>
      <Table rowKey="id" dataSource={items} columns={[
        { title: 'Name', dataIndex: 'name' },
        { title: 'Address', dataIndex: 'address' },
        { title: 'Capacity', dataIndex: 'capacity' },
        { title: 'Status', dataIndex: 'status' },
        { title: 'Actions', render: (_, row) => (
          <Space>
            <Button onClick={() => { setEditing(row); setOpen(true); form.setFieldsValue(row); }}>Edit</Button>
            <Button danger onClick={async () => { await (await import('../services/apiClient')).apiClient.delete(`/api/v1/admin/locations/${row.id}`); await load(); }}>Deactivate</Button>
          </Space>
        ) },
      ]} />
      <Modal open={open} onOk={save} onCancel={() => setOpen(false)} title={editing ? 'Edit Location' : 'Add Location'}>
        <Form form={form} layout="vertical">
          <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
          <Form.Item name="address" label="Address"><Input /></Form.Item>
          <Form.Item name="capacity" label="Capacity"><InputNumber min={1} style={{ width: '100%' }} /></Form.Item>
        </Form>
      </Modal>
    </>
  );
}
