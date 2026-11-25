<?php

// file generated with AI assistance: Claude Code - 2026-10-31 20:00:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Security;

/**
 * The permissions this bundle itself owns. Both are registered at the
 * container tag "za7_core.permission" (config/services.yaml) and decided by
 * the permission voter of the host application; which roles hold them is the
 * application's map (`za7_core.permissions`). Permissions of single endpoints are stored in
 * `McpEndpoint::$requiredPermission` and registered by the application.
 */
final class McpEndpointPermissions
{
    /** The admin API `/api/admin/mcp_endpoints`. */
    public const string ENDPOINT_MANAGE = 'mcpendpoint:endpoint:manage';

    /** Default stored permission of an endpoint: calling it. */
    public const string ENDPOINT_USE = 'mcpendpoint:endpoint:use';
}
