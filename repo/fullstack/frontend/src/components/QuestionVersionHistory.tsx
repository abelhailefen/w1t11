import { Button, Timeline } from 'antd';
import { RollbackConfirmModal } from './RollbackConfirmModal';
import { useState } from 'react';
import { questionApi } from '../services/questionApi';

type Props = {
  questionId: number;
  versions: any[];
  allowRollback: boolean;
  onDone: () => void;
};

export function QuestionVersionHistory({ questionId, versions, allowRollback, onDone }: Props) {
  const [target, setTarget] = useState<number | null>(null);

  return (
    <>
      <Timeline
        items={versions.map((v) => ({
          children: (
            <div>
              <div>v{v.version_no} by {v.username} at {v.created_at}</div>
              <div>{(v.content_html || '').replace(/<[^>]*>/g, '').slice(0, 120)}</div>
              {allowRollback ? <Button size="small" onClick={() => setTarget(v.version_no)}>Rollback</Button> : null}
            </div>
          ),
        }))}
      />
      <RollbackConfirmModal
        open={target !== null}
        targetVersionNo={target || 0}
        loading={false}
        onCancel={() => setTarget(null)}
        onConfirm={async (password, justification) => {
          if (!target) return;
          await questionApi.rollback(questionId, target, password, justification);
          setTarget(null);
          onDone();
        }}
      />
    </>
  );
}
