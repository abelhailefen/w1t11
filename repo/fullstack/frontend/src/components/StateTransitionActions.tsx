import { Button, Input, Modal, Space, message } from 'antd';
import { useState } from 'react';

type Props = {
  currentState: string;
  userRole?: string;
  onSubmit: () => Promise<void>;
  onStartReview: () => Promise<void>;
  onApprove: () => Promise<void>;
  onReject: (comment: string) => Promise<void>;
  onRequestResubmission: () => Promise<void>;
};

export function StateTransitionActions({
  currentState,
  userRole,
  onSubmit,
  onStartReview,
  onApprove,
  onReject,
  onRequestResubmission,
}: Props) {
  const [loadingAction, setLoadingAction] = useState<string | null>(null);
  const [openReject, setOpenReject] = useState(false);
  const [rejectComment, setRejectComment] = useState('');

  const isReviewer = userRole === 'ROLE_CREDENTIAL_REVIEWER' || userRole === 'ROLE_SYSTEM_ADMIN';
  const isUser = userRole === 'ROLE_USER' || userRole === 'ROLE_SYSTEM_ADMIN';

  const run = async (key: string, action: () => Promise<void>, successText: string) => {
    setLoadingAction(key);
    try {
      await action();
      message.success(successText);
    } catch (error: any) {
      message.error(error?.response?.data?.message || 'Action failed');
    } finally {
      setLoadingAction(null);
    }
  };

  return (
    <>
      <Space wrap>
        {isUser && (currentState === 'DRAFT' || currentState === 'RESUBMISSION_REQUESTED' || currentState === 'REJECTED') ? (
          <Button loading={loadingAction === 'submit'} onClick={() => run('submit', onSubmit, 'Submission sent')}>
            {currentState === 'DRAFT' ? 'Submit' : 'Resubmit'}
          </Button>
        ) : null}
        {isReviewer && currentState === 'SUBMITTED' ? (
          <Button loading={loadingAction === 'start-review'} onClick={() => run('start-review', onStartReview, 'Moved to review')}>
            Start Review
          </Button>
        ) : null}
        {isReviewer && currentState === 'UNDER_REVIEW' ? (
          <>
            <Button type="primary" loading={loadingAction === 'approve'} onClick={() => run('approve', onApprove, 'Approved')}>
              Approve
            </Button>
            <Button danger onClick={() => setOpenReject(true)}>
              Reject
            </Button>
            <Button loading={loadingAction === 'resubmit-request'} onClick={() => run('resubmit-request', onRequestResubmission, 'Resubmission requested')}>
              Request Resubmission
            </Button>
          </>
        ) : null}
      </Space>

      <Modal
        open={openReject}
        title="Reject Credential"
        onCancel={() => setOpenReject(false)}
        onOk={() => {
          if (!rejectComment.trim()) {
            message.error('Comment is required');
            return;
          }
          run('reject', () => onReject(rejectComment), 'Rejected').then(() => {
            setOpenReject(false);
            setRejectComment('');
          });
        }}
        okText="Reject"
      >
        <Input.TextArea
          value={rejectComment}
          onChange={(event) => setRejectComment(event.target.value)}
          rows={4}
          placeholder="Reason for rejection"
        />
      </Modal>
    </>
  );
}
