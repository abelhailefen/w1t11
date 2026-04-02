import { Badge, Button, Card, Space, Timeline, Typography, Upload, message } from 'antd';
import { UploadOutlined } from '@ant-design/icons';
import { useEffect, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { RollbackConfirmModal } from '../components/RollbackConfirmModal';
import { StateTransitionActions } from '../components/StateTransitionActions';
import { useAuth } from '../context/AuthContext';
import { credentialApi, CredentialQueueItem, CredentialVersionItem } from '../services/credentialApi';
import { practitionerApi } from '../services/practitionerApi';

const stateColors: Record<string, string> = {
  DRAFT: 'default',
  SUBMITTED: 'processing',
  UNDER_REVIEW: 'warning',
  APPROVED: 'success',
  REJECTED: 'error',
  RESUBMISSION_REQUESTED: 'purple',
};

export function CredentialDetailPage() {
  const { id } = useParams();
  const credentialId = Number(id);
  const auth = useAuth();

  const [submission, setSubmission] = useState<CredentialQueueItem | null>(null);
  const [versions, setVersions] = useState<CredentialVersionItem[]>([]);
  const [rollbackOpen, setRollbackOpen] = useState(false);
  const [rollbackLoading, setRollbackLoading] = useState(false);

  const load = async () => {
    const response = await credentialApi.versions(credentialId);
    setSubmission(response.data.submission);
    setVersions(response.data.items || []);
  };

  useEffect(() => {
    if (!Number.isNaN(credentialId)) {
      load();
    }
  }, [credentialId]);

  const versionNumbers = useMemo(() => versions.map((version) => version.version_no), [versions]);

  const downloadFile = async (practitionerId: number, fileId: number, fileName: string) => {
    const response = await practitionerApi.downloadCredential(practitionerId, fileId);
    const url = window.URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  };

  if (!submission) {
    return <Card loading title="Credential Detail" />;
  }

  return (
    <Space direction="vertical" style={{ width: '100%' }} size="large">
      <Card title="Credential Status" extra={<Badge status={stateColors[submission.current_state] as any} text={submission.current_state} />}>
        <Typography.Text>Practitioner: {submission.practitioner.full_name}</Typography.Text>
        <br />
        <Typography.Text>Firm: {submission.practitioner.firm.name}</Typography.Text>
      </Card>

      <Card title="Actions" extra={auth.user?.role === 'ROLE_SYSTEM_ADMIN' ? <Button onClick={() => setRollbackOpen(true)}>Rollback</Button> : null}>
        <StateTransitionActions
          currentState={submission.current_state}
          userRole={auth.user?.role}
          onSubmit={async () => {
            await credentialApi.submit(submission.id);
            await load();
          }}
          onStartReview={async () => {
            await credentialApi.startReview(submission.id);
            await load();
          }}
          onApprove={async () => {
            await credentialApi.approve(submission.id);
            await load();
          }}
          onReject={async (comment) => {
            await credentialApi.reject(submission.id, comment);
            await load();
          }}
          onRequestResubmission={async () => {
            await credentialApi.requestResubmission(submission.id);
            await load();
          }}
        />
        {(submission.current_state === 'DRAFT' || submission.current_state === 'RESUBMISSION_REQUESTED' || submission.current_state === 'REJECTED') &&
        (auth.user?.role === 'ROLE_USER' || auth.user?.role === 'ROLE_SYSTEM_ADMIN') ? (
          <Upload
            style={{ marginTop: 12 }}
            customRequest={async ({ file, onSuccess, onError }) => {
              try {
                await practitionerApi.uploadCredential(submission.practitioner.id, file as File);
                onSuccess?.({});
                message.success('File uploaded');
                await load();
              } catch (error) {
                onError?.(error as Error);
                message.error('Upload failed');
              }
            }}
            showUploadList={false}
          >
            <Button icon={<UploadOutlined />}>Upload New File</Button>
          </Upload>
        ) : null}
      </Card>

      <Card title="Version History">
        <Timeline
          items={versions.map((version) => ({
            color: stateColors[version.state] || 'default',
            children: (
              <Space direction="vertical" size={2}>
                <Typography.Text strong>
                  V{version.version_no} - {version.state}
                </Typography.Text>
                <Typography.Text type="secondary">
                  {version.created_by.username} - {version.created_at}
                </Typography.Text>
                {version.rejection_comment ? <Typography.Text type="danger">Reason: {version.rejection_comment}</Typography.Text> : null}
                {version.files.map((file) => (
                  <Button
                    key={file.id}
                    type="link"
                    style={{ padding: 0 }}
                    onClick={() => downloadFile(submission.practitioner.id, file.id, file.original_name)}
                  >
                    {file.original_name}
                  </Button>
                ))}
              </Space>
            ),
          }))}
        />
      </Card>

      <RollbackConfirmModal
        open={rollbackOpen}
        loading={rollbackLoading}
        versions={versionNumbers}
        onCancel={() => setRollbackOpen(false)}
        onConfirm={async (targetVersionNo, password, justification) => {
          setRollbackLoading(true);
          try {
            await credentialApi.rollback(submission.id, targetVersionNo, password, justification);
            message.success('Rollback complete');
            setRollbackOpen(false);
            await load();
          } catch {
            message.error('Rollback failed');
          } finally {
            setRollbackLoading(false);
          }
        }}
      />
    </Space>
  );
}
