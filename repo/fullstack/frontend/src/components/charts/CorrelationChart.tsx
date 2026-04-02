import { Scatter } from '@ant-design/charts';

export function CorrelationChart({ data }: { data: Array<{ x: number; y: number; time?: string }> }) {
  return <Scatter data={data} xField="x" yField="y" colorField="time" />;
}
