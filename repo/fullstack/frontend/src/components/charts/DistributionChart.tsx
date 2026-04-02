import { Bar, Pie } from '@ant-design/charts';
import { Segmented } from 'antd';
import { useState } from 'react';

export function DistributionChart({ data }: { data: Array<{ label: string; value: number }> }) {
  const [mode, setMode] = useState<'bar' | 'pie'>('bar');
  return (
    <>
      <Segmented value={mode} onChange={(v) => setMode(v as 'bar' | 'pie')} options={[{ label: 'Bar', value: 'bar' }, { label: 'Pie', value: 'pie' }]} style={{ marginBottom: 12 }} />
      {mode === 'bar' ? <Bar data={data} xField="value" yField="label" /> : <Pie data={data} angleField="value" colorField="label" />}
    </>
  );
}
