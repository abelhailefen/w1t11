import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AnalyticsWorkbenchPage } from '../../frontend/src/pages/AnalyticsWorkbenchPage';
import { analyticsApi } from '../../frontend/src/services/analyticsApi';

jest.mock('../../frontend/src/services/analyticsApi', () => ({
  analyticsApi: {
    runQuery: jest.fn().mockResolvedValue({ data: { items: [{ aggregate_value: 3, group_value: 'ACTIVE' }] } }),
    saveQuery: jest.fn().mockResolvedValue({ data: { id: 1 } }),
    listSavedQueries: jest.fn().mockResolvedValue({ data: { items: [] } }),
    exportCsv: jest.fn(),
  },
}));

describe('AnalyticsWorkbenchPage', () => {
  it('renders query form and runs query', async () => {
    render(<MemoryRouter><AnalyticsWorkbenchPage /></MemoryRouter>);
    fireEvent.click(await screen.findByText('Run Query'));
    expect((analyticsApi as any).runQuery).toHaveBeenCalled();
  });
});
