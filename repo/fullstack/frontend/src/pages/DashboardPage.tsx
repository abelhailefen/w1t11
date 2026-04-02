import { Card, Col, Row, Space, Statistic, Typography } from 'antd';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { analyticsApi } from '../services/analyticsApi';

export function DashboardPage() {
  const [kpis, setKpis] = useState<any>({});

  useEffect(() => {
    const from = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
    const to = new Date().toISOString().slice(0, 10);
    analyticsApi.compliance(from, to).then((r) => setKpis(r.data)).catch(() => setKpis({}));
  }, []);

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <Card>
        <Typography.Title level={5}>Overview Dashboard</Typography.Title>
        <Row gutter={[12, 12]}>
          <Col span={8}><Statistic title="Credential Reviews" value={kpis.credential_review_volume || 0} /></Col>
          <Col span={8}><Statistic title="Approval Rate" value={kpis.approval_rate || 0} suffix="%" /></Col>
          <Col span={8}><Statistic title="Question Growth" value={kpis.question_bank_growth || 0} /></Col>
        </Row>
      </Card>
      <Card title="Quick Links">
        <Space>
          <Link to="/credentials/queue">Credential Review</Link>
          <Link to="/calendar">Calendar</Link>
          <Link to="/questions">Question Bank</Link>
          <Link to="/analytics/workbench">Analytics Workbench</Link>
          <Link to="/analytics/compliance">Compliance Dashboard</Link>
        </Space>
      </Card>
    </Space>
  );
}
