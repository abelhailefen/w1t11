import { Button, Form, Input, Modal, Space, Table, message } from 'antd';
import { useEffect, useState } from 'react';
import { practitionerApi, FirmItem } from '../services/practitionerApi';

export function FirmManagementPage() {
  const [items, setItems] = useState<FirmItem[]>([]);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<FirmItem | null>(null);
  const [form] = Form.useForm();

  const load = async () => {
    const response = await practitionerApi.listFirms();
    setItems(response.data.items || []);
  };

  useEffect(() => {
    load();
  }, []);

  const save = async () => {
    const values = await form.validateFields();
    if (editing) {
      await practitionerApi.updateFirm(editing.id, values);
      message.success('Firm updated');
    } else {
      await practitionerApi.createFirm(values);
      message.success('Firm created');
    }
    setOpen(false);
    setEditing(null);
    form.resetFields();
    await load();
  };

  const deactivate = async (id: number) => {
    await practitionerApi.deactivateFirm(id);
    message.success('Firm deactivated');
    await load();
  };

  return (
    <>
      <Button type="primary" onClick={() => setOpen(true)} style={{ marginBottom: 12 }}>
        Add Firm
      </Button>
      <Table<FirmItem>
        rowKey="id"
        dataSource={items}
        columns={[
          { title: 'Name', dataIndex: 'name' },
          { title: 'Address', dataIndex: 'address' },
          { title: 'Status', dataIndex: 'status' },
          {
            title: 'Actions',
            render: (_, row) => (
              <Space>
                <Button
                  onClick={() => {
                    setEditing(row);
                    setOpen(true);
                    form.setFieldsValue(row);
                  }}
                >
                  Edit
                </Button>
                <Button danger onClick={() => deactivate(row.id)}>
                  Deactivate
                </Button>
              </Space>
            ),
          },
        ]}
      />

      <Modal open={open} onOk={save} onCancel={() => setOpen(false)} title={editing ? 'Edit Firm' : 'Add Firm'}>
        <Form form={form} layout="vertical">
          <Form.Item name="name" label="Name" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item name="address" label="Address">
            <Input />
          </Form.Item>
          <Form.Item name="status" label="Status">
            <Input placeholder="ACTIVE or INACTIVE" />
          </Form.Item>
        </Form>
      </Modal>
    </>
  );
}
