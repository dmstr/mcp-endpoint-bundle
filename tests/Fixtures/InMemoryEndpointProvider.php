<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Fixtures;

use Dmstr\McpEndpoint\Endpoint\McpEndpointProviderInterface;
use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;

/**
 * Endpoints of the MCP protocol tests without a database.
 *
 * Aliased to {@see McpEndpointProviderInterface} in the test kernel. The
 * slugs listed in {@see self::ENDPOINTS} answer from memory, including
 * those listed as null (absent): the protocol tests use only these, so they
 * never reach the database. Every other slug goes to the Doctrine provider,
 * so the database tests keep the real lookup.
 *
 * The entities are not validated: `read-only-write` lists a write tool on a
 * read-only endpoint, which the API would refuse, to prove that the tool
 * gate holds even then.
 */
final readonly class InMemoryEndpointProvider implements McpEndpointProviderInterface
{
    /** Scope of the `docs` endpoint. */
    public const array DOCS_SCOPE = [TestItemsScopeResolver::KEY => [TestItemsScopeResolver::ITEMS['alpha']]];

    /**
     * @var array<string, array{description?: string, tools: list<string>, enabled?: bool, readOnly?: bool, requiredPermission?: string, scope?: array<string, list<string>>, retrieval?: array<string, mixed>}|null>
     */
    public const array ENDPOINTS = [
        'docs' => [
            'description' => 'Docs endpoint instructions.',
            'tools' => [TestTools::SCOPED_TOOL, TestTools::STRUCTURED_TOOL],
            'scope' => self::DOCS_SCOPE,
            'retrieval' => ['maxK' => 3],
        ],
        'spy' => ['description' => 'Spy endpoint instructions.', 'tools' => [TestTools::READ_TOOL]],
        'disabled' => ['tools' => [TestTools::READ_TOOL], 'enabled' => false],
        'restricted' => ['tools' => [TestTools::READ_TOOL], 'requiredPermission' => TestPermissions::RESTRICTED],
        'read-only-write' => ['tools' => [TestTools::READ_TOOL, TestTools::WRITE_TOOL]],
        'writer' => ['tools' => [TestTools::READ_TOOL, TestTools::WRITE_TOOL], 'readOnly' => false],
        'paging' => ['tools' => [TestTools::READ_TOOL, TestTools::SCOPED_TOOL, TestTools::STRUCTURED_TOOL], 'scope' => self::DOCS_SCOPE],
        'unknown' => null,
    ];

    public function __construct(
        private McpEndpointProviderInterface $database,
    ) {
    }

    public function findEnabledBySlug(string $slug): ?McpEndpoint
    {
        if (!\array_key_exists($slug, self::ENDPOINTS)) {
            return $this->database->findEnabledBySlug($slug);
        }

        $definition = self::ENDPOINTS[$slug];
        if (null === $definition || false === ($definition['enabled'] ?? true)) {
            return null;
        }

        // A fresh entity per lookup: no state carries over between requests.
        return (new McpEndpoint())
            ->setSlug($slug)
            ->setName($slug)
            ->setDescription($definition['description'] ?? '')
            ->setTools($definition['tools'])
            ->setReadOnly($definition['readOnly'] ?? true)
            ->setRequiredPermission($definition['requiredPermission'] ?? McpEndpoint::DEFAULT_PERMISSION)
            ->setScope($definition['scope'] ?? [])
            ->setRetrieval($definition['retrieval'] ?? []);
    }
}
