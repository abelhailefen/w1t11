import { Button, Input, Space, Table } from 'antd';
import { useEffect, useState } from 'react';
import { apiClient } from '../services/apiClient';

export function QuestionTagsPage() {
  const [items, setItems] = useState<any[]>([]);
  const [name, setName] = useState('');
  const load = async () => setItems((await apiClient.get('/api/v1/admin/question-tags')).data.items || []);
  useEffect(() => { load(); }, []);
  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <Space><Input value={name} onChange={(e) => setName(e.target.value)} /><Button onClick={async () => { await apiClient.post('/api/v1/admin/question-tags', { name }); setName(''); await load(); }}>Add</Button></Space>
      <Table rowKey="id" dataSource={items} columns={[{ title: 'Name', dataIndex: 'name' }, { title: 'Actions', render: (_, r) => <Button danger onClick={async () => { await apiClient.delete(`/api/v1/admin/question-tags/${r.id}`); await load(); }}>Delete</Button> }]} />
    </Space>
  );
}
