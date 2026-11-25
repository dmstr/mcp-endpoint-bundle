<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Fixtures;

use Dmstr\McpEndpoint\Policy\DefaultMcpEndpointPolicy;
use Dmstr\McpEndpoint\Policy\McpEndpointPolicyInterface;

/**
 * Endpoint policy of the test kernel, aliased to
 * {@see McpEndpointPolicyInterface} as an application aliases its own: the
 * bundle defaults, with the write switch {@see self::$allowWrite} under the
 * control of the tests. Static, because the test client reboots the kernel
 * between requests; tests reset it in setUp() and tearDown().
 */
final class SwitchableEndpointPolicy implements McpEndpointPolicyInterface
{
    public static bool $allowWrite = DefaultMcpEndpointPolicy::ALLOW_WRITE;

    public function allowWrite(): bool
    {
        return self::$allowWrite;
    }

    public function defaultMaxK(): int
    {
        return DefaultMcpEndpointPolicy::DEFAULT_MAX_K;
    }

    public function defaultHybrid(): bool
    {
        return DefaultMcpEndpointPolicy::DEFAULT_HYBRID;
    }

    public function defaultHybridWeight(): float
    {
        return DefaultMcpEndpointPolicy::DEFAULT_HYBRID_WEIGHT;
    }
}
