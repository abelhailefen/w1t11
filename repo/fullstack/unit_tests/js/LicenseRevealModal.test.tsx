import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { LicenseRevealModal } from '../../frontend/src/components/LicenseRevealModal';

describe('LicenseRevealModal', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('renders confirmation, reveals license, and hides after timeout', async () => {
    render(
      <LicenseRevealModal
        open
        onCancel={() => undefined}
        onConfirm={async () => 'NY-123456'}
      />
    );

    expect(screen.getByText('Accessing encrypted license data will be logged. Continue?')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Confirm Reveal'));

    await waitFor(() => {
      expect(screen.getByTestId('revealed-license')).toHaveTextContent('NY-123456');
    });

    act(() => {
      jest.advanceTimersByTime(30000);
    });

    await waitFor(() => {
      expect(screen.queryByTestId('revealed-license')).not.toBeInTheDocument();
    });
  });
});
