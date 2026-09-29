# KOZA CRM v2.0.0-alpha.17

Laravel/MySQL source foundation; not a deployable release. No vendor directory or dependency lock yet. Existing CRM root is untouched; this app lives in crm-v2 on the development branch.

Shared company cards support customer, supplier or both roles, multiple supply areas, role/category filtering, and admin-created supply areas. All active employees can add a new revision; previous snapshots remain behind the history page with actor and time. Concurrent stale edits are rejected. Only admins archive cards. Purchasing is planned separately, not implemented. Finance permissions are not implemented by this slice.

Validation: CI runs PHP 8.4 and isolated MySQL 8.0 tests, dependency audit and Blade compilation. See the current GitHub Actions run for this commit. Browser/session/CSRF integration review, dependency lock, code formatting, user provisioning and deployment checks remain before hosting release.

User guide intentionally unchanged pending separate approval. Existing demo screens are not yet integrated with this backend.
