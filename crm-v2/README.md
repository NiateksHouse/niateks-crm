# KOZA CRM v2.0.0-alpha.19

Branded Laravel login uses the previously approved workshop image and Niateks House logo. The shared company header also uses the logo. Existing authentication, CSRF and session behavior is retained.

`koza:create-user` is an interactive administrative command with hidden password confirmation, input validation, duplicate protection and explicit admin/finance options. It never resets an existing account, never accepts a password argument, and rejects noninteractive invocation. No real user accounts or credentials are included.

CI passed 22 MySQL tests and real HTTP checks (see external test record for authoritative counts, commit and run). Formatting, dependency checks, Blade compilation and runtime packaging passed. Local view rendering passed. Visual desktop/mobile inspection remains unverified because the browser security policy rejected opening the local preview.

Hosting still needs a supported initial account setup path: existing cPanel has Cron but no interactive terminal. Cron cannot use this command. See INSTALL.md. No hosting deployment or merge occurred. Guide v0.3.0 is unchanged; a future login guide update requires separate approval.
