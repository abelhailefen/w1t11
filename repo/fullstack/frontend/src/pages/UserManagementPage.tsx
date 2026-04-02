import { Button, Modal, Select, Space, Table, message } from 'antd';
import { useEffect, useState } from 'react';
import { apiClient } from '../services/apiClient';

type UserItem = {
  id: number;
  username: string;
  role: string;
  status: string;
  created_at: string;
};

const roleOptions = [
  'ROLE_USER',
  'ROLE_CONTENT_ADMIN',
  'ROLE_CREDENTIAL_REVIEWER',
  'ROLE_ANALYST',
  'ROLE_SYSTEM_ADMIN',
].map((role) => ({ label: role, value: role }));

export function UserManagementPage() {
  const [users, setUsers] = useState<UserItem[]>([]);
  const [loading, setLoading] = useState(false);

  const loadUsers = async () => {
    setLoading(true);
    try {
      const response = await apiClient.get<{ items: UserItem[] }>('/api/v1/admin/users');
      setUsers(response.data.items);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadUsers();
  }, []);

  const updateRole = async (userId: number, role: string) => {
    await apiClient.patch(`/api/v1/admin/users/${userId}/role`, { role });
    message.success('Role updated');
    await loadUsers();
  };

  const resetPassword = async (userId: number) => {
    Modal.confirm({
      title: 'Reset password?',
      onOk: async () => {
        const response = await apiClient.post(`/api/v1/admin/users/${userId}/reset-password`, {});
        message.success(`Password reset. Temp password: ${response.data.temporary_password}`);
      },
    });
  };

  return (
    <Table<UserItem>
      rowKey="id"
      dataSource={users}
      loading={loading}
      columns={[
        { title: 'Username', dataIndex: 'username' },
        { title: 'Role', dataIndex: 'role' },
        { title: 'Status', dataIndex: 'status' },
        { title: 'Created At', dataIndex: 'created_at' },
        {
          title: 'Actions',
          render: (_, record) => (
            <Space>
              <Select
                style={{ width: 220 }}
                value={record.role}
                options={roleOptions}
                onChange={(role) => updateRole(record.id, role)}
              />
              <Button onClick={() => resetPassword(record.id)}>Reset Password</Button>
            </Space>
          ),
        },
      ]}
    />
  );
}
