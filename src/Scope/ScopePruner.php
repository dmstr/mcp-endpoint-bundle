<?php

// file generated with AI assistance: Claude Code - 2026-10-13 20:10:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Scope;

use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Removes the id of a deleted target from the scope of every endpoint, so
 * no endpoint keeps a dead id.
 *
 * The scope has no foreign key; the owner of the target calls the pruner
 * when it deletes one, e.g. the bundle that owns document collections when
 * a collection is deleted ({@see removeCollection()}). The endpoints are
 * changed and flushed through the EntityManager, so the change is audited
 * (Gedmo loggable on `scope`) and joins the caller's transaction when one
 * is active. The validator is not involved: an endpoint whose scoped tools
 * lose their last id keeps working with an empty scope (its tools see
 * nothing) and needs a new scope on its next save.
 *
 * The emptied key stays as an empty list. Ids are compared case-insensitively
 * (UUIDs). Reads every endpoint: the table holds a handful of rows.
 */
final class ScopePruner
{
    /** Scope key of document collections. */
    public const string COLLECTIONS = 'collections';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Removes $collectionId from `scope.collections` of every endpoint;
     * returns the number of changed endpoints.
     */
    public function removeCollection(string $collectionId): int
    {
        return $this->removeId(self::COLLECTIONS, $collectionId);
    }

    /**
     * Removes $id from `scope.<$scopeKey>` of every endpoint and flushes
     * when an endpoint changed; returns the number of changed endpoints.
     */
    public function removeId(string $scopeKey, string $id): int
    {
        $id = strtolower($id);
        $changed = 0;

        /** @var list<McpEndpoint> $endpoints */
        $endpoints = $this->entityManager->getRepository(McpEndpoint::class)->findAll();
        foreach ($endpoints as $endpoint) {
            $ids = $endpoint->getScopeIds($scopeKey);
            $kept = array_values(array_filter($ids, static fn (string $stored): bool => strtolower($stored) !== $id));
            if (\count($kept) === \count($ids)) {
                continue;
            }

            $scope = $endpoint->getScope();
            $scope[$scopeKey] = $kept;
            $endpoint->setScope($scope);
            ++$changed;
        }

        if ($changed > 0) {
            $this->entityManager->flush();
        }

        return $changed;
    }
}
