import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { StateTransitionActions } from '../../frontend/src/components/StateTransitionActions';

describe('StateTransitionActions', () => {
  it('renders reviewer buttons for UNDER_REVIEW', async () => {
    const approve = jest.fn().mockResolvedValue(undefined);

    render(
      <StateTransitionActions
        currentState="UNDER_REVIEW"
        userRole="ROLE_CREDENTIAL_REVIEWER"
        onSubmit={jest.fn()}
        onStartReview={jest.fn()}
        onApprove={approve}
        onReject={jest.fn().mockResolvedValue(undefined)}
        onRequestResubmission={jest.fn().mockResolvedValue(undefined)}
      />
    );

    expect(screen.getByText('Approve')).toBeInTheDocument();
    expect(screen.getByText('Reject')).toBeInTheDocument();
    expect(screen.getByText('Request Resubmission')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Approve'));
    await waitFor(() => expect(approve).toHaveBeenCalled());
  });

  it('renders submit button for user draft state', () => {
    render(
      <StateTransitionActions
        currentState="DRAFT"
        userRole="ROLE_USER"
        onSubmit={jest.fn().mockResolvedValue(undefined)}
        onStartReview={jest.fn()}
        onApprove={jest.fn()}
        onReject={jest.fn()}
        onRequestResubmission={jest.fn()}
      />
    );

    expect(screen.getByText('Submit')).toBeInTheDocument();
    expect(screen.queryByText('Approve')).not.toBeInTheDocument();
  });
});
