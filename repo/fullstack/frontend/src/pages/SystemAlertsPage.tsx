import { Button, Card, Modal, Select, Space, Table, Tag } from 'antd';
import { useEffect, useState } from 'react';
import { governanceApi } from '../services/governanceApi';

const colorMap: Record<string, string> = { LOW: 'default', MEDIUM: 'gold', HIGH: 'orange', CRITICAL: 'red' };

export function SystemAlertsPage() {
  const [items, setItems] = useState<any[]>([]);
  const [severity, setSeverity] = useState<string | undefined>();
  const [selected, setSelected] = useState<any | null>(null);

  const load = async () => {
    const response = await governanceApi.alerts({ severity, acknowledged: false });
    setItems(response.data.items || []);
  };

  useEffect(() => { load(); }, [severity]);

  return (
    <Card title="System Alerts">
      <Space style={{ marginBottom: 12 }}>
        <Select allowClear placeholder="Severity" style={{ width: 180 }} value={severity} onChange={setSeverity} options={['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'].map((s) => ({ value: s, label: s }))} />
      </Space>
      <Table
        rowKey={(r) => String(r.id)}
        dataSource={items}
        scroll={{ x: 'max-content' }}
        columns={[
          { title: 'Triggered', dataIndex: 'triggered_at' },
          { title: 'Type', dataIndex: 'alert_type' },
          { title: 'Severity', dataIndex: 'severity', render: (v) => <Tag color={colorMap[v] || 'default'}>{v}</Tag> },
          { title: 'Message', dataIndex: 'message' },
          { title: 'Action', render: (_, r) => <Space><Button onClick={() => setSelected(r)}>Details</Button><Button type="primary" onClick={async () => { await governanceApi.acknowledgeAlert(r.id); await load(); }}>Acknowledge</Button></Space> },
        ]}
      />
      <Modal open={Boolean(selected)} onCancel={() => setSelected(null)} footer={null} title="Alert Context">
        <pre style={{ margin: 0 }}>{selected?.context_json ? JSON.stringify(JSON.parse(selected.context_json), null, 2) : '{}'}</pre>
      </Modal>
    </Card>
  );
}
