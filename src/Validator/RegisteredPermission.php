<?php

// file generated with AI assistance: Claude Code - 2026-10-31 20:00:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Property constraint of `McpEndpoint::$requiredPermission`: the value must be
 * a permission registered at the container tag "za7_core.permission". Checked
 * only when the container parameter `za7_core.permissions.registered` exists,
 * so the bundle still works without a host application that publishes it. See
 * {@see RegisteredPermissionValidator}.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class RegisteredPermission extends Constraint
{
    public const string NOT_REGISTERED_ERROR = 'f7b9d1e3-6a8c-4e0f-b2d4-5c7e9a1f3b08';

    protected const array ERROR_NAMES = [
        self::NOT_REGISTERED_ERROR => 'NOT_REGISTERED_ERROR',
    ];

    public string $message = 'The permission "{{ permission }}" is not registered; it must be one of the permissions registered by a bundle or the application.';
}
