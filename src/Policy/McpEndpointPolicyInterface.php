<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Policy;

/**
 * Application-wide rules and defaults of MCP endpoints.
 *
 * The bundle ships {@see DefaultMcpEndpointPolicy} (fail closed: no write
 * tools); an application aliases this interface to its own implementation,
 * e.g. one that reads runtime application settings.
 */
interface McpEndpointPolicyInterface
{
    /** Hard cap of `retrieval.maxK` of an endpoint. */
    public const int MAX_K_LIMIT = 50;

    /**
     * Whether tools without `readOnlyHint: true` may be offered at all. When
     * false, they stay hidden and uncallable even on endpoints with
     * `readOnly: false`.
     */
    public function allowWrite(): bool;

    /** Result count of a search when the endpoint sets no `retrieval.maxK` (1 to {@see MAX_K_LIMIT}). */
    public function defaultMaxK(): int;

    /** Hybrid retrieval when the endpoint sets no `retrieval.hybrid`. */
    public function defaultHybrid(): bool;

    /** Weight of the keyword part when the endpoint sets no `retrieval.hybridWeight` (0 to 1). */
    public function defaultHybridWeight(): float;
}
