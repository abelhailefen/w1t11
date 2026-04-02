import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { RollbackConfirmModal } from '../../frontend/src/components/RollbackConfirmModal';

describe('RollbackConfirmModal', () => {
  it('requires fields before submit', async () => {
    const onConfirm = jest.fn().mockResolvedValue(undefined);

    render(
      <RollbackConfirmModal
        open
        versions={[1, 2, 3]}
        onCancel={() => undefined}
        onConfirm={onConfirm}
      />
    );

    fireEvent.click(screen.getByText('Confirm Rollback'));
    await waitFor(() => {
      expect(onConfirm).not.toHaveBeenCalled();
    });
  });
});
