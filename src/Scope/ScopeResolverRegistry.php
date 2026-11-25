<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Scope;

/**
 * The tagged {@see ScopeResolverInterface} services by scope key.
 */
final class ScopeResolverRegistry
{
    /** @var array<string, ScopeResolverInterface>|null */
    private ?array $byKey = null;

    /**
     * @param iterable<ScopeResolverInterface> $resolvers
     */
    public function __construct(
        private readonly iterable $resolvers,
    ) {
    }

    public function get(string $scopeKey): ?ScopeResolverInterface
    {
        return $this->all()[$scopeKey] ?? null;
    }

    /**
     * @return array<string, ScopeResolverInterface>
     *
     * @throws \LogicException when two resolvers claim the same scope key
     */
    private function all(): array
    {
        if (null === $this->byKey) {
            $byKey = [];
            foreach ($this->resolvers as $resolver) {
                $key = $resolver->scopeKey();
                if (isset($byKey[$key])) {
                    throw new \LogicException(\sprintf('Two scope resolvers handle the scope key "%s": %s and %s.', $key, $byKey[$key]::class, $resolver::class));
                }
                $byKey[$key] = $resolver;
            }
            $this->byKey = $byKey;
        }

        return $this->byKey;
    }
}
