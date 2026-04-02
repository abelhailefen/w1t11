import { Alert, Button, Card, Form, Image, Input, notification, Typography } from 'antd';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { apiClient } from '../services/apiClient';

type CaptchaPayload = {
  image: string;
  token: string;
};

export function LoginPage() {
  const auth = useAuth();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [captcha, setCaptcha] = useState<CaptchaPayload | null>(null);
  const [showCaptcha, setShowCaptcha] = useState(false);
  const [form] = Form.useForm();

  const fetchCaptcha = async () => {
    const response = await apiClient.get<CaptchaPayload>('/api/v1/auth/captcha');
    setCaptcha(response.data);
    setShowCaptcha(true);
  };

  const onSubmit = async () => {
    const values = await form.validateFields();

    setLoading(true);
    try {
      await auth.login(values.username, values.password, captcha?.token, values.captcha_answer);
      notification.success({ message: 'Login successful' });
      navigate('/');
    } catch (error: any) {
      const status = error?.response?.status;
      const message = error?.response?.data?.message || 'Login failed';
      const captchaRequired = error?.response?.data?.details?.captcha_required;

      if (status === 423 || captchaRequired) {
        await fetchCaptcha();
      }

      notification.error({ message });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-page">
      <Card style={{ width: 420 }}>
        <Typography.Title level={4}>Sign In</Typography.Title>
        <Form form={form} layout="vertical" onFinish={onSubmit}>
          <Form.Item
            name="username"
            label="Username"
            rules={[{ required: true, message: 'Username is required' }]}
            validateTrigger="onBlur"
          >
            <Input />
          </Form.Item>
          <Form.Item
            name="password"
            label="Password"
            rules={[
              { required: true, message: 'Password is required' },
              { min: 8, message: 'Password must be at least 8 characters' },
            ]}
            validateTrigger="onBlur"
          >
            <Input.Password />
          </Form.Item>

          {showCaptcha && captcha ? (
            <>
              <Alert message="CAPTCHA is required after failed attempts." type="warning" style={{ marginBottom: 16 }} />
              <div style={{ marginBottom: 12 }}>
                <Image preview={false} src={`data:image/png;base64,${captcha.image}`} alt="captcha" />
              </div>
              <Form.Item
                name="captcha_answer"
                label="Captcha Answer"
                rules={[{ required: true, message: 'Captcha answer is required' }]}
                validateTrigger="onBlur"
              >
                <Input />
              </Form.Item>
            </>
          ) : null}

          <Button type="primary" htmlType="submit" loading={loading} disabled={loading} block>
            Login
          </Button>
        </Form>
      </Card>
    </div>
  );
}
