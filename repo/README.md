# Regulatory Operations & Analytics Portal

Production-style, Docker-first fullstack platform for regulatory operations:
- secure auth and role-based access
- practitioner profile and credential workflows
- appointment scheduling
- question bank authoring and duplicate detection
- analytics dashboards and exports
- audit logging, alerts, and governance controls

## Technology stack

- Backend: Symfony 6.4, PHP 8.2, Doctrine ORM, MySQL 8.0, JWT auth
- Frontend: React 18, TypeScript, Ant Design 5, React Router 6
- Reporting/Data: dompdf, PhpSpreadsheet, @ant-design/charts
- Runtime: Docker Compose

## One-command startup

From the repository root:

```bash
docker compose up --build
```

## URLs

- Frontend: `http://localhost:3000`
- Backend API: `http://localhost:8000`
- Swagger UI: `http://localhost:8000/api/doc`
- OpenAPI JSON: `http://localhost:8000/api/doc.json`

## Seeded users

- `admin` / `Admin@123` -> `ROLE_SYSTEM_ADMIN`
- `user` / `User@123` -> `ROLE_USER`
- `content_admin` / `Content@123` -> `ROLE_CONTENT_ADMIN`
- `reviewer` / `Reviewer@123` -> `ROLE_CREDENTIAL_REVIEWER`
- `analyst` / `Analyst@123` -> `ROLE_ANALYST`

## Security

- Public registration (`POST /api/v1/auth/register`) always creates `ROLE_USER`; privileged roles are only assignable via `PATCH /api/v1/admin/users/{id}/role` by system admin.
- Authentication is JWT Bearer in the `Authorization` header and API firewalls are stateless.
- CSRF protection uses a double-submit cookie pattern: backend sets `XSRF-TOKEN` for authenticated sessions, and state-changing requests (`POST|PUT|PATCH|DELETE`) must send matching `X-XSRF-TOKEN` header or they are rejected with `403`.
- CSRF validation excludes bootstrap endpoints: `/api/v1/auth/login`, `/api/v1/auth/register`, `/api/v1/auth/captcha`, and `/api/doc`.
- Security note: JWT keys are generated automatically on first backend container startup. In production, replace `APP_SECRET`, `JWT_PASSPHRASE`, and `LICENSE_ENCRYPTION_KEY` values in `docker-compose.yml` with strong secrets.

## Test command

From the repository root:

```bash
./run_tests.sh
```

This runs:
- backend unit suite
- frontend jest suite
- backend API suite

## Offline / Air-Gapped Deployment

For environments without internet access during build:

1. **Pre-built images (recommended):** On a machine with internet, build and save images:
   ```bash
   docker compose build
   docker save regops-backend regops-frontend -o regops-images.tar
   ```
   Transfer `regops-images.tar` to the air-gapped host and load:
   ```bash
   docker load -i regops-images.tar
   docker compose up
   ```

2. **Vendored dependencies:** Alternatively, install dependencies locally before transfer:
   - Backend: Run `composer install` on a connected machine, then include the `vendor/` directory
   - Frontend: Run `npm install` on a connected machine, then include the `node_modules/` directory

   Both directories are excluded from version control by `.gitignore` but can be included in a deployment bundle.

At runtime, the application makes zero external network calls. All CAPTCHA generation, PDF rendering, and chart rendering are fully local.

## Core URLs/pages

- Login: `http://localhost:3000/login`
- Dashboard: `http://localhost:3000/dashboard`
- Practitioners: `http://localhost:3000/practitioners`
- Calendar: `http://localhost:3000/calendar`
- Appointments: `http://localhost:3000/appointments`
- Question Bank: `http://localhost:3000/questions`
- Analytics Workbench: `http://localhost:3000/analytics/workbench`
- Compliance Dashboard: `http://localhost:3000/analytics/compliance`
- Admin System Settings: `http://localhost:3000/admin/settings`
- Admin Audit Logs: `http://localhost:3000/admin/audit-logs`
- Admin Alerts: `http://localhost:3000/admin/alerts`

## Module overview

- Module 1: Foundation (Docker + app scaffolding)
- Module 2: Auth & Users (JWT auth, lockout/captcha, user admin)
- Module 3: Practitioner Profiles (firms/practitioners, encrypted license, file uploads)
- Module 4: Credential Review (state machine, reviewer queue, rollback/step-up)
- Module 5: Appointment Scheduling (availability, slot generation, booking/reschedule/cancel)
- Module 6: Question Bank (versioning, duplicate detection, import/export)
- Module 7: Analytics & Dashboards (query workbench, KPI/trend/distribution/correlation, CSV/PDF)
- Module 8: Audit & Data Governance (audit logs, alerts, retention cleanup)
- Module 9: Polish & Integration (system settings, global error handling, navigation integration, consistency)

## Environment variables reference

Configured in `docker-compose.yml` (runtime source of truth):

- `APP_ENV`, `APP_SECRET`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DATABASE_URL`
- `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`
- `LICENSE_ENCRYPTION_KEY`
- `CREDENTIAL_UPLOAD_DIR`
- `HUMAN_VERIFICATION_ENABLED`
- `DUPLICATE_SIMILARITY_THRESHOLD`
- `APPOINTMENT_SLOT_MINUTES`
- `APPOINTMENT_HOLD_MINUTES`
- `ALERT_REJECTION_THRESHOLD`
- `ALERT_REJECTION_WINDOW_HOURS`

System-level runtime overrides are editable by system admin at `System Settings` and persisted in `system_settings`.
