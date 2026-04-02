import { Button, Layout, Menu, Typography } from 'antd';
import { useMemo } from 'react';
import { Link, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { RoleGuard } from './components/RoleGuard';
import { useAuth } from './context/AuthContext';
import { DashboardPage } from './pages/DashboardPage';
import { FirmManagementPage } from './pages/FirmManagementPage';
import { LoginPage } from './pages/LoginPage';
import { PractitionerDetailPage } from './pages/PractitionerDetailPage';
import { PractitionerListPage } from './pages/PractitionerListPage';
import { SignupPage } from './pages/SignupPage';
import { UnauthorizedPage } from './pages/UnauthorizedPage';
import { UserManagementPage } from './pages/UserManagementPage';

const { Header, Sider, Content } = Layout;

function App() {
  const auth = useAuth();
  const location = useLocation();
  const isAuthRoute = location.pathname === '/login' || location.pathname === '/signup';
  const items = useMemo(() => {
    const base = [
      {
        key: 'dashboard',
        label: <Link to="/dashboard">Dashboard</Link>,
      },
      {
        key: 'practitioners',
        label: <Link to="/practitioners">Practitioners</Link>,
      },
    ];

    if (auth.hasRole(['ROLE_SYSTEM_ADMIN'])) {
      base.push({ key: 'users', label: <Link to="/admin/users">User Management</Link> });
      base.push({ key: 'firms', label: <Link to="/admin/firms">Firms</Link> });
    }

    return base;
  }, [auth]);

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider width={240}>
        <div className="brand">RegOps Portal</div>
        {auth.isAuthenticated && !isAuthRoute ? (
          <Menu theme="dark" mode="inline" defaultSelectedKeys={['dashboard']} items={items} />
        ) : null}
      </Sider>
      <Layout>
        <Header className="app-header">
          <Typography.Title level={4} style={{ margin: 0 }}>
            Regulatory Operations & Analytics Portal
          </Typography.Title>
          <div style={{ marginLeft: 'auto' }}>
            {auth.isAuthenticated && !isAuthRoute ? <Button onClick={auth.logout}>Logout</Button> : null}
          </div>
        </Header>
        <Content className="app-content">
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/signup" element={<SignupPage />} />
            <Route path="/unauthorized" element={<UnauthorizedPage />} />
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route
              path="/dashboard"
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
            <Route
              path="/admin/firms"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <FirmManagementPage />
                </RoleGuard>
              }
            />
            <Route
              path="/practitioners"
              element={
                <RoleGuard allowedRoles={['ROLE_USER']}>
                  <PractitionerListPage />
                </RoleGuard>
              }
            />
            <Route
              path="/practitioners/:id"
              element={
                <RoleGuard allowedRoles={['ROLE_USER']}>
                  <PractitionerDetailPage />
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
