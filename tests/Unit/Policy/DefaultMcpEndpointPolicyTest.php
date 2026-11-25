<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Unit\Policy;

use Dmstr\McpEndpoint\Policy\DefaultMcpEndpointPolicy;
use Dmstr\McpEndpoint\Policy\McpEndpointPolicyInterface;
use PHPUnit\Framework\TestCase;

/**
 * The bundle default of the endpoint policy is fail closed: no write
 * tools, 10 results, vector-only retrieval, hybrid weight 0.5.
 */
final class DefaultMcpEndpointPolicyTest extends TestCase
{
    public function testBundleDefaultIsFailClosed(): void
    {
        $policy = new DefaultMcpEndpointPolicy();

        self::assertFalse($policy->allowWrite());
        self::assertSame(10, $policy->defaultMaxK());
        self::assertFalse($policy->defaultHybrid());
        self::assertSame(0.5, $policy->defaultHybridWeight());
    }

    public function testDefaultMaxKIsWithinTheHardCap(): void
    {
        self::assertSame(50, McpEndpointPolicyInterface::MAX_K_LIMIT);
        self::assertLessThanOrEqual(McpEndpointPolicyInterface::MAX_K_LIMIT, (new DefaultMcpEndpointPolicy())->defaultMaxK());
    }
}
