<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Policy;

/**
 * The bundle default of {@see McpEndpointPolicyInterface}, used while the
 * application does not alias the interface: no write tools, 10 results,
 * vector-only retrieval, hybrid weight 0.5.
 */
final class DefaultMcpEndpointPolicy implements McpEndpointPolicyInterface
{
    public const bool ALLOW_WRITE = false;
    public const int DEFAULT_MAX_K = 10;
    public const bool DEFAULT_HYBRID = false;
    public const float DEFAULT_HYBRID_WEIGHT = 0.5;

    public function allowWrite(): bool
    {
        return self::ALLOW_WRITE;
    }

    public function defaultMaxK(): int
    {
        return self::DEFAULT_MAX_K;
    }

    public function defaultHybrid(): bool
    {
        return self::DEFAULT_HYBRID;
    }

    public function defaultHybridWeight(): float
    {
        return self::DEFAULT_HYBRID_WEIGHT;
    }
}
