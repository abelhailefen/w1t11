import { Result } from 'antd';

export function UnauthorizedPage() {
  return <Result status="403" title="403" subTitle="You are not authorized to access this resource." />;
}
