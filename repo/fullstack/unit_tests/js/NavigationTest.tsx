import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import App from '../../frontend/src/App';

let mockRole = 'ROLE_USER';

jest.mock('../../frontend/src/context/AuthContext', () => ({
  useAuth: () => ({
    isAuthenticated: true,
    user: { id: 1, username: 'mock', role: mockRole, status: 'ACTIVE' },
    logout: jest.fn(),
    hasRole: (roles: string[]) => mockRole === 'ROLE_SYSTEM_ADMIN' || roles.includes(mockRole) || roles.includes('ROLE_USER'),
  }),
}));

jest.mock('../../frontend/src/services/governanceApi', () => ({
  governanceApi: {
    alerts: jest.fn().mockResolvedValue({ data: { items: [] } }),
  },
}));

describe('NavigationTest', () => {
  it('renders admin items for system admin', async () => {
    mockRole = 'ROLE_SYSTEM_ADMIN';
    render(<MemoryRouter initialEntries={['/dashboard']}><App /></MemoryRouter>);
    expect(await screen.findByText('System Settings')).toBeInTheDocument();
    expect(screen.getByText('Audit Logs')).toBeInTheDocument();
    expect(screen.getByText('Alerts')).toBeInTheDocument();
  });

  it('hides admin-only items for regular user', async () => {
    mockRole = 'ROLE_USER';
    render(<MemoryRouter initialEntries={['/dashboard']}><App /></MemoryRouter>);
    expect(await screen.findByText('Dashboard')).toBeInTheDocument();
    expect(screen.queryByText('System Settings')).not.toBeInTheDocument();
    expect(screen.queryByText('Audit Logs')).not.toBeInTheDocument();
  });
});
