import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ComplianceDashboardPage } from '../../frontend/src/pages/ComplianceDashboardPage';

jest.mock('../../frontend/src/components/charts/TrendChart', () => ({
  TrendChart: () => <div data-testid="trend-chart" />,
}));
jest.mock('../../frontend/src/services/analyticsApi', () => ({
  analyticsApi: {
    compliance: jest.fn().mockResolvedValue({ data: {
      credential_review_volume: 10,
      approval_rate: 75,
      rejection_rate: 25,
      avg_review_turnaround_hours: 5,
      appointment_utilization_rate: 60,
      question_bank_growth: 4,
      active_practitioners_per_firm: [{ firm_name: 'A', practitioner_count: 3 }],
    } }),
    trend: jest.fn().mockResolvedValue({ data: { points: [{ time: '2026-04-01', value: 1 }] } }),
    listOrgUnits: jest.fn().mockResolvedValue({ data: { items: [] } }),
    exportPdf: jest.fn(),
    runQuery: jest.fn(),
    saveQuery: jest.fn(),
  },
}));

describe('ComplianceDashboardPage', () => {
  it('renders KPI cards and trend chart', async () => {
    render(<MemoryRouter><ComplianceDashboardPage /></MemoryRouter>);
    expect(await screen.findByText('Credential Review Volume')).toBeInTheDocument();
    expect(screen.getByTestId('trend-chart')).toBeInTheDocument();
  });
});
