<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:41:25 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Context;

use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The endpoint of the current MCP request.
 *
 * Request-scoped: only {@see \Dmstr\McpEndpoint\Controller\McpEndpointController}
 * fills it, before the MCP server runs; the tool gate, the access checker,
 * the handler decorators and the session store read it. Empty means "not an
 * endpoint request", and every reader denies then. Reset after every request
 * (tag `kernel.reset`).
 */
final class McpEndpointContext implements ResetInterface
{
    private ?McpEndpoint $endpoint = null;

    public function set(McpEndpoint $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function get(): ?McpEndpoint
    {
        return $this->endpoint;
    }

    public function reset(): void
    {
        $this->endpoint = null;
    }
}
