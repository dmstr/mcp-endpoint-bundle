<!-- file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC -->

# CLAUDE.md

This is a public open-source package maintained under github.com/dmstr.

Symfony bundle for MCP endpoint profiles: one `McpEndpoint` record per URL slug publishes a slice of the API Platform MCP tools at `/mcp/<slug>` (tool allowlist, read-only flag, required permission, scope, retrieval limits), with the admin-only API Platform resource under `/api/admin/mcp_endpoints`, scope resolvers, a policy port, a seed command and the request-time enforcement on top of `symfony/mcp-bundle` and `mcp/sdk`. See `README.md` for the concepts.

## Language policy

**Everything in this repository is in English**: commit messages, code, comments, documentation, test names and test data, and all communication about the repository (pull request titles and descriptions, code review, issues, and chat with the coding assistant). These packages are public and English-only; do not reference private projects, internal trackers or company internals.

## Requirements and tests

PHP >= 8.5 (see `composer.json`). The PHPUnit suite (configuration `phpunit.dist.xml`) has two test suites:

- `unit` (`tests/Unit`): no kernel, no database. Run it with `vendor/bin/phpunit --testsuite unit`.
- `integration` (`tests/Integration`): boots the test kernel `tests/Kernel/TestKernel.php` (one `symfony/mcp-bundle` server with `file` sessions, the route `/mcp/{slug}`, test-only MCP tools in `tests/Fixtures/Tool`, an in-memory endpoint provider, header-based authentication `X-Test-Roles` / `X-Test-User` / `X-Test-Auth`, a test permission voter, the audit-log bundle; kernel variants by environment: `test`, `test_no_voter`, `test_no_call_guard`, `test_paging`). Tests in the group `database` need a PostgreSQL server from `DATABASE_URL`; the schema is created by running the migrations, the bundle's own ones included. Without a server, run `vendor/bin/phpunit --testsuite integration --exclude-group database`.

CI is the GitHub Action `.github/workflows/tests.yml` (PHP 8.5, PostgreSQL service container), which runs both suites. Test helpers live only under `tests/`.

## Invariants

- The migration classes `Dmstr\McpEndpoint\Migrations\Version20261008223000` and `Dmstr\McpEndpoint\Migrations\Version20261031200000` and the migrations path `__DIR__.'/../migrations'` in `McpEndpointBundle` never change: applications store the class names.
- The bundle checks only permissions with `is_granted()` (subject `null`): `mcpendpoint:endpoint:manage`, `mcpendpoint:endpoint:use` (`Dmstr\McpEndpoint\Security\McpEndpointPermissions`, tagged `za7_core.permission` in `config/services.yaml`) and the `requiredPermission` of an endpoint. No `ROLE_*` name is checked in `src/` or `config/`; the host application maps permissions to roles through its own voter. The tag `za7_core.permission` and the parameters `za7_core.permissions` and `za7_core.permissions.registered` keep their names. A new permission gets a constant, a tag, a test and a README update in the same change.
- Default deny: a tool is listed and callable only when `EndpointToolGate` allows it; listing and calling share that one decision.
- `mcp/sdk` stays on `^0.9` (0.10 splits namespaces). `McpEndpointController` repeats `McpController::handle()` of `symfony/mcp-bundle`; compare it on every upgrade of that package.
