import { render, screen, fireEvent, act } from '@testing-library/react';
import { BookingDrawer } from '../../frontend/src/components/BookingDrawer';

jest.mock('../../frontend/src/services/appointmentApi', () => ({
  appointmentApi: {
    hold: jest.fn().mockResolvedValue({ data: { appointment_id: 99 } }),
    book: jest.fn().mockResolvedValue({ data: {} }),
  },
}));

describe('BookingDrawer', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.runOnlyPendingTimers();
    jest.useRealTimers();
  });

  it('renders countdown and expires after timeout', async () => {
    render(
      <BookingDrawer
        open
        slot={{
          id: 1,
          practitioner: { id: 1, full_name: 'Jordan Blake' },
          location: { id: 1, name: 'Main Hearing Center' },
          start_at: new Date().toISOString(),
          end_at: new Date(Date.now() + 30 * 60000).toISOString(),
          capacity: 1,
          available_count: 1,
          status: 'AVAILABLE',
        }}
        onClose={jest.fn()}
        onBooked={jest.fn()}
      />
    );

    fireEvent.click(screen.getByText('Hold & Book'));
    expect(await screen.findByTestId('countdown')).toBeInTheDocument();

    act(() => {
      jest.advanceTimersByTime(301000);
    });

    expect(await screen.findByText('Hold expired')).toBeInTheDocument();
  });
});
