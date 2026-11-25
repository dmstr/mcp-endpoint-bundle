<!-- file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC -->

# dmstr/mcp-endpoint-bundle

MCP endpoint profiles for Symfony applications that publish API Platform `McpTool` operations through `symfony/mcp-bundle`. One `McpEndpoint` record per URL slug decides which slice of the tools a client sees at `/mcp/<slug>`: a tool allowlist, a read-only flag, the permission a caller needs, a scope (for example the document collections a search may use) and retrieval limits.

The bundle provides the entity with its admin CRUD and validation, the ports an application plugs into (scope resolvers, policy, endpoint lookup), a seed command and the request-time enforcement (controller, access checker, handler and session decorators).

## Requirements

- PHP >= 8.5
- Symfony 7.4 (FrameworkBundle, SecurityBundle), Doctrine ORM 3 with DBAL 3 and DoctrineMigrationsBundle
- PostgreSQL: the migrations create `UUID`, `JSON` and `TIMESTAMP WITH TIME ZONE` columns
- API Platform `^4.4.8` with its MCP layer enabled (`symfony/object-mapper`, installed as a dependency); 4.4.8 is the first 4.4 release that supports `mcp/sdk` 0.9
- `mcp/sdk` `^0.9` and `symfony/mcp-bundle` `^0.15`. Both are experimental, so upgrades stay deliberate: the controller repeats `McpController::handle()` of `symfony/mcp-bundle` and has to be compared on every upgrade. `mcp/sdk` 0.10 splits namespaces and is not supported yet.
- A PSR-17 implementation such as `nyholm/psr7`: `symfony/mcp-bundle` discovers the PSR-17 factories of the HTTP transport at runtime
- `gedmo/doctrine-extensions` (the entity is `Loggable`; changes are audited when the application registers a loggable listener, for example through `dmstr/doctrine-audit-log-bundle`) and [`dmstr/openapi-json-schema-bundle`](https://github.com/dmstr/openapi-json-schema-bundle) `^0.5` (schemas of the JSON fields in the OpenAPI document), both installed as dependencies
- A security voter for the bundle's permissions, see [Permissions](#permissions)

## Installation

```bash
composer require dmstr/mcp-endpoint-bundle
```

Register the bundle and the bundles it builds on in `config/bundles.php` (Symfony Flex adds most of them):

```php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    ApiPlatform\Symfony\Bundle\ApiPlatformBundle::class => ['all' => true],
    Symfony\AI\McpBundle\McpBundle::class => ['all' => true],
    Dmstr\OpenApiJsonSchema\OpenApiJsonSchemaBundle::class => ['all' => true],
    Dmstr\McpEndpoint\McpEndpointBundle::class => ['all' => true],
];
```

Then run the migrations, see [Migrations](#migrations).

## Configuration

The bundle prepends its Doctrine mapping (mapping name and alias `DmstrMcpEndpoint`, prefix `Dmstr\McpEndpoint\Entity`), its API Platform mapping path and its migrations path (namespace `Dmstr\McpEndpoint\Migrations`). The application provides the MCP server, the route, the firewall and a voter.

### Server and bundle options

Name the `symfony/mcp-bundle` server the endpoints run on; it needs `transports.http: true`.

```yaml
# config/packages/mcp.yaml
mcp:
    servers:
        endpoints:
            transports: { http: true, stdio: false }
            http:
                # Only names the route of symfony/mcp-bundle, which is not
                # imported; clients use /mcp/{slug}.
                path: /mcp/_server
                allowed_hosts: ['localhost', 'mcp.example.com']
                allowed_origins: []
            # tools/list filters after paging, so one page should hold every tool
            pagination_limit: 200
            session: { store: cache }
            registry: { tools: '*' }

mcp_endpoint:
    server: endpoints   # name under mcp.servers
    # call_guard: true  # switch off in a test kernel only
```

| Option | Default | Meaning |
| --- | --- | --- |
| `server` | `null` | name of the `symfony/mcp-bundle` server; without it the controller and the session store decorator are not registered, and the decorators deny every tool |
| `call_guard` | `true` | whether `tools/call` is checked against the endpoint; a test kernel may switch it off to prove that the handler decorator is what blocks unlisted tools |

`registry: { tools: '*' }` satisfies the requirement of `symfony/mcp-bundle` that a server exposes something. API Platform tools reach the registry through their own loader; tools registered with the SDK's own `#[McpTool]` attribute are never offered by an endpoint.

### Route

Route `/mcp/{slug}` to the bundle's controller. Do not import the route of `symfony/mcp-bundle` (`type: mcp`): its controller knows no endpoint and builds the server with fixed instructions.

```yaml
# config/routes/mcp.yaml
mcp_endpoint:
    path: /mcp/{slug}
    controller: Dmstr\McpEndpoint\Controller\McpEndpointController
    methods: [GET, POST, DELETE, OPTIONS]
    requirements:
        slug: '[a-z0-9-]+'
```

`/mcp` without a slug matches no route and ends as 404.

### Firewall

Put `/mcp/` behind a stateless firewall that authenticates every request, and require a full login there. The controller checks the endpoint's permission, not authentication: without the `access_control` entry an unauthenticated request would get 403 from the controller instead of the 401 of the firewall's entry point.

```yaml
# config/packages/security.yaml
security:
    firewalls:
        mcp:
            pattern: ^/mcp/
            stateless: true
            # your authenticator, e.g. a bearer token
    access_control:
        - { path: ^/mcp/, roles: IS_AUTHENTICATED_FULLY }
```

The admin resource lives under `/api/admin/mcp_endpoints`; make sure `/api/admin` is behind your API firewall.

### DNS rebinding protection

`mcp/sdk` 0.9 checks the `Host` header against `http.allowed_hosts` (hostnames without port) and, separately, a request that carries an `Origin` header against `http.allowed_origins` (scheme, host and optional port). Same-origin requests always pass, requests without `Origin` (server-side clients) are not affected, and a listed host is not enough for an `Origin`: a browser-based client on another origin (for example the MCP Inspector on `http://localhost:6274`) needs its origin in `allowed_origins`, otherwise 403.

`allowed_origins` is a prototyped array node and refuses an environment placeholder ("A dynamic value is not compatible with a PrototypedArrayNode"). An application that reads the lists from the environment defines the middleware factory of the server itself, with both lists as arguments; an application service definition replaces the one of the extension:

```yaml
# config/packages/mcp.yaml
services:
    mcp.server.endpoints.middleware_factory:
        class: Symfony\AI\McpBundle\Http\MiddlewareFactory
        arguments:
            - '%env(csv:MCP_ALLOWED_HOSTS)%'
            - '%env(csv:MCP_ALLOWED_ORIGINS)%'
```

`csv` of an empty variable is `[]`, which means same-origin only.

## The resource

`/api/admin/mcp_endpoints`, operations `GET` (list and item), `POST`, `PATCH` (merge patch) and `DELETE`, all with the permission `mcpendpoint:endpoint:manage`. Filters: `slug` (exact) and `enabled`.

| Field | Type | Default | Meaning |
| --- | --- | --- | --- |
| `slug` | string | - | URL segment, `^[a-z0-9-]+$`, unique |
| `name` | string | - | display name |
| `description` | string | `''` | sent to MCP clients as `instructions` of the `initialize` answer |
| `enabled` | bool | `true` | a disabled endpoint answers 404 like an unknown slug |
| `tools` | list of strings | `[]` | allowlist: names of API Platform `McpTool` operations |
| `readOnly` | bool | `true` | only tools with the annotation `readOnlyHint: true` may be listed |
| `requiredPermission` | string | `mcpendpoint:endpoint:use` | permission a caller needs, `^[a-z][a-z0-9]*(?::[a-z][a-z0-9_]*){1,2}$`; when the application publishes its registered permissions it must be one of them (422 otherwise; a role name never matches) |
| `scope` | object | `{}` | per scope key a list of ids, e.g. `{"collections": ["<uuid>"]}` (`./src/Entity/McpEndpoint/scope.json`) |
| `retrieval` | object | `{}` | `maxK` (1 to 50), `hybrid`, `hybridWeight` (0 to 1); unset keys take the policy defaults (`./src/Entity/McpEndpoint/retrieval.json`) |

Creation and every change of a configuration field are audited when a Gedmo loggable listener is registered.

### Validation

The class constraint `ValidMcpEndpoint` answers 422 with JSON-pointer-like property paths:

- `tools/<i>`: the name is not an MCP tool (looked up through `api_platform.mcp.metadata.operation.mcp_factory`), is listed twice, is not a string, or the tool lacks `readOnlyHint: true` while `readOnly` is true (a missing hint counts as writing, the MCP default);
- `scope/<key>`: an allowed tool declares the scope key `<key>` in its MCP `meta` and `scope.<key>` is empty, or no scope resolver handles `<key>` while it holds references;
- `scope/<key>/<i>`: the resolver of `<key>` does not know the reference and it is new, i.e. not already in the stored scope (see [Deleted targets](#deleted-targets));
- `scope...`, `retrieval/<key>`: the structure does not match the schema files (for example `maxK` above 50);
- `slug`, `name`, `requiredPermission`: pattern, length, uniqueness or registration.

When every scope reference resolves, the validator replaces the references with the resolved ids, so the ids are what is stored. Resolving an id again yields the id, so validation is idempotent.

## Seed command

```bash
php bin/console mcp-endpoint:seed docs --tools=search_documents,get_document --max-k=10
```

Options: `--name` (default: the slug), `--description` (default: a generic text that tells the client to treat tool results as untrusted data), `--tools=<csv>`, `--read-only=true|false` (default true), `--required-permission` (default `mcpendpoint:endpoint:use`), `--scope=<key>:<csv>` (repeatable), `--scope-collections=<csv>` (shortcut for `--scope=collections:<csv>`), `--max-k`, `--hybrid=true|false`, `--hybrid-weight`, `--disabled`.

The options describe the whole endpoint: an option left out takes its default, also for an existing endpoint. The command validates before it writes (same rules as the API, scope references resolved to ids), creates a missing endpoint, updates a differing one in place and leaves an equal one alone (no flush). Running it again with the same arguments changes nothing, so it is safe in setup scripts.

## Scope

A tool that only makes sense with a scope declares the scope key in its MCP `meta` (key `de.dmstr/scope`, constant `Dmstr\McpEndpoint\Tool\ToolInspector::SCOPE_META_KEY`):

```php
new McpTool(
    name: 'search_documents',
    annotations: ['readOnlyHint' => true],
    meta: ['de.dmstr/scope' => 'collections'],
    // ...
)
```

An endpoint that allows such a tool needs a non-empty `scope.collections`. The tool reads the ids of the current endpoint from `Dmstr\McpEndpoint\Context\McpEndpointContext` (`get()?->getScopeIds('collections')`) and is expected to see nothing for an empty list.

### Scope resolvers

References of a scope key are resolved by a service implementing `Dmstr\McpEndpoint\Scope\ScopeResolverInterface`:

```php
interface ScopeResolverInterface
{
    public function scopeKey(): string;           // e.g. 'collections'
    /** @param list<string> $refs @return list<string> ids */
    public function resolve(array $refs): array;  // throws UnknownScopeReferenceException
}
```

With autoconfiguration the service is tagged `dmstr_mcp_endpoint.scope_resolver` automatically; a service defined in another bundle (autoconfigure off) adds the tag itself. One resolver per scope key; two resolvers for the same key are a configuration error. A scope key without resolver accepts no references.

### Deleted targets

There is no foreign key between an endpoint and the ids in its scope, because the scope is a JSON object of id lists for any scope key of any bundle. Two mechanisms keep a deleted target from blocking an endpoint:

- **Pruning on delete.** `Dmstr\McpEndpoint\Scope\ScopePruner` removes an id from the scope of every endpoint (`removeId(<key>, <id>)`, for the key `collections` also `removeCollection(<id>)`). The owner of the target calls it when it deletes one, ideally in the same transaction. The pruner changes the endpoints through the EntityManager, so the change is audited and rolls back with the caller's transaction. It does not validate: an endpoint whose scope loses its last id keeps working with an empty scope (its tools see nothing) and needs a new scope on its next save. The emptied key stays as an empty list, and ids are compared case-insensitively.
- **Tolerant validation.** An id that no longer resolves but is already in the stored scope (the original data of the unit of work) is dropped silently on the next save instead of answering 422, so a target deleted past the pruner (for example by SQL) does not block an unrelated change to the endpoint. A new unknown reference is still a 422 at `scope/<key>/<i>`. When dropping leaves a scope empty that an allowed tool requires, the 422 at `scope/<key>` names the deleted ids (`its last reference(s) "<id>" no longer exist (deleted)`), so the caller sees why the scope became empty.

Tools are still expected to skip a dead id silently, because an endpoint may hold one until its next save.

## Policy

`Dmstr\McpEndpoint\Policy\McpEndpointPolicyInterface` holds the application-wide rules and defaults:

| Method | Bundle default (`DefaultMcpEndpointPolicy`) |
| --- | --- |
| `allowWrite(): bool` | `false`: tools without `readOnlyHint: true` stay hidden and uncallable, even on endpoints with `readOnly: false` |
| `defaultMaxK(): int` | `10` (hard cap `MAX_K_LIMIT` 50) |
| `defaultHybrid(): bool` | `false` |
| `defaultHybridWeight(): float` | `0.5` |

`allowWrite()` is the kill switch for write tools across all endpoints. An application replaces the default by aliasing the interface, for example to an implementation that reads runtime settings:

```yaml
# config/services.yaml
Dmstr\McpEndpoint\Policy\McpEndpointPolicyInterface: '@Acme\Mcp\SettingsEndpointPolicy'
```

## Endpoint lookup

`Dmstr\McpEndpoint\Endpoint\McpEndpointProviderInterface::findEnabledBySlug(string $slug): ?McpEndpoint` returns the enabled endpoint of a slug and null for an unknown and a disabled one alike (both answer 404). The bundle aliases it to `DoctrineMcpEndpointProvider`; a test kernel may alias it to an in-memory implementation to test the protocol without a database.

## Permissions

The bundle checks two permissions with `is_granted()`:

| Permission | Where |
| --- | --- |
| `mcpendpoint:endpoint:manage` | every operation of the admin resource |
| `mcpendpoint:endpoint:use` | default `requiredPermission` of an endpoint, checked by the controller |

Endpoints may require other permissions of the application (`requiredPermission`). No role name appears in the bundle: the host application decides through its own security voter which roles hold a permission. The bundle takes part in a small, framework-level permission contract that such a voter can read:

- The bundle announces its permissions at the container tag `za7_core.permission` (attribute `permission`, plain strings; `Dmstr\McpEndpoint\Security\McpEndpointPermissions` in `./config/services.yaml`). The application announces the permissions of its own endpoints the same way.
- The application collects the tags into the container parameter `za7_core.permissions.registered` (a list of strings). When this parameter exists, `RegisteredPermissionValidator` refuses a `requiredPermission` that is not in it; without it only the format is checked.
- The application maps permissions to roles, for example in the parameter `za7_core.permissions` (permission => list of roles), and its voter grants a supported permission when one of the user's roles is mapped to it.

The three names are the permission tag and parameters the host application's voter reads; they are kept stable. `./tests/Kernel/TestKernel.php` and `./tests/Kernel/TestPermissionVoter.php` show a complete minimal implementation.

Without any voter for these permissions every voter abstains, Symfony's access decision manager denies, and the result is fail closed: the controller answers 403 to every authenticated request on `/mcp/<slug>` before the MCP transport runs (no session, no tool list, no tool call), the admin resource answers 403 to every operation, and unauthenticated requests still get 401 from the firewall.

## Request flow

A request to `/mcp/<slug>` passes these steps:

1. **Controller** (`Controller\McpEndpointController`): looks the slug up through `McpEndpointProviderInterface` (unknown or disabled: 404), checks `requiredPermission` with `isGranted()` (403 before the MCP transport runs; the permission voter decides), puts the endpoint into the request-scoped `Context\McpEndpointContext` (only the controller fills it) and builds the configured server per request from a clone of its builder, with the endpoint's `description` as `instructions`. The SDK builder memoizes its configuration on the first `build()`, and the controller of `symfony/mcp-bundle` offers no hook before it, so the controller repeats its few lines of transport handling.
2. **Tool gate** (`Security\EndpointToolGate`), one decision for listing and calling. Default deny: no endpoint in the context, a name off the allowlist, a name that is no API Platform `McpTool`, and a tool without `readOnlyHint: true` while the endpoint is `readOnly` or `McpEndpointPolicyInterface::allowWrite()` is false are all denied.
3. **Listing** (`Security\EndpointAccessChecker`, decorates `api_platform.mcp.security.expression_access_checker`): `tools/list` and `resources/list` show what the gate allows and the operation's `security` grants.
4. **Calling** (`Server\EndpointHandler`, decorates `api_platform.mcp.handler`): `supports()` answers like API Platform's handler (answering false would hand the call to the SDK's unfiltered `CallToolHandler`); `handle()` returns JSON-RPC "method not found" for every tool the gate denies and for `resources/read`, before any provider runs.
5. **Everything else** (`Server\UnservedRequestHandler`, tag `mcp.request_handler` with priority -1000, after the handler above and before the SDK's own handlers, which read the registry unfiltered): `tools/call` of names API Platform does not know, `resources/read`, `prompts/get`, `completion/complete` and resource subscriptions answer "method not found"; `prompts/list` and `resources/templates/list` answer an empty list. Endpoints publish tools only.

The operation's own `security` still applies when a tool runs (API Platform's `AccessCheckerProvider`).

## Sessions

`Session\EndpointSessionStore` decorates `mcp.server.<server>.session.store`. The SDK's store interface takes UUIDs, so each session id is mapped to a name-based UUID (v5) of the slug and the id under a fixed namespace; the client keeps seeing the id the SDK generated. A session opened on `/mcp/a` is unknown on `/mcp/b` (404), an invented session id is 404 as well, and `gc()` delegates. A missing session id after `initialize` answers 400, which is SDK behaviour. Since `mcp/sdk` 0.9 the session saves only the keys it changed, and `exists()` may throw `Mcp\Exception\SessionStoreException` for an unreachable store, which the SDK answers with 503; both pass through the decorator unchanged.

Every handshake revision up to 2025-11-25 requires a session, so the server needs a store that outlives the request: `cache` (a shared PSR-16 pool such as Redis) in production, `file` in a test kernel, where the array cache pool is reset between two requests of the test client.

## Migrations

The bundle registers its migrations path `./migrations` under the namespace `Dmstr\McpEndpoint\Migrations`. Run them with the application's migrations:

```bash
php bin/console doctrine:migrations:migrate
```

| Migration | Change |
| --- | --- |
| `Dmstr\McpEndpoint\Migrations\Version20261008223000` | creates the table `mcp_endpoint` with the unique index `uniq_mcp_endpoint_slug` |
| `Dmstr\McpEndpoint\Migrations\Version20261031200000` | renames `required_role` to `required_permission`, default `mcpendpoint:endpoint:use`; the stored value `ROLE_USER` becomes `mcpendpoint:endpoint:use`, other role values stay and are for the application's own migration to map |

Existing databases store these class names in their migrations table, so they never change.

## Operational notes

- Prompt injection: tool results (documents, search hits) reach the MCP client unfiltered. The client has to treat them as data, not as instructions; say so in the endpoint `description`, as the seed default does.
- `tools/list` paging: API Platform's `SecureRegistry` filters after paging, so with a small `pagination_limit` pages may be short or empty although further tools follow (the cursor still reaches every tool once). Clients cannot choose the page size; set `pagination_limit` high enough for one page to hold every tool.
- Load: a search tool usually embeds the query on every call; an endpoint shared widely multiplies that load. There is no rate limit per endpoint.

## Testing

The PHPUnit configuration `./phpunit.dist.xml` has two suites:

- `unit` (`./tests/Unit`): no kernel, no database.
- `integration` (`./tests/Integration`): boots `./tests/Kernel/TestKernel.php`, a minimal host application with API Platform, `symfony/mcp-bundle` (one server, `file` sessions, literal DNS rebinding lists), the route `/mcp/{slug}`, header-based test authentication (`X-Test-Roles`, `X-Test-User`, `X-Test-Auth: anonymous`), a test permission voter, the audit log and test-only MCP tools (`./tests/Fixtures/Tool`). The MCP protocol tests take their endpoints from an in-memory provider and need no database. Tests in the group `database` need PostgreSQL from `DATABASE_URL`; their schema comes from the migrations.

```bash
composer install
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration --exclude-group database
# with a PostgreSQL server:
DATABASE_URL='postgresql://app:app@127.0.0.1:5432/mcp_endpoint_test?serverVersion=18&charset=utf8' vendor/bin/phpunit
```

The GitHub Action `./.github/workflows/tests.yml` runs both suites on PHP 8.5 with a PostgreSQL 18 service container.

## License

MIT, see `./LICENSE`.
