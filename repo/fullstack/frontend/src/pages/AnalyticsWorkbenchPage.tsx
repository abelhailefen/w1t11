import { Button, Card, Form, Input, Modal, Select, Space, Table, message } from 'antd';
import { useEffect, useState } from 'react';
import { analyticsApi } from '../services/analyticsApi';

export function AnalyticsWorkbenchPage() {
  const [entityType, setEntityType] = useState('practitioners');
  const [aggregation, setAggregation] = useState('count');
  const [groupBy, setGroupBy] = useState('');
  const [status, setStatus] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [rows, setRows] = useState<any[]>([]);
  const [savedQueries, setSavedQueries] = useState<any[]>([]);
  const [saveOpen, setSaveOpen] = useState(false);
  const [saveName, setSaveName] = useState('');
  const [lastQueryId, setLastQueryId] = useState<number | null>(null);

  const definition = {
    entity_type: entityType,
    aggregation,
    group_by: groupBy || undefined,
    filters: {
      status: status || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
    },
  } as Record<string, unknown>;

  const run = async () => {
    const response = await analyticsApi.runQuery(definition);
    setRows(response.data.items || []);
  };

  const loadSaved = async () => {
    const response = await analyticsApi.listSavedQueries();
    setSavedQueries(response.data.items || []);
  };

  useEffect(() => {
    loadSaved();
  }, []);

  return (
    <Card title="Analytics Workbench">
      <Form layout="vertical">
        <Space wrap>
          <Form.Item label="Entity Type"><Select value={entityType} onChange={setEntityType} style={{ width: 180 }} options={[
            { value: 'practitioners', label: 'Practitioners' },
            { value: 'credentials', label: 'Credentials' },
            { value: 'appointments', label: 'Appointments' },
            { value: 'questions', label: 'Questions' },
          ]} /></Form.Item>
          <Form.Item label="Aggregation"><Select value={aggregation} onChange={setAggregation} style={{ width: 130 }} options={[{ value: 'count', label: 'count' }, { value: 'avg', label: 'avg' }, { value: 'sum', label: 'sum' }]} /></Form.Item>
          <Form.Item label="Group By"><Input value={groupBy} onChange={(e) => setGroupBy(e.target.value)} placeholder="status" /></Form.Item>
          <Form.Item label="Status"><Input value={status} onChange={(e) => setStatus(e.target.value)} /></Form.Item>
          <Form.Item label="Date From"><Input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} /></Form.Item>
          <Form.Item label="Date To"><Input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} /></Form.Item>
        </Space>
      </Form>
      <Space style={{ marginBottom: 12 }}>
        <Button type="primary" onClick={run}>Run Query</Button>
        <Button onClick={() => setSaveOpen(true)}>Save Query</Button>
        <Select
          style={{ width: 260 }}
          placeholder="Load saved query"
          options={savedQueries.map((q) => ({ value: q.id, label: q.name }))}
          onChange={async (id) => {
            const found = savedQueries.find((q) => q.id === id);
            if (!found) return;
            setLastQueryId(id);
            const response = await analyticsApi.runQuery(found.query_definition || {});
            setRows(response.data.items || []);
          }}
        />
        <Button
          onClick={async () => {
            if (!lastQueryId) return;
            const response = await analyticsApi.exportCsv(lastQueryId);
            const blob = new Blob([response.data]);
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'analytics-query.csv';
            a.click();
            window.URL.revokeObjectURL(url);
          }}
        >
          Export CSV
        </Button>
      </Space>

      <Table rowKey={(_, idx) => String(idx)} dataSource={rows} columns={Object.keys(rows[0] || {}).map((k) => ({ title: k, dataIndex: k }))} />

      <Modal open={saveOpen} onCancel={() => setSaveOpen(false)} onOk={async () => {
        await analyticsApi.saveQuery(saveName || 'Saved Query', definition);
        message.success('Query saved');
        setSaveOpen(false);
        setSaveName('');
        await loadSaved();
      }} title="Save Query">
        <Input value={saveName} onChange={(e) => setSaveName(e.target.value)} placeholder="Query name" />
      </Modal>
    </Card>
  );
}
