# API Specification

**Base URL:** `http://localhost:8000/api/v1`
**Interactive Docs:** `http://localhost:8000/api/doc` (Swagger UI)

## Authentication

All endpoints except login, register, captcha, and health require a valid JWT token.

```
Authorization: Bearer <jwt_token>
```

### CSRF Protection

All state-changing requests (POST, PUT, PATCH, DELETE) to authenticated endpoints require a CSRF token:

1. The server sets an `XSRF-TOKEN` cookie on authenticated responses
2. The client reads this cookie and sends it as the `X-XSRF-TOKEN` header
3. Excluded endpoints: `/auth/login`, `/auth/register`, `/auth/captcha*`, `/api/doc`

### Error Response Format

All errors follow this structure:

```json
{
  "code": 400,
  "message": "Validation failed",
  "details": {
    "field_name": ["Error message"]
  }
}
```

Standard HTTP status codes:
- `200` Success
- `201` Created
- `400` Validation error
- `401` Unauthorized (missing/invalid token)
- `403` Forbidden (insufficient role or CSRF mismatch)
- `404` Not found
- `409` Conflict (duplicate)
- `410` Gone (expired hold)
- `413` Payload too large (file upload)
- `422` Unprocessable (invalid state transition)
- `423` Locked (account lockout)

---

## 1. Health

### `GET /api/v1/health`
- **Auth:** None
- **Response:** `{"status": "ok"}`

---

## 2. Authentication & Users

### `POST /api/v1/auth/register`
- **Auth:** None
- **Note:** Always creates `ROLE_USER`. Role cannot be specified.
- **Request:**
```json
{
  "username": "new_user",
  "password": "StrongPass@123",
  "full_name": "Jordan Blake",
  "firm_affiliation": "Eagle Point Legal",
  "license_number": "NY-123456"
}
```
- **201 Response:**
```json
{
  "id": 9,
  "username": "new_user",
  "full_name": "Jordan Blake",
  "firm_affiliation": "Eagle Point Legal",
  "role": "ROLE_USER",
  "status": "ACTIVE"
}
```
- **409:** Username already exists

### `POST /api/v1/auth/login`
- **Auth:** None
- **Request:**
```json
{
  "username": "admin",
  "password": "Admin@123",
  "captcha_token": "optional",
  "captcha_answer": "optional"
}
```
- **200 Response:**
```json
{
  "token": "eyJ...",
  "user": {
    "id": 1,
    "username": "admin",
    "role": "ROLE_SYSTEM_ADMIN",
    "status": "ACTIVE"
  }
}
```
- **401:** Invalid credentials
- **403:** CAPTCHA required
- **423:** Account locked (returns `locked_until` timestamp)

### `POST /api/v1/auth/logout`
- **Auth:** Bearer JWT
- **Response:** `{"message": "Logged out"}`

### `GET /api/v1/auth/me`
- **Auth:** Bearer JWT
- **Response:**
```json
{
  "id": 1,
  "username": "admin",
  "role": "ROLE_SYSTEM_ADMIN",
  "status": "ACTIVE"
}
```

### `GET /api/v1/auth/captcha`
- **Auth:** None
- **Response:**
```json
{
  "token": "uuid-string",
  "challenge_image": "data:image/png;base64,...",
  "expires_at": "2026-04-03T12:00:00Z"
}
```

### `POST /api/v1/auth/captcha/verify`
- **Auth:** None
- **Request:** `{"token": "uuid", "answer": "10"}`
- **200:** `{"verified": true}`
- **400:** Invalid answer

---

## 3. Admin — User Management

**Role required:** `ROLE_SYSTEM_ADMIN`

### `GET /api/v1/admin/users`
- **Response:** Paginated list of all users

### `PATCH /api/v1/admin/users/{id}/role`
- **Request:** `{"role": "ROLE_ANALYST"}`
- **200:** Updated user

### `POST /api/v1/admin/users/{id}/reset-password`
- **Request:** `{"new_password": "NewPass@123"}`
- **200:** Password reset confirmation

### `POST /api/v1/admin/step-up/verify`
- **Request:** `{"password": "current_password", "justification": "Rollback reason"}`
- **200:** `{"verified": true}`
- **401:** Wrong password

---

## 4. Admin — Reference Data

**Role required:** `ROLE_SYSTEM_ADMIN` for all admin endpoints.

### Firms: `GET|POST|PATCH|DELETE /api/v1/admin/firms[/{id}]`
### Locations: `GET|POST|PATCH|DELETE /api/v1/admin/locations[/{id}]`
### Org Units: `GET|POST|PATCH|DELETE /api/v1/admin/org-units[/{id}]`
### Question Tags: `GET|POST|PATCH|DELETE /api/v1/admin/question-tags[/{id}]`
### Question Categories: `GET|POST|PATCH|DELETE /api/v1/admin/question-categories[/{id}]`

### System Settings: `GET|PUT /api/v1/admin/settings`
- **GET Response:**
```json
{
  "APPOINTMENT_SLOT_MINUTES": "30",
  "APPOINTMENT_HOLD_MINUTES": "5",
  "DUPLICATE_SIMILARITY_THRESHOLD": "80",
  "ALERT_REJECTION_THRESHOLD": "5",
  "ALERT_REJECTION_WINDOW_HOURS": "24"
}
```
- **PUT Request:** Same structure with updated values

---

## 5. Practitioners

**Auth:** Any authenticated user can read. Create/update depends on role.

### `GET /api/v1/practitioners?page=&firm_id=&status=`
- **Response:** Paginated list with **masked** license numbers (`***-XXXX`)

### `POST /api/v1/practitioners`
- **Request:**
```json
{
  "full_name": "Jane Smith",
  "firm_id": 1,
  "license_number": "CA-789012",
  "license_jurisdiction": "California",
  "contact_email": "jane@firm.com",
  "contact_phone": "555-0123"
}
```
- **Note:** `license_number` is AES-256 encrypted before storage

### `GET /api/v1/practitioners/{id}`
- **Response:** Detail with masked license

### `PATCH /api/v1/practitioners/{id}`
- **Request:** Partial update fields

### `POST /api/v1/practitioners/{id}/license/reveal`
- **Roles:** `ROLE_CREDENTIAL_REVIEWER`, `ROLE_SYSTEM_ADMIN`
- **Response:** `{"license_number": "CA-789012"}`
- **Side effect:** Creates a `sensitive_access_log` record AND an audit log entry

### `POST /api/v1/practitioners/{id}/credentials/upload`
- **Roles:** Owner, `ROLE_CREDENTIAL_REVIEWER`, `ROLE_SYSTEM_ADMIN`
- **Request:** Multipart file upload
- **Validation:** PDF/JPG/PNG only, ≤10 MB, server-side MIME check
- **201:** File metadata

### `GET /api/v1/practitioners/{id}/credentials`
- **Roles:** Owner, `ROLE_CREDENTIAL_REVIEWER`, `ROLE_SYSTEM_ADMIN`
- **Response:** List of credential files
- **403:** Unauthorized users

### `GET /api/v1/practitioners/{id}/credentials/{fileId}/download`
- **Roles:** Owner, `ROLE_CREDENTIAL_REVIEWER`, `ROLE_SYSTEM_ADMIN`
- **Response:** Binary file download
- **403:** Unauthorized users

---

## 6. Credential Workflow

### `POST /api/v1/credentials`
- **Request:** `{"practitioner_id": 1}`
- **Creates:** Submission in DRAFT state

### State Transitions

| Endpoint | From State | To State | Required Role |
|----------|-----------|----------|---------------|
| `POST /credentials/{id}/submit` | DRAFT | SUBMITTED | Owner |
| `POST /credentials/{id}/start-review` | SUBMITTED | UNDER_REVIEW | CREDENTIAL_REVIEWER |
| `POST /credentials/{id}/approve` | UNDER_REVIEW | APPROVED | CREDENTIAL_REVIEWER |
| `POST /credentials/{id}/reject` | UNDER_REVIEW | REJECTED | CREDENTIAL_REVIEWER |
| `POST /credentials/{id}/request-resubmission` | UNDER_REVIEW | RESUBMISSION_REQUESTED | CREDENTIAL_REVIEWER |
| `POST /credentials/{id}/submit` | REJECTED/RESUBMISSION_REQUESTED | SUBMITTED | Owner (new version) |

- **Reject requires:** `{"comment": "Reason for rejection"}` (mandatory, enforced server-side)
- **Every transition creates a new version record**
- **422:** Invalid state transition

### `GET /api/v1/credentials/{id}/versions`
- **Roles:** Owner, `ROLE_CREDENTIAL_REVIEWER`, `ROLE_SYSTEM_ADMIN`
- **Response:** Array of version records with state history

### `POST /api/v1/credentials/{id}/rollback`
- **Roles:** `ROLE_SYSTEM_ADMIN` only
- **Requires step-up auth:**
```json
{
  "target_version": 2,
  "password": "admin_password",
  "justification": "Incorrect approval, reverting to version 2"
}
```

### `GET /api/v1/credentials/queue?state=`
- **Roles:** `ROLE_CREDENTIAL_REVIEWER`, `ROLE_SYSTEM_ADMIN` see all; owners see own

---

## 7. Appointment Scheduling

### `GET /api/v1/availability`
- **Response:** List of availability windows

### `PUT /api/v1/availability`
- **Roles:** `ROLE_SYSTEM_ADMIN`
- **Request:** Array of availability window definitions

### `GET /api/v1/appointments/slots?practitioner_id=&date_from=&date_to=`
- **Response:** Available slots for date range

### `POST /api/v1/appointments/slots/generate`
- **Roles:** `ROLE_SYSTEM_ADMIN`
- **Request:** `{"practitioner_id": 1, "date_from": "2026-04-01", "date_to": "2026-04-30"}`

### `POST /api/v1/appointments/hold`
- **Request:** `{"slot_id": 5}`
- **Concurrency:** Uses `SELECT ... FOR UPDATE` row-level locking
- **Response:** `{"appointment_id": 12, "held_until": "2026-04-03T12:05:00Z"}`
- **Hold expires after 5 minutes** (configurable)

### `POST /api/v1/appointments/book`
- **Request:** `{"appointment_id": 12}`
- **Validates:** Hold not expired, slot still available
- **410:** Hold expired

### `POST /api/v1/appointments/{id}/reschedule`
- **Request:** `{"new_slot_id": 8}`
- **Limit:** Maximum 2 reschedules per appointment
- **422:** Reschedule limit exceeded

### `POST /api/v1/appointments/{id}/cancel`
- **Rule:** Blocked within 24 hours unless `ROLE_SYSTEM_ADMIN`
- **403:** Non-admin cancelling within 24h

### `GET /api/v1/appointments/calendar?week=&practitioner_id=`
- **Response:** Week/day calendar data structure

### Booking Rules Enforced Server-Side
- No overlapping bookings for same practitioner + location + time
- Maximum 90 days in advance
- Row-level locking prevents double-booking (concurrent test required)

---

## 8. Question Bank

**Roles:** `ROLE_CONTENT_ADMIN`, `ROLE_SYSTEM_ADMIN` (enforced at API level)

### `GET /api/v1/questions?category=&status=&tag=&difficulty=&page=`
- **Response:** Paginated, filterable list

### `POST /api/v1/questions`
- **Request:**
```json
{
  "content_html": "<p>What is the statute of limitations?</p>",
  "category_id": 1,
  "difficulty": 3,
  "tags": [1, 3, 5]
}
```

### `PATCH /api/v1/questions/{id}`
- **Creates a new version** (content is never updated in-place)

### `POST /api/v1/questions/{id}/publish`
- **Runs duplicate detection** before publishing (trigram similarity, default threshold 80%)
- **200 with warnings:** `{"published": true, "duplicate_flags": [...]}`
- Content Admin must acknowledge flags before re-publishing if flagged

### `PATCH /api/v1/questions/{id}/status`
- **Request:** `{"status": "OFFLINE"}`
- Valid transitions: PUBLISHED→OFFLINE, OFFLINE→DRAFT

### `POST /api/v1/questions/import`
- **Request:** Multipart CSV or XLSX file upload
- **Validation:** Per-row (required fields, difficulty 1-5)
- **Response:** Import job with per-row results

### `GET /api/v1/questions/export?format=csv|xlsx&category=&status=`
- **Response:** Downloadable file

### `GET /api/v1/questions/{id}/versions`
- **Response:** Complete version history

### `POST /api/v1/questions/{id}/rollback`
- **Roles:** `ROLE_SYSTEM_ADMIN` + step-up auth (same pattern as credentials)

---

## 9. Analytics & Dashboards

**Roles:** `ROLE_ANALYST`, `ROLE_SYSTEM_ADMIN`

### `POST /api/v1/analytics/query`
- **Request:**
```json
{
  "entity_type": "practitioners",
  "filters": {"status": "active", "date_from": "2026-01-01"},
  "aggregation": "count",
  "aggregation_field": "id",
  "group_by": "status"
}
```
- **Security:** `aggregation_field` and `group_by` are validated against a strict allowlist per entity type. Invalid fields return 400.

### `GET /api/v1/dashboards/compliance?from=&to=&org_unit=`
- **Response:**
```json
{
  "rescue_volume": 45,
  "recovery_rate": 72.5,
  "adoption_conversion": 88.0,
  "average_shelter_stay": 36.2,
  "donation_mix": {"Firm A": 40, "Firm B": 35, "Firm C": 25},
  "supply_turnover": 15.3,
  "credential_review_volume": 45,
  "approval_rate": 82.0,
  "rejection_rate": 18.0,
  "appointment_utilization_rate": 67.5,
  "active_practitioners_per_firm": {"Firm A": 12, "Firm B": 8}
}
```
- **`org_unit` filter:** Applied server-side to scope all KPI queries

### `GET /api/v1/dashboards/trend?metric=&from=&to=&interval=daily|weekly|monthly`
### `GET /api/v1/dashboards/distribution?metric=&from=&to=`
### `GET /api/v1/dashboards/correlation?metricX=&metricY=&from=&to=`

### `GET /api/v1/reports/export.csv?query_id=`
### `GET /api/v1/reports/export.pdf?dashboard=compliance&from=&to=`

### `POST /api/v1/analytics/queries/save`
- **Request:** `{"name": "My Query", "query_definition": {...}}`

### `GET /api/v1/analytics/queries`

### `POST /api/v1/analytics/features`
### `GET /api/v1/analytics/features`

---

## 10. Audit & Governance

**Roles:** `ROLE_SYSTEM_ADMIN`

### `GET /api/v1/audit/logs?action_type=&entity_type=&user_id=&from=&to=&page=`
- **Response:** Paginated, filterable audit log entries
- **Expandable:** Each entry includes `old_value_json` and `new_value_json`

### `GET /api/v1/alerts?severity=&acknowledged=&page=`
- **Response:** Paginated alert list

### `POST /api/v1/alerts/{id}/acknowledge`
- **Response:** Alert marked as acknowledged with user and timestamp

### Audit Action Types
`LOGIN`, `LOGOUT`, `CREATE`, `UPDATE`, `DELETE`, `APPROVE`, `REJECT`, `ROLLBACK`, `IMPORT`, `EXPORT`, `LICENSE_REVEAL`, `PASSWORD_RESET`

---

## Seed Data (Default Credentials)

| Username | Password | Role |
|----------|----------|------|
| admin | Admin@123 | ROLE_SYSTEM_ADMIN |
| user | User@123 | ROLE_USER |
| content_admin | Content@123 | ROLE_CONTENT_ADMIN |
| reviewer | Reviewer@123 | ROLE_CREDENTIAL_REVIEWER |
| analyst | Analyst@123 | ROLE_ANALYST |
