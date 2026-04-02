import { createContext, ReactNode, useContext, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiClient, setUnauthorizedHandler } from '../services/apiClient';
import { clearToken, getToken, setTokenValue } from '../services/authStore';

export type AuthUser = {
  id: number;
  username: string;
  role: string;
  status: string;
};

type AuthContextValue = {
  token: string | null;
  user: AuthUser | null;
  isAuthenticated: boolean;
  login: (username: string, password: string, captchaToken?: string, captchaAnswer?: string) => Promise<void>;
  logout: () => Promise<void>;
  hasRole: (roles: string[]) => boolean;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

const roleRank: Record<string, number> = {
  ROLE_USER: 1,
  ROLE_CONTENT_ADMIN: 2,
  ROLE_CREDENTIAL_REVIEWER: 2,
  ROLE_ANALYST: 2,
  ROLE_SYSTEM_ADMIN: 99,
};

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setAuthToken] = useState<string | null>(getToken());
  const [user, setUser] = useState<AuthUser | null>(null);
  const navigate = useNavigate();

  useEffect(() => {
    setUnauthorizedHandler(() => {
      setAuthToken(null);
      setUser(null);
      clearToken();
      navigate('/login');
    });
  }, [navigate]);

  useEffect(() => {
    if (!token) {
      return;
    }

    apiClient
      .get<AuthUser>('/api/v1/auth/me')
      .then((response) => setUser(response.data))
      .catch(() => {
        setAuthToken(null);
        clearToken();
      });
  }, [token]);

  const value = useMemo<AuthContextValue>(
    () => ({
      token,
      user,
      isAuthenticated: Boolean(token && user),
      login: async (username: string, password: string, captchaToken?: string, captchaAnswer?: string) => {
        const response = await apiClient.post<{ token: string; user: AuthUser }>('/api/v1/auth/login', {
          username,
          password,
          captcha_token: captchaToken,
          captcha_answer: captchaAnswer,
        });

        setTokenValue(response.data.token);
        setAuthToken(response.data.token);
        setUser(response.data.user);
      },
      logout: async () => {
        if (token) {
          await apiClient.post('/api/v1/auth/logout');
        }
        clearToken();
        setAuthToken(null);
        setUser(null);
        navigate('/login');
      },
      hasRole: (roles: string[]) => {
        if (!user) {
          return false;
        }

        if (user.role === 'ROLE_SYSTEM_ADMIN') {
          return true;
        }

        const userRank = roleRank[user.role] ?? 0;
        return roles.some((role) => (roleRank[role] ?? 0) <= userRank || role === user.role);
      },
    }),
    [navigate, token, user]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return context;
}
