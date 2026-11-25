<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:41:25 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Security;

use Dmstr\McpEndpoint\Context\McpEndpointContext;
use Dmstr\McpEndpoint\Policy\McpEndpointPolicyInterface;
use Dmstr\McpEndpoint\Server\EndpointHandler;
use Dmstr\McpEndpoint\Tool\ToolInspector;

/**
 * The one decision whether the endpoint of the current request offers a
 * tool, shared by listing ({@see EndpointAccessChecker}) and calling
 * ({@see EndpointHandler}), so a tool is never listed but refused or
 * hidden but callable.
 *
 * Default deny, in this order:
 *  - no endpoint in {@see McpEndpointContext}: denied;
 *  - the name is not on the endpoint's `tools` allowlist: denied;
 *  - the name is no API Platform `McpTool` (unknown, a resource, an SDK
 *    attribute tool): denied;
 *  - the tool is not read-only ({@see ToolInspector::isReadOnly()}, a
 *    missing `readOnlyHint` counts as writing) and the endpoint is
 *    `readOnly` or the policy forbids write tools: denied.
 *
 * The operation's own `security` is not part of this decision; the access
 * checker still asks the inner checker, and API Platform enforces it again
 * when the tool runs.
 */
final readonly class EndpointToolGate
{
    public function __construct(
        private McpEndpointContext $context,
        private ToolInspector $tools,
        private McpEndpointPolicyInterface $policy,
    ) {
    }

    public function allows(string $toolName): bool
    {
        $endpoint = $this->context->get();
        if (null === $endpoint || !$endpoint->allowsTool($toolName)) {
            return false;
        }

        $tool = $this->tools->find($toolName);
        if (null === $tool) {
            return false;
        }

        if (ToolInspector::isReadOnly($tool)) {
            return true;
        }

        return !$endpoint->isReadOnly() && $this->policy->allowWrite();
    }
}
