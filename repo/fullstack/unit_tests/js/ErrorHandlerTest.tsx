import { notification } from 'antd';
import { apiClient } from '../../frontend/src/services/apiClient';

describe('ErrorHandlerTest', () => {
  it('shows toast notification for API error message', async () => {
    const spy = jest.spyOn(notification, 'error').mockImplementation(jest.fn());
    const handlers = (apiClient.interceptors.response as any).handlers;
    const rejected = handlers[0]?.rejected;
    try {
      await rejected({ response: { status: 500, data: { message: 'Server exploded' } } });
    } catch {
    }
    expect(spy).toHaveBeenCalledWith({ message: 'Server exploded' });
    spy.mockRestore();
  });
});
