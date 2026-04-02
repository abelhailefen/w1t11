# Regulatory Operations & Analytics Portal

Module 1 (Foundation) scaffold for a Docker-first fullstack platform:
- Backend: Symfony 6.4 on PHP 8.2 (`http://localhost:8000`)
- Frontend: React 18 + Ant Design 5 (`http://localhost:3000`)
- Database: MySQL 8.0 (`localhost:3306`)
- Test runner service: `frontend-test` (Node 18)

Module 2 (Auth & Users) is implemented:
- JWT authentication (`Authorization: Bearer <token>`, 1 hour TTL)
- User registration/login/logout/me endpoints
- Failed-login lockout policy (5 failures -> 15 min lock)
- Local CAPTCHA generation/verification (GD, no network)
- Admin user management (list users, role update, password reset)

## One-command startup

```bash
docker compose up --build
```

This starts MySQL, backend, frontend, and frontend-test. Backend startup automatically:
1. waits for MySQL health
2. runs migrations
3. runs idempotent seed command

## Seeded users

- `admin` / `Admin@123` -> `ROLE_SYSTEM_ADMIN`
- `user` / `User@123` -> `ROLE_USER`
- `content_admin` / `Content@123` -> `ROLE_CONTENT_ADMIN`
- `reviewer` / `Reviewer@123` -> `ROLE_CREDENTIAL_REVIEWER`
- `analyst` / `Analyst@123` -> `ROLE_ANALYST`

## Authentication API

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET /api/v1/auth/captcha`
- `POST /api/v1/auth/captcha/verify`

Admin-only:
- `GET /api/v1/admin/users`
- `PATCH /api/v1/admin/users/{id}/role`
- `POST /api/v1/admin/users/{id}/reset-password`
- `POST /api/v1/admin/step-up/verify`

All errors are structured as:

```json
{
  "code": 401,
  "message": "Unauthorized",
  "details": {}
}
```

## Frontend Auth UI

- Login page: `http://localhost:3000/login`
- Dashboard: `http://localhost:3000/` (requires auth)
- User management: `http://localhost:3000/admin/users` (System Admin only)

Flow:
1. Login with seeded credentials
2. JWT is kept in memory by AuthContext
3. Axios interceptor attaches token to API calls
4. On `401`, frontend redirects to `/login`

## Health check

Backend health endpoint:

```bash
curl http://localhost:8000/api/v1/health
```

## Run all tests (Docker only)

From `fullstack/`:

```bash
./run_tests.sh
```

The script executes:
- PHP unit tests in `backend`
- frontend unit tests in `frontend-test`
- API tests in `backend`

## Environment variable policy

- Runtime variables are explicitly set in `docker-compose.yml` service `environment` sections.
- `.env.example` is provided for documentation only.

## Module status

- [x] Module 1: Foundation
- [x] Module 2: Auth & Users
- [ ] Module 3: Practitioner Profiles
- [ ] Module 4: Credential Review
- [ ] Module 5: Appointment Scheduling
- [ ] Module 6: Question Bank
- [ ] Module 7: Analytics & Dashboards
- [ ] Module 8: Audit & Data Governance
- [ ] Module 9: Polish & Integration
