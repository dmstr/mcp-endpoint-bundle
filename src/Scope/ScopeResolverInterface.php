<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Scope;

/**
 * Resolves the references of one scope key of an endpoint (e.g.
 * `collections`: names or UUIDs of document collections) to the ids that
 * are stored.
 *
 * Implementations are tagged `dmstr_mcp_endpoint.scope_resolver`
 * ({@see \Dmstr\McpEndpoint\McpEndpointBundle::SCOPE_RESOLVER_TAG}); with
 * autoconfiguration this happens by implementing the interface. One
 * resolver per scope key. A scope key without resolver accepts no
 * references (422).
 */
interface ScopeResolverInterface
{
    /** The scope key this resolver handles, e.g. `collections`. */
    public function scopeKey(): string;

    /**
     * Resolves references to ids, in the order of $refs. Duplicates may be
     * returned; the caller removes them.
     *
     * @param list<string> $refs
     *
     * @return list<string> the ids to store, one per reference
     *
     * @throws UnknownScopeReferenceException naming every reference that
     *                                        resolves to nothing (or to
     *                                        more than one target)
     */
    public function resolve(array $refs): array;
}
