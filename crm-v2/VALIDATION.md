# Alpha.17 validation

PHP syntax checked locally. Behavioral tests require the isolated MySQL CI database ending in _ci; never run migrate:fresh against hosting databases. CI includes role/category filters, shared revision access, historical snapshots, concurrent update rejection, category administration and input validation, plus authentication and archive tests.

Results: refer to the GitHub Actions run for the current commit; do not reuse alpha.16 results. No hosting deployment or browser end-to-end verification is claimed. PHPUnit does not prove browser CSRF behavior. Source ZIP is not a deployment package.
