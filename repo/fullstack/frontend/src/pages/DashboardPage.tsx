import { Card, Col, Descriptions, Row, Typography } from 'antd';
import { useEffect, useState } from 'react';
import { apiClient } from '../services/apiClient';

type HealthResponse = {
  status: string;
  service: string;
  timestamp: string;
};

export function DashboardPage() {
  const [health, setHealth] = useState<HealthResponse | null>(null);

  useEffect(() => {
    apiClient
      .get<HealthResponse>('/api/v1/health')
      .then((response) => setHealth(response.data))
      .catch(() => setHealth(null));
  }, []);

  return (
    <Row gutter={[16, 16]}>
      <Col span={24}>
        <Card>
          <Typography.Title level={5}>Foundation Module Status</Typography.Title>
          <Descriptions column={1} size="small">
            <Descriptions.Item label="Frontend">Online</Descriptions.Item>
            <Descriptions.Item label="Backend Health">{health?.status ?? 'Unavailable'}</Descriptions.Item>
            <Descriptions.Item label="Service">{health?.service ?? 'Unavailable'}</Descriptions.Item>
            <Descriptions.Item label="Timestamp">{health?.timestamp ?? 'Unavailable'}</Descriptions.Item>
          </Descriptions>
        </Card>
      </Col>
    </Row>
  );
}
