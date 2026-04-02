# Regulatory Operations & Analytics Portal - Implementation Plan

## 1) Delivery Sequence

Build in strict vertical slices:

1. Foundation
2. Auth & Users
3. Practitioner Profiles
4. Credential Review
5. Appointment Scheduling
6. Question Bank
7. Analytics & Dashboards
8. Audit & Data Governance
9. Polish & Integration

For each module:
- Backend: Entity -> Repository -> Service -> Controller -> Security -> Migration
- Frontend: Page -> Components -> Hooks/Context -> API client
- Tests: PHP unit + frontend unit + API integration (inside Docker only)
- Verification: run module end-to-end via Docker Compose

## 2) Repository Layout

```text
fullstack/
├── docker-compose.yml
├── README.md
├── run_tests.sh
├── .env.example                  # documentation only, not consumed by compose
├── implementationplan.md
├── unit_tests/
│   ├── php/
│   └── js/
├── API_tests/
│   └── php/
├── backend/
│   ├── Dockerfile
│   ├── docker/
│   │   ├── apache-vhost.conf
│   │   ├── php.ini
│   │   ├── entrypoint.sh
│   │   └── wait-for-db.sh
│   ├── src/
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Service/
│   │   ├── Security/
│   │   ├── EventListener/
│   │   ├── DTO/
│   │   ├── Enum/
│   │   ├── Validator/
│   │   └── Command/
│   ├── config/
│   ├── migrations/
│   └── tests/
└── frontend/
    ├── Dockerfile
    ├── nginx.conf
    ├── src/
    │   ├── components/
    │   ├── pages/
    │   ├── services/
    │   ├── hooks/
    │   ├── context/
    │   ├── utils/
    │   ├── types/
    │   └── theme/
    └── public/
```

## 3) Docker Topology (Docker-only)

Services (all on named network `app_net`):

1. `mysql` (`mysql:8.0`)
   - Port: `3306:3306`
   - Volume: `mysql_data`
   - Healthcheck: `mysqladmin ping`

2. `backend` (`php:8.2-apache` custom image)
   - Port: `8000:80`
   - Depends on healthy `mysql`
   - Runs migrations and idempotent seed at startup
   - Upload volume mounted outside web root

3. `frontend` (multi-stage `node:18` build -> `nginx:alpine`)
   - Port: `3000:80`
   - No CDN; fully bundled assets

4. `frontend-test` (`node:18`)
   - Dedicated test runner container for Jest
   - Required by `run_tests.sh`

## 4) Environment Variables Policy

- `docker-compose.yml` contains all runtime environment variables inline in each service.
- `.env.example` is documentation-only and not required by Docker Compose.
- No secrets hardcoded in source code.

Core vars:
- `APP_ENV`, `APP_SECRET`
- `DATABASE_URL` pieces (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`)
- `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`
- `LICENSE_ENCRYPTION_KEY`
- `DUPLICATE_SIMILARITY_THRESHOLD` (default 80)
- `APPOINTMENT_SLOT_MINUTES` (default 30)
- `APPOINTMENT_HOLD_MINUTES` (default 5)
- `ALERT_REJECTION_THRESHOLD` (default 5)
- `ALERT_REJECTION_WINDOW_HOURS` (default 24)

## 5) Backend Architecture (Symfony 6.4)

Selected packages:
- `lexik/jwt-authentication-bundle` (JWT auth approved)
- `symfony/security-bundle`, `symfony/validator`, `symfony/workflow`
- `doctrine/orm`, `doctrine/doctrine-bundle`, `doctrine/migrations`
- `phpoffice/phpspreadsheet` (xlsx/csv import/export)
- `dompdf/dompdf` (PDF export)

Planned frontend libraries:
- `@ant-design/charts`
- `react-quill`
- `katex`

Core services:
- `AuthService`, `CaptchaService`, `HumanVerificationInterface` (local stub)
- `EncryptionService` (AES-256)
- `CredentialWorkflowService`, `StepUpAuthService`
- `BookingService` (transaction + row locking)
- `QuestionSimilarityService`
- `AnalyticsService`, `AuditLogService`, `AlertService`

## 6) Database Schema Outline

### 6.1 Reference / dimensions
- `firms` (id, name, address, status, created_at)
- `locations` (id, name, address, capacity, created_at)
- `org_units` (id, name, parent_id, created_at)
- `question_categories` (id, name, description, parent_id, created_at)
- `question_tags` (id, name, created_at)
- `system_settings` (id, setting_key, setting_value, updated_by, updated_at)

### 6.2 Auth & users
- `users` (id, username, password_hash, role, status, created_at, updated_at)
- `login_attempts` (id, user_id, username, attempt_at, success, ip_address)
- `account_lockouts` (id, user_id, failed_count, locked_until, captcha_required)
- `captcha_challenges` (id, token, challenge_payload, expected_answer_hash, expires_at, used_at)
- `password_reset_requests` (id, target_user_id, initiated_by_admin_id, expires_at, used_at)

### 6.3 Practitioners / credentials
- `practitioners` (id, full_name, firm_id, license_number_encrypted, license_jurisdiction, contact_email, contact_phone, status, created_at, updated_at)
- `credential_submissions` (id, practitioner_id, current_state, created_by, created_at, updated_at)
- `credential_versions` (id, submission_id, version_no, payload_json, state, rejection_comment, created_by, created_at)
- `credential_files` (id, credential_version_id, original_name, mime_type, size_bytes, storage_path, uploaded_at)
- `sensitive_access_logs` (id, user_id, entity_type, entity_id, field_name, reason, ip_address, accessed_at)

### 6.4 Scheduling
- `availability_windows` (id, practitioner_id, org_unit_id, weekday, start_time, end_time, slot_minutes)
- `appointment_slots` (id, practitioner_id, location_id, start_at, end_at, capacity, available_count, status)
- `appointments` (id, practitioner_id, location_id, slot_id, booked_by, state, held_until, reschedule_count, booked_at, cancelled_at)
- `appointment_reschedule_history` (id, appointment_id, old_slot_id, new_slot_id, changed_by, changed_at)

### 6.5 Question bank
- `questions` (id, category_id, status, current_version_id, created_by, created_at, updated_at)
- `question_versions` (id, question_id, version_no, content_html, plain_text_index, metadata_json, created_by, created_at)
- `question_tag_map` (question_id, tag_id)
- `question_import_jobs` (id, initiated_by, file_name, format, status, result_json, created_at, completed_at)
- `question_similarity_flags` (id, question_version_id, matched_question_id, similarity_score, threshold, status)

### 6.6 Audit / analytics
- `analytics_saved_queries` (id, name, query_definition_json, created_by, created_at)
- `analytics_features` (id, name, definition_json, created_by, created_at)
- `audit_logs` (id, occurred_at, user_id, action_type, entity_type, entity_id, old_value_json, new_value_json, ip_address, retention_expires_at)
- `alerts` (id, alert_type, severity, message, context_json, triggered_at, acknowledged_by, acknowledged_at)

## 7) API Endpoint Inventory

### 7.1 Auth
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET /api/v1/auth/captcha` -> `{challenge_image, token}`
- `POST /api/v1/auth/captcha/verify`
- `POST /api/v1/admin/users/{id}/reset-password`

### 7.2 Admin settings / reference data
- `GET /api/v1/admin/settings`
- `PUT /api/v1/admin/settings`
- `GET|POST|PUT|DELETE /api/v1/admin/firms`
- `GET|POST|PUT|DELETE /api/v1/admin/locations`
- `GET|POST|PUT|DELETE /api/v1/admin/org-units`
- `GET|POST|PUT|DELETE /api/v1/admin/question-tags`
- `GET|POST|PUT|DELETE /api/v1/admin/users`

### 7.3 Practitioners / credentials
- `GET|POST /api/v1/practitioners`
- `GET|PATCH /api/v1/practitioners/{id}`
- `POST /api/v1/practitioners/{id}/license/reveal`
- `POST /api/v1/practitioners/{id}/credentials/upload`
- `GET /api/v1/practitioners/{id}/credentials`
- `POST /api/v1/credentials/{id}/submit`
- `POST /api/v1/credentials/{id}/start-review`
- `POST /api/v1/credentials/{id}/approve`
- `POST /api/v1/credentials/{id}/reject`
- `POST /api/v1/credentials/{id}/request-resubmission`
- `GET /api/v1/credentials/{id}/versions`
- `POST /api/v1/credentials/{id}/rollback`

### 7.4 Scheduling
- `GET|PUT /api/v1/availability`
- `GET /api/v1/appointments/slots`
- `POST /api/v1/appointments/hold`
- `POST /api/v1/appointments/book`
- `POST /api/v1/appointments/{id}/reschedule`
- `POST /api/v1/appointments/{id}/cancel`
- `GET /api/v1/appointments/calendar`

### 7.5 Question bank
- `GET|POST /api/v1/questions`
- `GET|PATCH /api/v1/questions/{id}`
- `POST /api/v1/questions/{id}/publish`
- `PATCH /api/v1/questions/{id}/status`
- `POST /api/v1/questions/import`
- `GET /api/v1/questions/export`
- `GET /api/v1/questions/{id}/versions`
- `POST /api/v1/questions/{id}/rollback`

### 7.6 Analytics, audit, alerts
- `POST /api/v1/analytics/query`
- `GET /api/v1/dashboards/compliance`
- `GET /api/v1/dashboards/trend`
- `GET /api/v1/dashboards/distribution`
- `GET /api/v1/dashboards/correlation`
- `GET /api/v1/reports/export.csv`
- `GET /api/v1/reports/export.pdf`
- `GET /api/v1/audit/logs`
- `GET /api/v1/alerts`
- `POST /api/v1/alerts/{id}/acknowledge`
- `POST /api/v1/admin/step-up/verify`

## 8) Module File Plan (delta highlights)

Module 2 additions:
- Frontend `pages/UserManagementPage.tsx`

Module 3 additions:
- Frontend `pages/PractitionerListPage.tsx`
- Frontend `pages/PractitionerDetailPage.tsx`
- Frontend `pages/FirmManagementPage.tsx`
- Frontend `components/LicenseRevealModal.tsx`

Module 9 additions:
- Frontend `pages/SystemSettingsPage.tsx`

Scheduling hold expiration:
- `appointments.held_until` enforced in `BookingService`
- New command: `app:appointments:cleanup-expired-holds`

## 9) Testing Plan

- `run_tests.sh` is the only host entrypoint.
- All tests run in containers with `docker compose exec`.
- Backend testsuites: `unit`, `api`.
- Frontend tests run in `frontend-test` container with Jest.

## 10) Seed Data (idempotent)

Seed command creates:
- `admin / Admin@123` (`ROLE_SYSTEM_ADMIN`)
- `user / User@123` (`ROLE_USER`)
- `content_admin / Content@123` (`ROLE_CONTENT_ADMIN`)
- `reviewer / Reviewer@123` (`ROLE_CREDENTIAL_REVIEWER`)
- `analyst / Analyst@123` (`ROLE_ANALYST`)
- Sample firms, practitioners, appointments, and questions

## 11) Module 1 Immediate Deliverables

- Docker Compose stack with `mysql`, `backend`, `frontend`, `frontend-test`
- Backend container entrypoint runs migration + seed automatically
- Frontend React + Ant Design scaffold builds to nginx
- Health endpoint for smoke validation
- Base PHPUnit/Jest setup and `run_tests.sh`

## 12) Module 2 Delivery Snapshot

Implemented scope for Auth & Users includes:
- User, LoginAttempt, AccountLockout entities with repositories
- AuthService (register, login, lockout, checkLockout, attempt tracking)
- CaptchaService with local math challenge image generation (GD)
- HumanVerificationInterface + NullHumanVerificationService (env-controlled)
- StepUpAuthService for password re-check and justification logging
- AuthController and AdminUserController endpoints with validator-backed input checks
- JWT-secured API firewall and role hierarchy enforcement
- React AuthContext, RoleGuard, LoginPage, UserManagementPage, role-based navigation
- Unit tests and API tests for auth and admin flows

Module 2 completion checkpoint:
- Backend startup fixed for Symfony runtime Dotenv by adding committed fallback files: `backend/.env` and `backend/.env.test`
- Interactive OpenAPI docs added with Nelmio at `GET /api/doc` and spec at `GET /api/doc.json`
- Health, auth, and admin controllers are documented with request/response/security schemas
- Test execution stabilized in Docker with explicit Symfony test env in `run_tests.sh`
- Module 2 suites green: backend unit, backend API, and frontend Jest

## 13) Module 3 Delivery Snapshot

Implemented scope for Practitioner Profiles includes:
- `Firm`, `Practitioner`, `SensitiveAccessLog`, and `CredentialFile` entities with repositories
- `PractitionerService` with masked default responses and explicit license reveal endpoint
- `EncryptionService` for license-at-rest encryption with deterministic masking format
- `FileUploadService` for credential validation/storage under `CREDENTIAL_UPLOAD_DIR` (outside web root)
- `AdminFirmController` and `PractitionerController` endpoints with OpenAPI annotations
- Idempotent seed updates for firms and practitioners with encrypted licenses
- React practitioner list/detail pages, firm management page, and reveal modal UI
- Unit/API/frontend tests for encryption, uploads, CRUD, reveal logging, and firm admin flows

Module 3 completion checkpoint:
- Swagger includes `/api/v1/practitioners*` and `/api/v1/admin/firms*` under `GET /api/doc`
- Reveal flow logs to `sensitive_access_logs` on every successful reveal
- Upload/download flow verified with persisted `credential_files` metadata and file retrieval
- Backend and frontend Module 3 suites run green in Docker

## 14) Module 4 Delivery Snapshot

Implemented scope for Credential Review includes:
- `CredentialState` enum and `CredentialSubmission` / `CredentialVersion` entities
- `credential_files` linkage switched to `credential_version_id`
- Migration for `credential_submissions`, `credential_versions`, and credential file FK backfill/update
- `CredentialWorkflowService` with strict transitions and descriptive 422 errors on invalid transitions
- Mandatory rejection comments enforced server-side
- Rollback flow restricted to `ROLE_SYSTEM_ADMIN` and gated by `StepUpAuthService`
- `CredentialController` endpoints with OpenAPI annotations for create/submit/review/approve/reject/resubmission/versions/rollback/queue
- Frontend credential queue and detail pages with state badges, timeline history, role-based actions, and rollback modal
- Unit/API/frontend tests for state transitions, rollback, queue filtering, and UI action validation

Module 4 completion checkpoint:
- Full workflow verified: create -> submit -> start-review -> approve
- Rejection + comment + resubmit workflow verified
- Rollback with step-up auth verified and produces a new version
- Swagger includes all credential workflow endpoints under `/api/v1/credentials*`

## 15) Module 5 Delivery Snapshot

Implemented scope for Appointment Scheduling includes:
- `Location`, `AvailabilityWindow`, `AppointmentSlot`, `Appointment`, `AppointmentRescheduleHistory` entities
- `BookingService` with transactional row-level locks (`SELECT ... FOR UPDATE`) for hold/confirm/reschedule/cancel
- Conflict detection against overlapping confirmed appointments
- 90-day booking advance guard and 24-hour cancellation window rule
- Hold expiration enforcement server-side with 410 response on expired confirms
- `SlotGenerationService` for materializing slots from recurring windows
- `AppointmentController` endpoints for availability, slot list, hold/book/reschedule/cancel/calendar, and appointment list
- `AdminLocationController` CRUD with deactivate semantics
- Cleanup command `app:appointments:cleanup-expired-holds`
- Seed updates for locations, windows, generated slots, and mixed sample appointments
- Frontend calendar workbench, booking drawer with countdown, appointment list, availability admin, and location admin pages
- Unit/API/frontend tests including required concurrency test (parallel holds, one success/one failure)

Module 5 completion checkpoint:
- Full scheduling flow verified: configure availability -> generate slots -> hold -> book -> reschedule -> cancel
- Hold expiry enforcement verified via expired hold confirm rejection
- Swagger includes all scheduling endpoints under `/api/v1/availability`, `/api/v1/appointments*`, and `/api/v1/admin/locations*`

## 16) Module 6 Delivery Snapshot

Implemented scope for Question Bank includes:
- Question bank entities and repositories for categories, tags, questions, versions, import jobs, and similarity flags
- Migration for question versioning and import/similarity/tag-map tables
- `QuestionService` for CRUD, version-on-every-edit, status transitions, publish flow with duplicate check, and rollback with step-up
- `QuestionSimilarityService` server-side duplicate detection against published question corpus using percentage similarity and configurable threshold
- `QuestionImportExportService` with CSV/XLSX import validation (per-row success/error) and CSV/XLSX export
- `QuestionController` endpoints for list/detail/create/update/publish/status/import/export/versions/rollback with OpenAPI annotations
- Admin controllers for question tags and categories with OpenAPI annotations
- Frontend question bank list/editor/review/history/import-export pages and components
- Docker-run unit/API/frontend tests for duplicate detection, import validation, transitions, CRUD/publish/import/export/rollback/admin CRUD

Module 6 completion checkpoint:
- Rich text question draft/save/publish flow verified with duplicate warning review
- Import workflow supports mixed valid/invalid rows with per-row results
- Export workflow produces downloadable CSV/XLSX
- Swagger includes all question bank endpoints under `/api/v1/questions*`, `/api/v1/admin/question-tags*`, and `/api/v1/admin/question-categories*`

## 17) Module 7 Delivery Snapshot

Implemented scope for Analytics & Dashboards includes:
- `OrgUnit`, `AnalyticsSavedQuery`, and `AnalyticsFeature` entities with repositories
- Migration for analytics saved query/feature tables
- `AnalyticsService` with structured query execution using Doctrine QueryBuilder, KPI calculations, trend/distribution/correlation data
- `ReportExportService` CSV and dompdf PDF export
- Controllers for analytics query/save/list/features, dashboards (compliance/trend/distribution/correlation), and report exports with OpenAPI annotations
- Admin org-unit CRUD controller with OpenAPI annotations
- Frontend analytics workbench, compliance dashboard, and chart components (`TrendChart`, `DistributionChart`, `CorrelationChart`)
- Dashboard home upgraded to real KPI overview and quick links
- Tests for KPI calculations, query builder behavior, trend intervals, analytics API endpoints, export endpoints, authorization, and frontend dashboard/workbench rendering

Module 7 completion checkpoint:
- Compliance dashboard displays real KPI values and trend data from API
- Analytics workbench runs, saves, reloads structured queries and supports CSV export path
- PDF and CSV report endpoints verified
- Swagger includes analytics/dashboard/report endpoints under `/api/v1/analytics*`, `/api/v1/dashboards*`, `/api/v1/reports*`, and admin org units under `/api/v1/admin/org-units*`

## 18) Module 8 Delivery Snapshot

Implemented scope for Audit & Data Governance includes:
- `AuditLog` and `Alert` entities with repositories and migration for `alerts`
- `AuditLogService` fail-safe logging (`never throw`) with 7-year retention expiry default
- Doctrine lifecycle `AuditEventListener` on auditable entities (`User`, `Practitioner`, `CredentialSubmission`, `Appointment`, `Question`)
- Service-level action audits wired into auth/workflow/scheduling/question/practitioner/admin reset flows
- `AlertService` anomaly detection and deduplicated alert creation
  - rejected credentials threshold by firm
  - failed login threshold by username
  - high-volume license reveal threshold by user
- Commands:
  - `app:alerts:check` anomaly sweep
  - `app:audit:cleanup` retention cleanup (dry-run default, `--force` apply)
- API controllers with OpenAPI annotations:
  - `GET /api/v1/audit/logs`
  - `GET /api/v1/alerts`
  - `POST /api/v1/alerts/{id}/acknowledge`
- Frontend admin experience:
  - `AuditLogPage` with filters, pagination, expandable old/new payload details, CSV export
  - `SystemAlertsPage` with severity badges, context modal, and acknowledge action
  - Dashboard alert widget and admin navigation badge/count

Module 8 completion checkpoint:
- Login and practitioner create flows produce audit records visible via `GET /api/v1/audit/logs`
- Alert sweep and acknowledge flow verified end-to-end via API and command
- Retention cleanup dry-run verified via `app:audit:cleanup`
- Swagger includes `/api/v1/audit/logs`, `/api/v1/alerts`, `/api/v1/alerts/{id}/acknowledge`

## 19) Module 9 Delivery Snapshot

Implemented scope for Polish & Integration includes:
- `SystemSettingsPage` with full admin-editable operational settings (`/api/v1/admin/settings`)
- Admin settings API (`GET/PUT /api/v1/admin/settings`) with OpenAPI annotations
- Settings wiring into core services using DB-backed values with env fallback:
  - `AuthService` (lockout attempts/duration)
  - `BookingService` / `SlotGenerationService` (hold + slot minutes)
  - `QuestionSimilarityService` (duplicate threshold)
  - `AlertService` (rejection threshold/window)
- Sidebar and route integration finalized for all module pages including admin governance/settings
- Global API error notification handling in frontend interceptor
- UX tightening pass for loading/disabled submit states, form validation triggers, toast feedback, and responsive table scrolling
- Final docs pass in `README.md` and completion tracking through modules 3-9

Module 9 completion checkpoint:
- All module areas are reachable via role-appropriate navigation
- System settings persist and influence runtime service behavior
- Full Docker test run is executed as release gate via `./run_tests.sh`

## 20) Fix Pass Remediation Snapshot

Resolved delivery acceptance findings:
- **Blocker (Privilege Escalation):** Public registration no longer accepts role assignment; backend hard-sets `ROLE_USER`, OpenAPI register schema removed `role`, and role elevation remains admin-only via `PATCH /api/v1/admin/users/{id}/role`.
- **High (Credential Object Authorization):** Credential read authorization is now object-scoped for `/api/v1/credentials/{id}/versions` and queue behavior is role-aware (reviewer/admin = global queue, regular user = own submissions only).
- **High (Lockout Policy):** Lockout lifecycle implemented: 5 failed attempts produce 15-minute lock (`423`), lock expiry resets counters, CAPTCHA gate remains enforced, and successful login clears lock state.
- **High (License Encryption):** Registration now uses `EncryptionService::encrypt()` for license data. Added one-time remediation command `app:migrate:fix-license-encryption` and wired it into container startup after migration/seed.
- **High (Compliance KPI Naming):** Compliance API now returns both prompt-literal KPI names and existing domain KPI fields; dashboard and compliance CSV/PDF exports include the expanded KPI set.
- **Medium (CSRF Documentation):** Added explicit CSRF strategy rationale in `backend/config/packages/security.yaml` and README Security section for JWT header-based stateless auth.
