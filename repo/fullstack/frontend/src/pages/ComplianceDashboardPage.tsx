import { Button, Card, Col, Progress, Row, Select, Space, Statistic } from 'antd';
import { useEffect, useState } from 'react';
import { TrendChart } from '../components/charts/TrendChart';
import { analyticsApi } from '../services/analyticsApi';

export function ComplianceDashboardPage() {
  const [from, setFrom] = useState(new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10));
  const [to, setTo] = useState(new Date().toISOString().slice(0, 10));
  const [orgUnit, setOrgUnit] = useState<number | undefined>();
  const [orgUnits, setOrgUnits] = useState<any[]>([]);
  const [kpis, setKpis] = useState<any>({});
  const [trend, setTrend] = useState<Array<{ time: string; value: number }>>([]);

  const load = async () => {
    const [k, t, o] = await Promise.all([
      analyticsApi.compliance(from, to, orgUnit),
      analyticsApi.trend('credential_submissions', from, to, 'daily'),
      analyticsApi.listOrgUnits(),
    ]);
    setKpis(k.data || {});
    setTrend(t.data.points || []);
    setOrgUnits(o.data.items || []);
  };

  useEffect(() => { load(); }, [from, to, orgUnit]);

  const exportKpiCsv = () => {
    const rows = Object.entries(kpis || {}).map(([key, value]) => {
      const serialized = typeof value === 'object' ? JSON.stringify(value) : String(value);
      return `${key},"${serialized.replace(/"/g, '""')}"`;
    });
    const csv = ['kpi,value', ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'compliance-kpis.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <Card title="Compliance Dashboard" extra={<Space>
        <Button onClick={async () => {
          const r = await analyticsApi.exportPdf(from, to, orgUnit);
          const blob = new Blob([r.data], { type: 'application/pdf' });
          const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'compliance.pdf'; a.click(); URL.revokeObjectURL(url);
        }}>Export PDF</Button>
        <Button onClick={exportKpiCsv}>Export CSV</Button>
      </Space>}>
        <Space wrap>
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          <Select allowClear placeholder="Org Unit" style={{ width: 220 }} value={orgUnit} onChange={setOrgUnit} options={orgUnits.map((o) => ({ label: o.name, value: o.id }))} />
        </Space>
      </Card>

      <Row gutter={[12, 12]}>
        <Col span={6}><Card><Statistic title="Credential Review Volume" value={kpis.credential_review_volume || 0} /></Card></Col>
        <Col span={6}><Card><Statistic title="Rescue Volume" value={kpis.rescue_volume || 0} /></Card></Col>
        <Col span={6}><Card><Statistic title="Approval Rate" value={kpis.approval_rate || 0} suffix="%" /><Progress type="circle" percent={Math.round(kpis.approval_rate || 0)} size={64} /></Card></Col>
        <Col span={6}><Card><Statistic title="Rejection Rate" value={kpis.rejection_rate || 0} suffix="%" /></Card></Col>
        <Col span={6}><Card><Statistic title="Avg Review Turnaround" value={kpis.avg_review_turnaround_hours || 0} suffix="h" /></Card></Col>
        <Col span={6}><Card><Statistic title="Recovery Rate" value={kpis.recovery_rate || 0} suffix="%" /></Card></Col>
        <Col span={6}><Card><Statistic title="Adoption Conversion" value={kpis.adoption_conversion || 0} suffix="%" /></Card></Col>
        <Col span={6}><Card><Statistic title="Average Shelter Stay" value={kpis.average_shelter_stay || 0} suffix="h" /></Card></Col>
        <Col span={6}><Card><Statistic title="Appointment Utilization" value={kpis.appointment_utilization_rate || 0} suffix="%" /></Card></Col>
        <Col span={6}><Card><Statistic title="Question Bank Growth" value={kpis.question_bank_growth || 0} /></Card></Col>
        <Col span={6}><Card><Statistic title="Supply Turnover" value={kpis.supply_turnover || 0} suffix="%" /></Card></Col>
        <Col span={12}><Card><Statistic title="Donation Mix (Firms)" value={Object.keys(kpis.donation_mix || {}).length} suffix="firms" /></Card></Col>
        <Col span={12}><Card><Statistic title="Active Practitioners per Firm" value={(kpis.active_practitioners_per_firm || []).reduce((a: number, b: any) => a + Number(b.practitioner_count || 0), 0)} /></Card></Col>
      </Row>

      <Card title="Trend"><TrendChart data={trend} /></Card>
    </Space>
  );
}
