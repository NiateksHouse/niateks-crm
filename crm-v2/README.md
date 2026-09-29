# KOZA CRM v2.0.0-alpha.20

Terminal-free initial-account preparation via a noninteractive, one-shot CLI invitation issuer suitable for a private Cron command after installation. The issuer reads only storage/app/private/bootstrap-users.json, writes 0600 invitation codes to a private file, never emits codes in stdout, and refuses existing-user systems. Repeating the initial batch is a no-op.

Users activate through /activate by entering a 24-hour single-use code and their own password. Only token hashes are stored in MySQL. Consumption/user creation/event are transactional with row locking. Server-side grants cannot be chosen in the form. Codes/passwords are excluded from flashed input; routes use CSRF and rate limiting. No email is sent automatically.

No accounts or production codes were created by development. Hosting migrations, private setup file, PHP CLI verification, HTTPS checks and actual activation still remain. User guide v0.3.0 unchanged pending separate approval.
