<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:41:25 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Server;

use Dmstr\McpEndpoint\Security\EndpointAccessChecker;
use Dmstr\McpEndpoint\Security\EndpointToolGate;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * Stops tools/call of a tool the endpoint does not offer before any provider
 * or processor runs.
 *
 * The listing filter ({@see EndpointAccessChecker}) does not cover calls: a
 * hidden tool stays callable by name through API Platform's Handler. This
 * decorator of `api_platform.mcp.handler` (the tag `mcp.request_handler`
 * moves to it) therefore:
 *
 * - answers supports() exactly like the inner handler. Returning false would
 *   not reject the request but hand it to the SDK's CallToolHandler, which
 *   finds the tool through SecureRegistry::getTool() (unfiltered) and runs it;
 * - rejects in handle() with JSON-RPC "method not found" every tool the
 *   {@see EndpointToolGate} denies (the same decision as the listing) and
 *   every resources/read (default deny, the bundle publishes no resources).
 *
 * `$callGuard` (bundle option `call_guard`) is true except in a test kernel
 * that switches it off for the counter-proof that this decorator is what
 * blocks unlisted tools.
 *
 * @implements RequestHandlerInterface<mixed>
 */
final readonly class EndpointHandler implements RequestHandlerInterface
{
    /**
     * @param RequestHandlerInterface<mixed> $inner
     */
    public function __construct(
        private RequestHandlerInterface $inner,
        private EndpointToolGate $gate,
        private bool $callGuard = true,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->inner->supports($request);
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        if (!$this->callGuard) {
            return $this->inner->handle($request, $session);
        }

        if ($request instanceof CallToolRequest && $this->gate->allows($request->name)) {
            return $this->inner->handle($request, $session);
        }

        return UnservedRequestHandler::methodNotFound($request);
    }
}
