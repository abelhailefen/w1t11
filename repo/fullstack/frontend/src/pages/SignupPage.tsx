import { Button, Card, Form, Input, notification, Typography } from 'antd';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { apiClient } from '../services/apiClient';

type SignupValues = {
  username: string;
  password: string;
  full_name: string;
  firm_affiliation: string;
  license_number: string;
};

export function SignupPage() {
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const [form] = Form.useForm<SignupValues>();

  const onSubmit = async () => {
    const values = await form.validateFields();
    setLoading(true);
    try {
      await apiClient.post('/api/v1/auth/register', values);
      notification.success({ message: 'Registration submitted. Please sign in.' });
      navigate('/login');
    } catch (error: any) {
      const message = error?.response?.data?.message || 'Registration failed';
      notification.error({ message });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-page">
      <Card style={{ width: 420 }}>
        <Typography.Title level={4}>Practitioner Sign Up</Typography.Title>
        <Form form={form} layout="vertical" onFinish={onSubmit}>
          <Form.Item name="username" label="Username" rules={[{ required: true }, { min: 3, max: 180 }]}>
            <Input />
          </Form.Item>
          <Form.Item
            name="password"
            label="Password"
            rules={[
              { required: true },
              { min: 8, message: 'Password must be at least 8 characters' },
              {
                pattern: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/,
                message: 'Include uppercase, lowercase, number, and special character',
              },
            ]}
          >
            <Input.Password />
          </Form.Item>
          <Form.Item name="full_name" label="Full Name" rules={[{ required: true }, { min: 2, max: 255 }]}>
            <Input />
          </Form.Item>
          <Form.Item name="firm_affiliation" label="Firm Affiliation" rules={[{ required: true }, { min: 2, max: 255 }]}>
            <Input />
          </Form.Item>
          <Form.Item name="license_number" label="License Number" rules={[{ required: true }, { min: 4, max: 120 }]}>
            <Input />
          </Form.Item>

          <Button type="primary" htmlType="submit" loading={loading} disabled={loading} block>
            Register
          </Button>

          <div style={{ marginTop: 12, textAlign: 'center' }}>
            <Link to="/login">Already have an account? Sign In</Link>
          </div>
        </Form>
      </Card>
    </div>
  );
}
