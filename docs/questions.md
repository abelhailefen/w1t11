# Questions & Ambiguity Log

This document records all meaningful areas of ambiguity found in the original prompt, along with the assumptions made and solutions implemented.

---

## Q1 — Credential Review State Machine: Can a rejected credential be resubmitted indefinitely?

**Question:** The prompt says reviewers can "approve, reject with a required comment, or request resubmission." It is unclear whether there is a limit on how many times a credential can be rejected and resubmitted.

**Assumption:** There is no hard limit on resubmission cycles. A credential can be rejected and resubmitted any number of times. However, each cycle is tracked in version history and the anomaly detection system will flag excessive rejections (>5 for the same firm in 24 hours).

**Solution:** The credential state machine allows unlimited transitions between `RESUBMISSION_REQUESTED` → `SUBMITTED` → `UNDER_REVIEW` → (any terminal state). Each transition creates a new version record. The anomaly alerting system monitors rejection frequency.

---

## Q2 — Appointment Rescheduling: Does the 2-reschedule limit reset or is it lifetime?

**Question:** The prompt states "rescheduling is allowed up to 2 times per appointment." It is unclear whether this is a lifetime limit per appointment or whether it resets under any conditions.

**Assumption:** The 2-reschedule limit is a lifetime limit for each individual appointment. Once an appointment has been rescheduled twice, it cannot be rescheduled again — only cancelled (subject to 24-hour rule) or attended.

**Solution:** Each appointment entity tracks a `reschedule_count` integer field initialized to 0. The booking service increments this on each reschedule and rejects requests when `reschedule_count >= 2`. System Admins can override this restriction.

---

## Q3 — Duplicate Detection Threshold: How should textual similarity be computed?

**Question:** The prompt says "duplicate detection that flags questions with high textual similarity for review before publishing" but does not specify the similarity algorithm or exact threshold.

**Assumption:** Use a simple trigram-based similarity approach (PHP's `similar_text` or a Jaccard similarity on n-grams). The default threshold is 80% similarity. This is configurable via an application parameter. The comparison is performed against all currently published questions in the same category.

**Solution:** Implemented a `DuplicateDetectionService` that computes similarity scores against published questions. Questions exceeding the threshold are flagged with a `POTENTIAL_DUPLICATE` warning in the UI, requiring Content Admin acknowledgment before publishing. The threshold is stored in a system settings table and can be adjusted by System Admins.

---

## Q4 — "Held" State for Appointments: What triggers and releases a hold?

**Question:** The prompt mentions bookings can have "held" states but does not define entry/exit conditions for this state.

**Assumption:** A slot enters the `HELD` state when a user initiates the booking process (opens the booking form for a specific slot). The hold expires after 5 minutes if not confirmed. This prevents two users from booking the same slot simultaneously without using pessimistic locking at the UI level.

**Solution:** When a user selects a slot, the backend creates a temporary hold record with a `held_until` timestamp (current time + 5 minutes). The booking confirmation checks that the hold is still valid. Expired holds are ignored by the conflict detection logic. A Symfony console command cleans up expired holds periodically.

---

## Q5 — Compliance Dashboard KPIs: What are "rescue volume, recovery rate, adoption conversion, average shelter stay, donation mix, and supply turnover"?

**Question:** The prompt lists operational KPIs that appear to reference an animal shelter or rescue organization domain (rescue volume, adoption conversion, shelter stay, etc.), but the rest of the prompt describes a legal professional services organization managing lawyers and credentials.

**Assumption:** The compliance dashboard KPIs listed in the prompt are domain-specific metric names that should be mapped to the actual domain entities in the system. We interpret them as follows:
- **Rescue volume** → Credential review volume (total submissions processed)
- **Recovery rate** → Resubmission success rate (rejected → eventually approved)
- **Adoption conversion** → New practitioner onboarding rate (registered → fully credentialed)
- **Average shelter stay** → Average credential review turnaround time
- **Donation mix** → Revenue/billing distribution across practice areas (or organizational units)
- **Supply turnover** → Question bank refresh rate (new questions published vs. taken offline)

**Solution:** Implemented the compliance dashboard with KPIs mapped to the legal professional services domain as described above. Each KPI queries actual data from the MySQL database across the relevant entities (credentials, practitioners, appointments, questions). The dashboard labels use the domain-appropriate names.

---

## Q6 — Field-Level Encryption Key Management: Where does the AES-256 key come from?

**Question:** The prompt requires AES-256 encryption for license numbers but does not specify how the encryption key is provisioned or rotated.

**Assumption:** The encryption key is provided via an environment variable (`APP_ENCRYPTION_KEY`) set in `docker-compose.yml` (with a development default) and overridden in production deployments. Key rotation is out of scope for the initial implementation but the encryption service is designed to support it (keys are versioned).

**Solution:** Created an `EncryptionService` that reads the key from the environment variable. The service uses OpenSSL AES-256-CBC with a random IV stored alongside the ciphertext. The docker-compose.yml sets a development key. The README documents that this must be changed in production.

---

## Q7 — Analytics Workbench: What does "unified queries" mean in this context?

**Question:** The prompt mentions analysts can "run unified queries" but does not define the query interface or capabilities.

**Assumption:** The analytics workbench provides a structured query builder (not raw SQL) that allows analysts to select entities (practitioners, appointments, credentials, questions), apply filters (date range, status, org unit), and generate tabular results. It is not a free-form SQL console.

**Solution:** Implemented a query builder UI with Ant Design form components. The backend provides a `/api/analytics/query` endpoint that accepts structured query parameters (entity type, filters, aggregations, groupBy) and returns paginated results. Results can be tagged, sampled, and exported to CSV.

---

## Q8 — "Human-Verification" Integration Point: What should the stub look like?

**Question:** The prompt says a "reserved human-verification integration point may be configured for future on-prem deployments but must be disabled by default and must not require any external network connectivity."

**Assumption:** This is a service interface that can be swapped in future deployments for a local CAPTCHA or verification service. The default implementation is a no-op that always returns "verified." The interface is used during login and registration flows but is bypassed when disabled.

**Solution:** Created a `HumanVerificationInterface` with a `verify(request): bool` method. The default `NullHumanVerificationService` always returns `true`. A feature flag (`HUMAN_VERIFICATION_ENABLED=false`) in the environment controls whether the verification step is active. The service makes zero network calls in any configuration.

---

## Q9 — Data Retention: Should the 7-year cleanup be automatic?

**Question:** The prompt states 7-year retention for audit logs but does not specify whether cleanup should be automatic or manual.

**Assumption:** In development/staging, automatic cleanup is disabled to preserve all data for testing. The retention policy is implemented as a Symfony console command (`app:audit:cleanup`) that can be scheduled via cron in production. Each audit record has a `retention_expires_at` timestamp set to `created_at + 7 years`.

**Solution:** Audit records are created with a `retention_expires_at` column. The cleanup command is available but not scheduled by default. The README documents how to schedule it in production.

---

## Q10 — CSRF Protection Strategy for SPA Architecture

**Question:** The prompt requires CSRF protection, but the architecture is a decoupled SPA (React) consuming REST APIs. Traditional CSRF tokens (embedded in forms) don't apply cleanly to this pattern.

**Assumption:** Since the frontend is a separate SPA communicating via REST API with JWT authentication, the primary CSRF mitigation is: (1) JWT stored in httpOnly cookies with SameSite=Strict, or (2) JWT in Authorization header (Bearer token). If using cookies, a CSRF double-submit pattern is applied. If using Authorization header, CSRF is inherently mitigated because browsers don't auto-attach custom headers.

**Solution:** Implemented JWT authentication with tokens sent via the `Authorization: Bearer <token>` header. This approach is inherently CSRF-safe because browsers do not automatically attach custom headers to cross-origin requests. For any state-changing operations that use cookies, the backend validates a CSRF token from a custom header.
