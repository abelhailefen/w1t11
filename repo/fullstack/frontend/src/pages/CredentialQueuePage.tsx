import { Card, Table, Tabs } from 'antd';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { credentialApi, CredentialQueueItem } from '../services/credentialApi';

const states = ['', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'RESUBMISSION_REQUESTED'];

export function CredentialQueuePage() {
  const [stateFilter, setStateFilter] = useState('');
  const [items, setItems] = useState<CredentialQueueItem[]>([]);
  const navigate = useNavigate();

  useEffect(() => {
    credentialApi.queue(stateFilter || undefined).then((response) => setItems(response.data.items || []));
  }, [stateFilter]);

  return (
    <Card title="Credential Review Queue">
      <Tabs
        activeKey={stateFilter}
        onChange={setStateFilter}
        items={states.map((state) => ({ key: state, label: state || 'ALL' }))}
      />
      <Table<CredentialQueueItem>
        rowKey="id"
        dataSource={items}
        onRow={(record) => ({ onClick: () => navigate(`/credentials/${record.id}`), style: { cursor: 'pointer' } })}
        columns={[
          { title: 'Practitioner', render: (_, record) => record.practitioner.full_name },
          { title: 'Firm', render: (_, record) => record.practitioner.firm.name },
          { title: 'Current State', dataIndex: 'current_state' },
          { title: 'Submitted Date', dataIndex: 'updated_at' },
          { title: 'Actions', render: () => 'Open' },
        ]}
      />
    </Card>
  );
}
