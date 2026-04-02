import { Line } from '@ant-design/charts';

export function TrendChart({ data }: { data: Array<{ time: string; value: number }> }) {
  return <Line data={data} xField="time" yField="value" point={{ size: 3 }} />;
}
