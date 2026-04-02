import { Button, Card, List, Space } from 'antd';

type Props = {
  flags: Array<{ matched_question_id: number; similarity_score: number; content_preview: string }>;
  onDismiss: (id: number) => void;
  onView: (id: number) => void;
  onConfirm: () => void;
};

export function DuplicateFlagReview({ flags, onDismiss, onView, onConfirm }: Props) {
  return (
    <Card title="Potential Duplicates">
      <List
        dataSource={flags}
        renderItem={(item) => (
          <List.Item
            actions={[
              <Button key="dismiss" onClick={() => onDismiss(item.matched_question_id)}>Dismiss</Button>,
              <Button key="view" onClick={() => onView(item.matched_question_id)}>View Original</Button>,
            ]}
          >
            <Space direction="vertical">
              <span>Question #{item.matched_question_id}</span>
              <span>Similarity: {item.similarity_score}%</span>
              <span>{item.content_preview}</span>
            </Space>
          </List.Item>
        )}
      />
      <Button type="primary" onClick={onConfirm} disabled={flags.length === 0}>Confirm Publish Anyway</Button>
    </Card>
  );
}
