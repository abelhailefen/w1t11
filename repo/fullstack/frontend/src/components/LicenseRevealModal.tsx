import { Button, Modal, Typography } from 'antd';
import { useEffect, useState } from 'react';

type Props = {
  open: boolean;
  loading?: boolean;
  onCancel: () => void;
  onConfirm: () => Promise<string>;
};

export function LicenseRevealModal({ open, loading = false, onCancel, onConfirm }: Props) {
  const [revealed, setRevealed] = useState<string | null>(null);

  useEffect(() => {
    if (!revealed) {
      return;
    }
    const timer = setTimeout(() => setRevealed(null), 30000);
    return () => clearTimeout(timer);
  }, [revealed]);

  useEffect(() => {
    if (!open) {
      setRevealed(null);
    }
  }, [open]);

  const handleConfirm = async () => {
    const fullLicense = await onConfirm();
    setRevealed(fullLicense);
  };

  return (
    <Modal open={open} onCancel={onCancel} footer={null} title="Reveal License Number" destroyOnHidden>
      <Typography.Paragraph>
        Accessing encrypted license data will be logged. Continue?
      </Typography.Paragraph>

      {revealed ? (
        <Typography.Text strong data-testid="revealed-license">
          {revealed}
        </Typography.Text>
      ) : (
        <Button type="primary" onClick={handleConfirm} loading={loading}>
          Confirm Reveal
        </Button>
      )}
    </Modal>
  );
}
