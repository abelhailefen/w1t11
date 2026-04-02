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

From `fullstack/`:

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

## Test command

From `fullstack/`:

```bash
./run_tests.sh
```

This runs:
- backend unit suite
- frontend jest suite
- backend API suite

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
