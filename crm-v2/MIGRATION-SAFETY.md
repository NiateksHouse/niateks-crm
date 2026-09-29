# Alpha.25 migration rollback review

## Root cause and change
The original 46-test run asked Laravel to roll back the most recent migration. Migration 000005 intentionally threw in down(), so PHPUnit exited 2. This was unrelated to MySQL connectivity or the Node 20 warning. Alpha24 later passed by targeting the project migration directly. Alpha25 restores the named upgrade test to Laravel rollback/migrate, creates the legacy records before rollback, retains its original assertions and adds preservation assertions after rollback.

Before: matching down() always threw. After: persistent environments still throw the same forward-only exception. Testing can reverse this migration only through the guard below. No up() behavior changes; existing matching data is never rewritten by this patch.

## Disposable database guard
All conditions are mandatory: APP_ENV=testing, CLI process, no cached Laravel config, default driver mysql, DB_DATABASE ends in _ci, DB_DISPOSABLE_TEST_DATABASE exactly equals DB_DATABASE. The resolved connection and SELECT DATABASE() must both match that name. PHPUnit's base TestCase invokes the same guard BEFORE migrate:fresh.

Use a dedicated MySQL database and account granted access only to that database. Naming/opt-in cannot prove the business value of data; never designate a persistent database as disposable. The CI MySQL container and its koza_ci account are created afresh for each job; the explicit designation is set in that job. phpunit.xml forces testing/mysql but does not silently grant the destructive opt-in.

Local tests require an independently provisioned disposable MySQL _ci database, uncached config and DB_DISPOSABLE_TEST_DATABASE matching its name. No local MySQL is installed in the current workspace; tests run in GitHub's MySQL 8.0 service. SQLite is neither configured nor supported by these tests. Development/local/staging/production/ci_http environments are refused by down(), even with the opt-in set.

## Exact schema reversal
The migration creates:
- contacts: company_id -> companies, created_by -> users; primary id, foreign-key indexes.
- matching_settings: primary id, JSON weights/thresholds and version; no foreign keys.
- matching_decisions: actor_id/revoked_by -> users; alias_key index; composite entity_type/source_id/target_id index. Source/target IDs are polymorphic, not foreign keys.
- self_learning_company_dictionary: company_id -> companies, decision_id -> matching_decisions; normalized_alias index.
- matching_keys: composite entity_type/kind/value and entity_type/entity_id indexes; polymorphic IDs, no foreign keys.
- matching_events: actor_id -> users; decision_id is nullable reference without FK.
All six tables have their defined primary keys; there are no new alias unique constraints.

Rollback first checks that company identity_key/email/phone values can satisfy the OLD unique constraints (NULLs excluded, MySQL collation/grouping used). Any duplicate refuses BEFORE any DDL; it does not deduplicate, delete or reassign company rows.

Drop order: matching_events, matching_keys, self_learning_company_dictionary, matching_decisions, matching_settings, contacts. The dictionary child is dropped before its decision parent; companies/users remain. Dropping each table removes its own indexes and foreign keys without disabling referential checks globally.

Then remove companies_identity_key_index, companies_email_index and companies_phone_index, restore the three corresponding _unique constraints, and drop ONLY website and tax_number columns introduced by 000005. Existing company fields/rows, users and unrelated tables remain unchanged. Test-only values in the two new columns and the six tables are deliberately disposable.

## Lifecycle and operational limits
- migrate:rollback --step=1 and migrate rerun: covered with preservation, restored-index and backfill assertions.
- migrate:refresh: traverses down(); succeeds in the explicitly designated disposable DB if old uniqueness can be restored. Existing synthetic duplicates require guarded migrate:fresh instead.
- migrate:fresh: Laravel drops tables directly, bypassing migration down(). The forward-only exception has NEVER protected this command. Our PHPUnit entry point guards it before execution. Do not use it in hosting, development databases containing valued data, staging or production. Direct privileged Artisan/SQL access can destroy data; environment checks are not database access control.
- Deployment rollback: restore reviewed prior application files; do not automatically reverse the persistent database migration. Alpha25 is a source/CI fix, not a hosting deployment.
- Recovery: use verified, access-controlled server-side database and application backups; a failed MySQL DDL reversal is not transactional. Do not infer a backup/restore drill from passing tests. No hosting backup is downloaded to local SSD.
- No test skip, removed test, destructive production exception bypass or SQLite substitution.

## Commands
The project has PHPUnit directly, without Collision's `artisan test` wrapper. The equivalent focused command is `php vendor/bin/phpunit --filter=test_upgrade_keeps_existing_users_and_companies`; the full suite is `composer test`. Both are separate mandatory CI steps.

Checkout v7.0.1: 3d3c42e5aac5ba805825da76410c181273ba90b1, Node 24.
Upload-artifact v7.0.1: 043fb46d1a93c77aae656e7c1c64a875d1fc6a0a, Node 24.
Setup-php retained at f3e473d116dcccaddc5834248c87452386958240; its action.yml already specifies Node 24.
