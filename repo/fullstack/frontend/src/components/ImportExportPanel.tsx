import { Button, Card, Select, Space, Upload, message } from 'antd';
import { useState } from 'react';
import { questionApi } from '../services/questionApi';

export function ImportExportPanel() {
  const [format, setFormat] = useState<'csv' | 'xlsx'>('csv');
  const [results, setResults] = useState<any[]>([]);

  return (
    <Card title="Import / Export">
      <Space direction="vertical" style={{ width: '100%' }}>
        <Upload beforeUpload={async (file) => { const r = await questionApi.importFile(file as File); setResults(r.data.results || []); message.success('Import complete'); return false; }} showUploadList={false}>
          <Button>Upload CSV/XLSX</Button>
        </Upload>
        <Space>
          <Select value={format} onChange={(v) => setFormat(v)} options={[{ value: 'csv', label: 'CSV' }, { value: 'xlsx', label: 'XLSX' }]} />
          <Button onClick={async () => {
            const response = await questionApi.exportFile({ format });
            const blob = new Blob([response.data]);
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `questions.${format}`;
            a.click();
            window.URL.revokeObjectURL(url);
          }}>Download</Button>
        </Space>
        <div>{results.map((r, idx) => <div key={idx}>Row {r.row}: {r.status}{r.reason ? ` - ${r.reason}` : ''}</div>)}</div>
      </Space>
    </Card>
  );
}
