<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Scope;

/**
 * Thrown by a {@see ScopeResolverInterface} for references that resolve to
 * nothing or to more than one target.
 */
final class UnknownScopeReferenceException extends \RuntimeException
{
    /**
     * @param list<string> $references the offending references, as passed in
     */
    public function __construct(
        public readonly array $references,
        string $message = '',
    ) {
        parent::__construct('' !== $message ? $message : 'Unknown scope reference(s): '.implode(', ', $references));
    }
}
