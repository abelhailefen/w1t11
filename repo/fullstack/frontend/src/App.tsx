import { Layout, Menu, Typography } from 'antd';
import { useMemo } from 'react';
import { Link, Route, Routes } from 'react-router-dom';
import { DashboardPage } from './pages/DashboardPage';

const { Header, Sider, Content } = Layout;

function App() {
  const items = useMemo(
    () => [
      {
        key: 'dashboard',
        label: <Link to="/">Dashboard</Link>,
      },
    ],
    []
  );

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
        </Header>
        <Content className="app-content">
          <Routes>
            <Route path="/" element={<DashboardPage />} />
          </Routes>
        </Content>
      </Layout>
    </Layout>
  );
}

export default App;
