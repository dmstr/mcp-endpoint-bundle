<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:41:25 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Security;

use ApiPlatform\Mcp\Security\ElementAccessCheckerInterface;
use Dmstr\McpEndpoint\Server\EndpointHandler;

/**
 * Narrows what tools/list (and resources/list) show to what the endpoint of
 * the current request offers ({@see EndpointToolGate}), then leaves the
 * operation's `security` to the inner checker.
 *
 * Decorates `api_platform.mcp.security.expression_access_checker`: API
 * Platform's SecureRegistry asks this checker for every listed element
 * (McpRegistryPass passes the service by id, so the decorator takes its
 * place). Resources are never on a tool allowlist and therefore hidden.
 *
 * Listing only: the call of a hidden tool is stopped by
 * {@see EndpointHandler} with the same gate.
 */
final readonly class EndpointAccessChecker implements ElementAccessCheckerInterface
{
    public function __construct(
        private ElementAccessCheckerInterface $inner,
        private EndpointToolGate $gate,
    ) {
    }

    public function isGranted(string $operationName): bool
    {
        return $this->gate->allows($operationName) && $this->inner->isGranted($operationName);
    }
}
