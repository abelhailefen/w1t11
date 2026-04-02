import { Button, Form, Input, Modal, Select, Space, Table, message } from 'antd';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { practitionerApi, PractitionerListItem } from '../services/practitionerApi';

export function PractitionerListPage() {
  const [items, setItems] = useState<PractitionerListItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [filters, setFilters] = useState({ name: '', status: '', firm_id: '' });
  const [openCreate, setOpenCreate] = useState(false);
  const [creating, setCreating] = useState(false);
  const [form] = Form.useForm();

  const load = async (nextPage = page) => {
    setLoading(true);
    try {
      const response = await practitionerApi.listPractitioners({
        page: nextPage,
        limit: 10,
        name: filters.name,
        status: filters.status,
        firm_id: filters.firm_id,
      });
      setItems(response.data.items || []);
      setTotal(response.data.pagination?.total || 0);
      setPage(nextPage);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load(1);
  }, []);

  const createPractitioner = async () => {
    const values = await form.validateFields();
    setCreating(true);
    try {
      await practitionerApi.createPractitioner(values);
      message.success('Practitioner created');
      setOpenCreate(false);
      form.resetFields();
      await load(1);
    } finally {
      setCreating(false);
    }
  };

  return (
    <>
      <Space style={{ marginBottom: 16 }}>
        <Input placeholder="Search name" value={filters.name} onChange={(e) => setFilters((f) => ({ ...f, name: e.target.value }))} />
        <Select
          style={{ width: 180 }}
          placeholder="Status"
          allowClear
          value={filters.status || undefined}
          options={[
            { label: 'ACTIVE', value: 'ACTIVE' },
            { label: 'INACTIVE', value: 'INACTIVE' },
            { label: 'SUSPENDED', value: 'SUSPENDED' },
          ]}
          onChange={(value) => setFilters((f) => ({ ...f, status: value || '' }))}
        />
        <Input
          placeholder="Firm ID"
          value={filters.firm_id}
          onChange={(e) => setFilters((f) => ({ ...f, firm_id: e.target.value }))}
          style={{ width: 120 }}
        />
        <Button onClick={() => load(1)}>Apply</Button>
        <Button type="primary" onClick={() => setOpenCreate(true)}>
          Add Practitioner
        </Button>
      </Space>

      <Table<PractitionerListItem>
        rowKey="id"
        loading={loading}
        dataSource={items}
        scroll={{ x: 'max-content' }}
        pagination={{ current: page, pageSize: 10, total, onChange: (p) => load(p) }}
        columns={[
          { title: 'Name', dataIndex: 'full_name' },
          { title: 'Firm', render: (_, r) => r.firm?.name },
          { title: 'License', dataIndex: 'license_number' },
          { title: 'Jurisdiction', dataIndex: 'license_jurisdiction' },
          { title: 'Status', dataIndex: 'status' },
          { title: 'Actions', render: (_, r) => <Link to={`/practitioners/${r.id}`}>View</Link> },
        ]}
      />

      <Modal open={openCreate} title="Add Practitioner" onOk={createPractitioner} okButtonProps={{ loading: creating, disabled: creating }} onCancel={() => setOpenCreate(false)} destroyOnClose>
        <Form layout="vertical" form={form}>
          <Form.Item name="full_name" label="Full Name" rules={[{ required: true, message: 'Name is required' }]} validateTrigger="onBlur">
            <Input />
          </Form.Item>
          <Form.Item name="firm_id" label="Firm ID" rules={[{ required: true, message: 'Firm is required' }]} validateTrigger="onBlur">
            <Input type="number" />
          </Form.Item>
          <Form.Item name="license_number" label="License Number" rules={[{ required: true, message: 'License number is required' }]} validateTrigger="onBlur">
            <Input />
          </Form.Item>
          <Form.Item name="license_jurisdiction" label="Jurisdiction" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item name="contact_email" label="Email">
            <Input />
          </Form.Item>
          <Form.Item name="contact_phone" label="Phone">
            <Input />
          </Form.Item>
        </Form>
      </Modal>
    </>
  );
}
