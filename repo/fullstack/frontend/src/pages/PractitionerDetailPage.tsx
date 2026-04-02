import { Button, Card, Descriptions, Form, Input, Space, Table, Upload, message } from 'antd';
import { UploadOutlined } from '@ant-design/icons';
import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { LicenseRevealModal } from '../components/LicenseRevealModal';
import { useAuth } from '../context/AuthContext';
import { practitionerApi, PractitionerListItem, CredentialFileItem } from '../services/practitionerApi';

export function PractitionerDetailPage() {
  const { id } = useParams();
  const practitionerId = Number(id);
  const auth = useAuth();
  const [item, setItem] = useState<PractitionerListItem | null>(null);
  const [files, setFiles] = useState<CredentialFileItem[]>([]);
  const [openReveal, setOpenReveal] = useState(false);
  const [loadingReveal, setLoadingReveal] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form] = Form.useForm();

  const canReveal = auth.hasRole(['ROLE_CREDENTIAL_REVIEWER', 'ROLE_SYSTEM_ADMIN']);

  const load = async () => {
    const [detail, fileResp] = await Promise.all([
      practitionerApi.getPractitioner(practitionerId),
      practitionerApi.listCredentials(practitionerId),
    ]);
    setItem(detail.data);
    setFiles(fileResp.data.items || []);
    form.setFieldsValue(detail.data);
  };

  useEffect(() => {
    if (!Number.isNaN(practitionerId)) {
      load();
    }
  }, [practitionerId]);

  const save = async () => {
    const values = await form.validateFields();
    setSaving(true);
    try {
      await practitionerApi.updatePractitioner(practitionerId, values);
      message.success('Practitioner updated');
      await load();
    } finally {
      setSaving(false);
    }
  };

  const reveal = async () => {
    setLoadingReveal(true);
    try {
      const response = await practitionerApi.revealLicense(practitionerId, 'Detail page manual reveal');
      return response.data.license_number as string;
    } finally {
      setLoadingReveal(false);
    }
  };

  const downloadFile = async (file: CredentialFileItem) => {
    const response = await practitionerApi.downloadCredential(practitionerId, file.id);
    const url = window.URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = file.original_name;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  };

  return (
    <Space direction="vertical" style={{ width: '100%' }} size="large">
      <Card title="Practitioner Details">
        <Descriptions column={1} size="small">
          <Descriptions.Item label="Name">{item?.full_name}</Descriptions.Item>
          <Descriptions.Item label="Firm">{item?.firm?.name}</Descriptions.Item>
          <Descriptions.Item label="License">{item?.license_number}</Descriptions.Item>
          <Descriptions.Item label="Jurisdiction">{item?.license_jurisdiction}</Descriptions.Item>
          <Descriptions.Item label="Status">{item?.status}</Descriptions.Item>
        </Descriptions>
        {canReveal ? (
          <Button style={{ marginTop: 12 }} onClick={() => setOpenReveal(true)}>
            Reveal
          </Button>
        ) : null}
      </Card>

      <Card title="Edit Practitioner">
        <Form form={form} layout="vertical">
          <Form.Item name="full_name" label="Full Name" rules={[{ required: true, message: 'Name is required' }]} validateTrigger="onBlur">
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
          <Button type="primary" onClick={save} loading={saving} disabled={saving}>
            Save
          </Button>
        </Form>
      </Card>

      <Card title="Credential Files">
        <Upload
          customRequest={async ({ file, onSuccess, onError }) => {
            try {
              await practitionerApi.uploadCredential(practitionerId, file as File);
              onSuccess?.({});
              message.success('Upload complete');
              await load();
            } catch (error) {
              onError?.(error as Error);
              message.error('Upload failed');
            }
          }}
          showUploadList={false}
        >
          <Button icon={<UploadOutlined />}>Upload file</Button>
        </Upload>

        <Table<CredentialFileItem>
          style={{ marginTop: 12 }}
          rowKey="id"
          dataSource={files}
          pagination={false}
          columns={[
            { title: 'File', dataIndex: 'original_name' },
            { title: 'MIME', dataIndex: 'mime_type' },
            { title: 'Size', dataIndex: 'size_bytes' },
            {
              title: 'Download',
              render: (_, file) => (
                <Button type="link" onClick={() => downloadFile(file)}>
                  Download
                </Button>
              ),
            },
          ]}
        />
      </Card>

      <LicenseRevealModal
        open={openReveal}
        loading={loadingReveal}
        onCancel={() => setOpenReveal(false)}
        onConfirm={reveal}
      />
    </Space>
  );
}
