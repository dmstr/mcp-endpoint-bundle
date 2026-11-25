<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Endpoint;

use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Doctrine\ORM\EntityManagerInterface;

/**
 * {@see McpEndpointProviderInterface} on the `mcp_endpoint` table.
 */
final readonly class DoctrineMcpEndpointProvider implements McpEndpointProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findEnabledBySlug(string $slug): ?McpEndpoint
    {
        if (1 !== preg_match(McpEndpoint::SLUG_PATTERN, $slug)) {
            return null;
        }

        return $this->entityManager->getRepository(McpEndpoint::class)
            ->findOneBy(['slug' => $slug, 'enabled' => true]);
    }
}
