<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Endpoint;

use Dmstr\McpEndpoint\Entity\McpEndpoint;

/**
 * Lookup of the endpoint a request addresses (`/mcp/{slug}`).
 *
 * The bundle aliases this interface to {@see DoctrineMcpEndpointProvider};
 * a test kernel may alias it to an in-memory implementation.
 */
interface McpEndpointProviderInterface
{
    /**
     * The enabled endpoint with this slug; null for an unknown slug and for
     * a disabled endpoint alike, so callers answer both with 404.
     */
    public function findEnabledBySlug(string $slug): ?McpEndpoint;
}
