import { Button, Card, Input, Space, Table } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { governanceApi } from '../services/governanceApi';

export function AuditLogPage() {
  const [items, setItems] = useState<any[]>([]);
  const [actionType, setActionType] = useState('');
  const [entityType, setEntityType] = useState('');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  const load = async () => {
    const response = await governanceApi.auditLogs({ page, limit: 20, action_type: actionType || undefined, entity_type: entityType || undefined });
    setItems(response.data.items || []);
    setTotal(response.data.pagination?.total || 0);
  };

  useEffect(() => { load(); }, [page]);

  const exportCsv = () => {
    const csv = ['id,occurred_at,action_type,entity_type,entity_id,ip_address', ...items.map((i) => [i.id, i.occurred_at, i.action_type, i.entity_type, i.entity_id, i.ip_address].join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'audit-logs.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  const columns = useMemo(() => [
    { title: 'Occurred At', dataIndex: 'occurred_at' },
    { title: 'Action', dataIndex: 'action_type' },
    { title: 'Entity', dataIndex: 'entity_type' },
    { title: 'Entity ID', dataIndex: 'entity_id' },
    { title: 'IP', dataIndex: 'ip_address' },
  ], []);

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <Card title="Audit Logs" extra={<Button onClick={exportCsv}>Export CSV</Button>}>
        <Space style={{ marginBottom: 12 }}>
          <Input placeholder="Action type" value={actionType} onChange={(e) => setActionType(e.target.value)} />
          <Input placeholder="Entity type" value={entityType} onChange={(e) => setEntityType(e.target.value)} />
          <Button onClick={() => { setPage(1); load(); }}>Apply</Button>
        </Space>
        <Table
          rowKey={(record) => String(record.id)}
          dataSource={items}
          columns={columns}
          expandable={{ expandedRowRender: (record) => <pre style={{ margin: 0 }}>{JSON.stringify({ old: record.old_value_json ? JSON.parse(record.old_value_json) : null, new: record.new_value_json ? JSON.parse(record.new_value_json) : null }, null, 2)}</pre> }}
          scroll={{ x: 'max-content' }}
          pagination={{ current: page, pageSize: 20, total, onChange: (p) => setPage(p) }}
        />
      </Card>
    </Space>
  );
}
