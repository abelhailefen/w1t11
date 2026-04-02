import { Button, Card, Select, Space, Table, Tabs } from 'antd';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ImportExportPanel } from '../components/ImportExportPanel';
import { questionApi } from '../services/questionApi';

export function QuestionBankPage() {
  const [items, setItems] = useState<any[]>([]);
  const [status, setStatus] = useState('');
  const [category, setCategory] = useState<number | undefined>();
  const [tag, setTag] = useState<number | undefined>();
  const [categories, setCategories] = useState<any[]>([]);
  const [tags, setTags] = useState<any[]>([]);

  const load = async () => {
    const [list, c, t] = await Promise.all([
      questionApi.list({ status: status || undefined, category, tag, page: 1 }),
      questionApi.listCategories(),
      questionApi.listTags(),
    ]);
    setItems(list.data.items || []);
    setCategories(c.data.items || []);
    setTags(t.data.items || []);
  };

  useEffect(() => { load(); }, [status, category, tag]);

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <Card title="Question Bank" extra={<Link to="/questions/new"><Button type="primary">New Question</Button></Link>}>
        <Space style={{ marginBottom: 12 }} wrap>
          <Tabs activeKey={status} onChange={setStatus} items={[{ key: '', label: 'ALL' }, { key: 'DRAFT', label: 'DRAFT' }, { key: 'PUBLISHED', label: 'PUBLISHED' }, { key: 'OFFLINE', label: 'OFFLINE' }]} />
          <Select allowClear placeholder="Category" style={{ width: 180 }} value={category} onChange={setCategory} options={categories.map((c) => ({ label: c.name, value: c.id }))} />
          <Select allowClear placeholder="Tag" style={{ width: 180 }} value={tag} onChange={setTag} options={tags.map((t) => ({ label: t.name, value: t.id }))} />
        </Space>
        <Table rowKey="id" dataSource={items} scroll={{ x: 'max-content' }} columns={[
          { title: 'ID', dataIndex: 'id' },
          { title: 'Preview', dataIndex: 'preview' },
          { title: 'Category', dataIndex: 'category_name' },
          { title: 'Difficulty', dataIndex: 'difficulty' },
          { title: 'Status', dataIndex: 'status' },
          { title: 'Tags', render: (_, r) => (r.tags || []).join(', ') },
          { title: 'Actions', render: (_, r) => <Link to={`/questions/${r.id}`}>Edit</Link> },
        ]} />
      </Card>
      <ImportExportPanel />
    </Space>
  );
}
