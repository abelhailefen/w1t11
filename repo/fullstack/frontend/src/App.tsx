import { Button, Layout, Menu, Typography } from 'antd';
import { useMemo } from 'react';
import { Link, Route, Routes } from 'react-router-dom';
import { RoleGuard } from './components/RoleGuard';
import { useAuth } from './context/AuthContext';
import { DashboardPage } from './pages/DashboardPage';
import { LoginPage } from './pages/LoginPage';
import { UnauthorizedPage } from './pages/UnauthorizedPage';
import { UserManagementPage } from './pages/UserManagementPage';

const { Header, Sider, Content } = Layout;

function App() {
  const auth = useAuth();
  const items = useMemo(() => {
    const base = [
      {
        key: 'dashboard',
        label: <Link to="/">Dashboard</Link>,
      },
    ];

    if (auth.hasRole(['ROLE_SYSTEM_ADMIN'])) {
      base.push({ key: 'users', label: <Link to="/admin/users">User Management</Link> });
    }

    return base;
  }, [auth]);

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider width={240}>
        <div className="brand">RegOps Portal</div>
        <Menu theme="dark" mode="inline" defaultSelectedKeys={['dashboard']} items={items} />
      </Sider>
      <Layout>
        <Header className="app-header">
          <Typography.Title level={4} style={{ margin: 0 }}>
            Regulatory Operations & Analytics Portal
          </Typography.Title>
          <div style={{ marginLeft: 'auto' }}>
            {auth.isAuthenticated ? <Button onClick={auth.logout}>Logout</Button> : <Link to="/login">Login</Link>}
          </div>
        </Header>
        <Content className="app-content">
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/unauthorized" element={<UnauthorizedPage />} />
            <Route
              path="/"
              element={
                <RoleGuard allowedRoles={['ROLE_USER']}>
                  <DashboardPage />
                </RoleGuard>
              }
            />
            <Route
              path="/admin/users"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <UserManagementPage />
                </RoleGuard>
              }
            />
          </Routes>
        </Content>
      </Layout>
    </Layout>
  );
}

export default App;
