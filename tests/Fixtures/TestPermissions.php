<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Fixtures;

/**
 * A permission of the host application in the test kernel, registered at
 * the tag `za7_core.permission` there and required by single endpoints, as
 * an application registers the permissions of its own endpoints.
 */
final class TestPermissions
{
    public const string RESTRICTED = 'testapp:endpoint:restricted';
}
