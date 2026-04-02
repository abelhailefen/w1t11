import { Alert, Button, Drawer, Space, Typography, message } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { appointmentApi, SlotItem } from '../services/appointmentApi';

type Props = {
  open: boolean;
  slot: SlotItem | null;
  onClose: () => void;
  onBooked: () => void;
};

export function BookingDrawer({ open, slot, onClose, onBooked }: Props) {
  const [holding, setHolding] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [appointmentId, setAppointmentId] = useState<number | null>(null);
  const [secondsLeft, setSecondsLeft] = useState(0);

  useEffect(() => {
    if (!open || !appointmentId) {
      return;
    }
    const id = window.setInterval(() => {
      setSecondsLeft((s) => {
        if (s <= 1) {
          window.clearInterval(id);
          return 0;
        }
        return s - 1;
      });
    }, 1000);

    return () => window.clearInterval(id);
  }, [open, appointmentId]);

  const expired = appointmentId !== null && secondsLeft <= 0;
  const durationText = useMemo(() => {
    if (!slot) return '';
    const start = new Date(slot.start_at).getTime();
    const end = new Date(slot.end_at).getTime();
    return `${Math.round((end - start) / 60000)} min`;
  }, [slot]);

  const startHold = async () => {
    if (!slot) return;
    setHolding(true);
    try {
      const response = await appointmentApi.hold(slot.id);
      setAppointmentId(response.data.appointment_id);
      setSecondsLeft(300);
      message.success('Slot held for 5 minutes');
    } catch (error: any) {
      message.error(error?.response?.data?.message || 'Failed to hold slot');
    } finally {
      setHolding(false);
    }
  };

  const confirm = async () => {
    if (!appointmentId) return;
    setConfirming(true);
    try {
      await appointmentApi.book(appointmentId);
      message.success('Appointment booked');
      onBooked();
      onClose();
      setAppointmentId(null);
      setSecondsLeft(0);
    } catch (error: any) {
      message.error(error?.response?.data?.message || 'Booking failed');
    } finally {
      setConfirming(false);
    }
  };

  return (
    <Drawer open={open} title="Book Slot" width={420} onClose={onClose}>
      {slot ? (
        <Space direction="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text>Practitioner: {slot.practitioner.full_name}</Typography.Text>
          <Typography.Text>Location: {slot.location.name}</Typography.Text>
          <Typography.Text>Time: {new Date(slot.start_at).toLocaleString()} - {new Date(slot.end_at).toLocaleTimeString()}</Typography.Text>
          <Typography.Text>Duration: {durationText}</Typography.Text>
          {!appointmentId ? (
            <Button type="primary" loading={holding} onClick={startHold}>Hold &amp; Book</Button>
          ) : (
            <Space direction="vertical" style={{ width: '100%' }}>
              <Typography.Text data-testid="countdown">Time left: {secondsLeft}s</Typography.Text>
              {expired ? <Alert type="warning" message="Hold expired" /> : <Button type="primary" loading={confirming} disabled={confirming} onClick={confirm}>Confirm Booking</Button>}
            </Space>
          )}
        </Space>
      ) : null}
    </Drawer>
  );
}
