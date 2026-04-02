import { Form, Input, Modal, Select } from 'antd';

type Props = {
  open: boolean;
  loading?: boolean;
  versions: number[];
  onCancel: () => void;
  onConfirm: (targetVersionNo: number, password: string, justification: string) => Promise<void>;
};

export function RollbackConfirmModal({ open, loading = false, versions, onCancel, onConfirm }: Props) {
  const [form] = Form.useForm();

  return (
    <Modal
      open={open}
      title="Rollback Credential"
      okText="Confirm Rollback"
      confirmLoading={loading}
      onCancel={onCancel}
      onOk={async () => {
        try {
          const values = await form.validateFields();
          await onConfirm(values.target_version_no, values.password, values.justification);
          form.resetFields();
        } catch {
          return;
        }
      }}
    >
      <Form form={form} layout="vertical">
        <Form.Item name="target_version_no" label="Target Version" rules={[{ required: true }]}> 
          <Select options={versions.map((versionNo) => ({ value: versionNo, label: `Version ${versionNo}` }))} />
        </Form.Item>
        <Form.Item name="password" label="Re-enter Password" rules={[{ required: true }]}> 
          <Input.Password />
        </Form.Item>
        <Form.Item name="justification" label="Justification" rules={[{ required: true }, { min: 5 }]}> 
          <Input.TextArea rows={4} />
        </Form.Item>
      </Form>
    </Modal>
  );
}
