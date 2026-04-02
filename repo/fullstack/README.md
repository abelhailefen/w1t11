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
- Interactive Swagger/OpenAPI documentation at `http://localhost:8000/api/doc`

Module 3 (Practitioner Profiles) is implemented:
- Firm management CRUD for system admins
- Practitioner CRUD with encrypted license storage at rest
- Masked license display by default and explicit reveal flow
- Sensitive access logging on every reveal action
- Credential file upload/list/download with storage outside web root
- Practitioner/Firm frontend pages with reveal modal

Module 4 (Credential Review) is implemented:
- Credential submission workflow with strict state transitions and per-transition versioning
- Reviewer queue and version timeline endpoints/pages
- Rejection requires a non-empty comment (server-side enforced)
- Rollback endpoint with ROLE_SYSTEM_ADMIN check and step-up auth verification
- Credential files linked to credential versions

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
- `GET /api/v1/admin/firms`
- `POST /api/v1/admin/firms`
- `PATCH /api/v1/admin/firms/{id}`
- `DELETE /api/v1/admin/firms/{id}`

Practitioner profiles:
- `GET /api/v1/practitioners`
- `POST /api/v1/practitioners`
- `GET /api/v1/practitioners/{id}`
- `PATCH /api/v1/practitioners/{id}`
- `POST /api/v1/practitioners/{id}/license/reveal`
- `POST /api/v1/practitioners/{id}/credentials/upload`
- `GET /api/v1/practitioners/{id}/credentials`
- `GET /api/v1/practitioners/{id}/credentials/{fileId}/download`

Credential review:
- `POST /api/v1/credentials`
- `POST /api/v1/credentials/{id}/submit`
- `POST /api/v1/credentials/{id}/start-review`
- `POST /api/v1/credentials/{id}/approve`
- `POST /api/v1/credentials/{id}/reject`
- `POST /api/v1/credentials/{id}/request-resubmission`
- `GET /api/v1/credentials/{id}/versions`
- `POST /api/v1/credentials/{id}/rollback`
- `GET /api/v1/credentials/queue`

All errors are structured as:

```json
{
  "code": 401,
  "message": "Unauthorized",
  "details": {}
}
```

## Swagger / OpenAPI UI

- Swagger UI: `http://localhost:8000/api/doc`
- Raw OpenAPI JSON: `http://localhost:8000/api/doc.json`
- Use `POST /api/v1/auth/login` in Swagger to get a JWT token.
- Click **Authorize** and paste `Bearer <token>` to call protected endpoints such as `GET /api/v1/auth/me`.

## Frontend Auth UI

- Login page: `http://localhost:3000/login`
- Dashboard: `http://localhost:3000/` (requires auth)
- User management: `http://localhost:3000/admin/users` (System Admin only)
- Practitioners: `http://localhost:3000/practitioners` (authenticated)
- Firms: `http://localhost:3000/admin/firms` (System Admin only)
- Credential queue: `http://localhost:3000/credentials/queue` (Reviewer/System Admin)
- Credential detail: `http://localhost:3000/credentials/:id`

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
- `backend/.env` and `backend/.env.test` provide non-sensitive fallback defaults required by Symfony Dotenv/runtime boot.
- Docker Compose environment variables override `.env` values at runtime.
- `.env.example` is provided for documentation only.

## Module status

- [x] Module 1: Foundation
- [x] Module 2: Auth & Users
- [x] Module 3: Practitioner Profiles
- [x] Module 4: Credential Review
- [x] Module 5: Appointment Scheduling
- [x] Module 6: Question Bank
- [x] Module 7: Analytics & Dashboards
- [ ] Module 8: Audit & Data Governance
- [ ] Module 9: Polish & Integration

## Module 5 scheduling endpoints

- `GET|PUT /api/v1/availability`
- `POST /api/v1/appointments/slots/generate`
- `GET /api/v1/appointments/slots`
- `POST /api/v1/appointments/hold`
- `POST /api/v1/appointments/book`
- `POST /api/v1/appointments/{id}/reschedule`
- `POST /api/v1/appointments/{id}/cancel`
- `GET /api/v1/appointments/calendar`
- `GET|POST|PATCH|DELETE /api/v1/admin/locations`
- `GET|POST /api/v1/questions`
- `GET|PATCH /api/v1/questions/{id}`
- `POST /api/v1/questions/{id}/publish`
- `PATCH /api/v1/questions/{id}/status`
- `POST /api/v1/questions/import`
- `GET /api/v1/questions/export`
- `GET /api/v1/questions/{id}/versions`
- `POST /api/v1/questions/{id}/rollback`
- `GET|POST|PATCH|DELETE /api/v1/admin/question-tags`
- `GET|POST|PATCH|DELETE /api/v1/admin/question-categories`
- `POST /api/v1/analytics/query`
- `POST /api/v1/analytics/queries/save`
- `GET /api/v1/analytics/queries`
- `POST /api/v1/analytics/features`
- `GET /api/v1/analytics/features`
- `GET /api/v1/dashboards/compliance`
- `GET /api/v1/dashboards/trend`
- `GET /api/v1/dashboards/distribution`
- `GET /api/v1/dashboards/correlation`
- `GET /api/v1/reports/export.csv`
- `GET /api/v1/reports/export.pdf`
- `GET|POST|PATCH|DELETE /api/v1/admin/org-units`

Cleanup command:

```bash
docker compose exec backend php bin/console app:appointments:cleanup-expired-holds
```
