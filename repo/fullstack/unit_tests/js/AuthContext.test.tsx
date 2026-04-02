import { renderHook } from '@testing-library/react';
import { ReactNode } from 'react';
import { BrowserRouter } from 'react-router-dom';
import { AuthProvider, useAuth } from '../../frontend/src/context/AuthContext';

describe('AuthContext', () => {
  it('hasRole returns false when no user', () => {
    const wrapper = ({ children }: { children: ReactNode }) => (
      <BrowserRouter>
        <AuthProvider>{children}</AuthProvider>
      </BrowserRouter>
    );

    const { result } = renderHook(() => useAuth(), { wrapper });
    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.hasRole(['ROLE_SYSTEM_ADMIN'])).toBe(false);
  });
});
