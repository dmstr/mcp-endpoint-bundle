<!-- file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC -->

# Changelog

All notable changes to this bundle. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/) and come from the Git tags.

## [0.1.0] - 2026-11-25

Initial public release.

### Added

- The entity `McpEndpoint` (slug, tool allowlist, read-only flag, required permission, scope, retrieval limits) with its admin-only API Platform resource under `/api/admin/mcp_endpoints` and the class constraint `ValidMcpEndpoint` against the registered API Platform MCP tools.
- Request-time enforcement per `/mcp/{slug}`: `McpEndpointController`, the tool gate behind decorators of API Platform's MCP access checker and handler, the default-deny `UnservedRequestHandler` and the slug-bound `EndpointSessionStore`.
- Ports: scope resolvers (`ScopeResolverInterface`, autoconfigured), the policy `McpEndpointPolicyInterface` with the fail-closed `DefaultMcpEndpointPolicy`, the endpoint lookup `McpEndpointProviderInterface`, and `ScopePruner` for deleted scope targets.
- The seed command `mcp-endpoint:seed`.
- The permissions `mcpendpoint:endpoint:manage` and `mcpendpoint:endpoint:use`, announced at the container tag `za7_core.permission`; the host application provides the voter.
- The migrations `Dmstr\McpEndpoint\Migrations\Version20261008223000` and `Dmstr\McpEndpoint\Migrations\Version20261031200000` (table `mcp_endpoint`, PostgreSQL).
- PHPUnit suites `unit` and `integration` (test kernel with an MCP server, PostgreSQL for the group `database`) and a GitHub Action.

### Requirements

- Licence: MIT.
- PHP >= 8.5, Symfony 7.4, `mcp/sdk` ^0.9, `symfony/mcp-bundle` ^0.15, `api-platform/core` >=4.4.8 (^4.4.8), Doctrine ORM 3, Doctrine DBAL 3, PostgreSQL.

[0.1.0]: https://github.com/dmstr/mcp-endpoint-bundle/releases/tag/0.1.0
