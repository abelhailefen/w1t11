import { Badge, Button, Layout, Menu, Typography } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { RoleGuard } from './components/RoleGuard';
import { useAuth } from './context/AuthContext';
import { DashboardPage } from './pages/DashboardPage';
import { CalendarWorkbenchPage } from './pages/CalendarWorkbenchPage';
import { CredentialDetailPage } from './pages/CredentialDetailPage';
import { CredentialQueuePage } from './pages/CredentialQueuePage';
import { FirmManagementPage } from './pages/FirmManagementPage';
import { AvailabilityConfigPage } from './pages/AvailabilityConfigPage';
import { LoginPage } from './pages/LoginPage';
import { LocationManagementPage } from './pages/LocationManagementPage';
import { AppointmentListPage } from './pages/AppointmentListPage';
import { AnalyticsWorkbenchPage } from './pages/AnalyticsWorkbenchPage';
import { ComplianceDashboardPage } from './pages/ComplianceDashboardPage';
import { OrgUnitManagementPage } from './pages/OrgUnitManagementPage';
import { PractitionerDetailPage } from './pages/PractitionerDetailPage';
import { PractitionerListPage } from './pages/PractitionerListPage';
import { QuestionBankPage } from './pages/QuestionBankPage';
import { QuestionCategoriesPage } from './pages/QuestionCategoriesPage';
import { QuestionEditorPage } from './pages/QuestionEditorPage';
import { QuestionTagsPage } from './pages/QuestionTagsPage';
import { SignupPage } from './pages/SignupPage';
import { UnauthorizedPage } from './pages/UnauthorizedPage';
import { UserManagementPage } from './pages/UserManagementPage';
import { AuditLogPage } from './pages/AuditLogPage';
import { SystemAlertsPage } from './pages/SystemAlertsPage';
import { governanceApi } from './services/governanceApi';
import { SystemSettingsPage } from './pages/SystemSettingsPage';

const { Header, Sider, Content } = Layout;

function App() {
  const auth = useAuth();
  const location = useLocation();
  const isAuthRoute = location.pathname === '/login' || location.pathname === '/signup';
  const [unackedAlerts, setUnackedAlerts] = useState(0);

  useEffect(() => {
    if (!auth.hasRole(['ROLE_SYSTEM_ADMIN'])) {
      setUnackedAlerts(0);
      return;
    }
    governanceApi.alerts({ acknowledged: false }).then((r) => setUnackedAlerts((r.data.items || []).length)).catch(() => setUnackedAlerts(0));
  }, [auth.user?.role]);
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
      {
        key: 'calendar',
        label: <Link to="/calendar">Calendar</Link>,
      },
      {
        key: 'appointments',
        label: <Link to="/appointments">Appointments</Link>,
      },
    ];

    if (auth.hasRole(['ROLE_SYSTEM_ADMIN'])) {
      base.push({ key: 'users', label: <Link to="/admin/users">User Management</Link> });
      base.push({ key: 'firms', label: <Link to="/admin/firms">Firms</Link> });
      base.push({ key: 'locations', label: <Link to="/admin/locations">Locations</Link> });
      base.push({ key: 'question-categories', label: <Link to="/admin/question-categories">Categories</Link> });
      base.push({ key: 'question-tags', label: <Link to="/admin/question-tags">Tags</Link> });
      base.push({ key: 'org-units', label: <Link to="/admin/org-units">Org Units</Link> });
      base.push({ key: 'system-settings', label: <Link to="/admin/settings">System Settings</Link> });
      base.push({ key: 'audit-logs', label: <Link to="/admin/audit-logs">Audit Logs</Link> });
      base.push({ key: 'alerts', label: <Link to="/admin/alerts"><Badge count={unackedAlerts} size="small">Alerts</Badge></Link> });
    }

    if (auth.hasRole(['ROLE_CONTENT_ADMIN', 'ROLE_SYSTEM_ADMIN'])) {
      base.push({ key: 'question-bank', label: <Link to="/questions">Question Bank</Link> });
    }

    if (auth.hasRole(['ROLE_CREDENTIAL_REVIEWER', 'ROLE_SYSTEM_ADMIN'])) {
      base.push({ key: 'credential-review', label: <Link to="/credentials/queue">Credential Review</Link> });
    }

    if (auth.hasRole(['ROLE_ANALYST', 'ROLE_SYSTEM_ADMIN'])) {
      base.push({ key: 'analytics-workbench', label: <Link to="/analytics/workbench">Analytics</Link> });
      base.push({ key: 'analytics-compliance', label: <Link to="/analytics/compliance">Compliance Dashboard</Link> });
    }

    return base;
  }, [auth, unackedAlerts]);

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
              path="/calendar"
              element={
                <RoleGuard allowedRoles={['ROLE_USER']}>
                  <CalendarWorkbenchPage />
                </RoleGuard>
              }
            />
            <Route
              path="/appointments"
              element={
                <RoleGuard allowedRoles={['ROLE_USER']}>
                  <AppointmentListPage />
                </RoleGuard>
              }
            />
            <Route
              path="/analytics/workbench"
              element={
                <RoleGuard allowedRoles={['ROLE_ANALYST', 'ROLE_SYSTEM_ADMIN']}>
                  <AnalyticsWorkbenchPage />
                </RoleGuard>
              }
            />
            <Route
              path="/analytics/compliance"
              element={
                <RoleGuard allowedRoles={['ROLE_ANALYST', 'ROLE_SYSTEM_ADMIN']}>
                  <ComplianceDashboardPage />
                </RoleGuard>
              }
            />
            <Route
              path="/questions"
              element={
                <RoleGuard allowedRoles={['ROLE_CONTENT_ADMIN', 'ROLE_SYSTEM_ADMIN']}>
                  <QuestionBankPage />
                </RoleGuard>
              }
            />
            <Route
              path="/questions/new"
              element={
                <RoleGuard allowedRoles={['ROLE_CONTENT_ADMIN', 'ROLE_SYSTEM_ADMIN']}>
                  <QuestionEditorPage />
                </RoleGuard>
              }
            />
            <Route
              path="/questions/:id"
              element={
                <RoleGuard allowedRoles={['ROLE_CONTENT_ADMIN', 'ROLE_SYSTEM_ADMIN']}>
                  <QuestionEditorPage />
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
              path="/admin/availability"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <AvailabilityConfigPage />
                </RoleGuard>
              }
            />
            <Route
              path="/admin/settings"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <SystemSettingsPage />
                </RoleGuard>
              }
            />
            <Route
              path="/admin/locations"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <LocationManagementPage />
                </RoleGuard>
              }
            />
            <Route
              path="/admin/org-units"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <OrgUnitManagementPage />
                </RoleGuard>
              }
            />
            <Route
              path="/admin/question-categories"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <QuestionCategoriesPage />
                </RoleGuard>
              }
            />
            <Route
              path="/admin/question-tags"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <QuestionTagsPage />
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
              path="/admin/audit-logs"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <AuditLogPage />
                </RoleGuard>
              }
            />
            <Route
              path="/admin/alerts"
              element={
                <RoleGuard allowedRoles={['ROLE_SYSTEM_ADMIN']}>
                  <SystemAlertsPage />
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
            <Route
              path="/credentials/queue"
              element={
                <RoleGuard allowedRoles={['ROLE_CREDENTIAL_REVIEWER', 'ROLE_SYSTEM_ADMIN']}>
                  <CredentialQueuePage />
                </RoleGuard>
              }
            />
            <Route
              path="/credentials/:id"
              element={
                <RoleGuard allowedRoles={['ROLE_USER', 'ROLE_CREDENTIAL_REVIEWER', 'ROLE_SYSTEM_ADMIN']}>
                  <CredentialDetailPage />
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
