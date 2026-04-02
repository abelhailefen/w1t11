import { fireEvent, render, screen } from '@testing-library/react';
import { DuplicateFlagReview } from '../../frontend/src/components/DuplicateFlagReview';

describe('DuplicateFlagReview', () => {
  it('renders flags and supports dismiss/confirm', () => {
    const onDismiss = jest.fn();
    const onConfirm = jest.fn();
    render(
      <DuplicateFlagReview
        flags={[{ matched_question_id: 12, similarity_score: 88.4, content_preview: 'similar content' }]}
        onDismiss={onDismiss}
        onView={jest.fn()}
        onConfirm={onConfirm}
      />
    );

    expect(screen.getByText('Question #12')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Dismiss'));
    expect(onDismiss).toHaveBeenCalledWith(12);
    fireEvent.click(screen.getByText('Confirm Publish Anyway'));
    expect(onConfirm).toHaveBeenCalled();
  });
});
