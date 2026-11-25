<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:41:25 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Server;

use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\CompletionCompleteRequest;
use Mcp\Schema\Request\GetPromptRequest;
use Mcp\Schema\Request\ListPromptsRequest;
use Mcp\Schema\Request\ListResourceTemplatesRequest;
use Mcp\Schema\Request\ReadResourceRequest;
use Mcp\Schema\Request\ResourceSubscribeRequest;
use Mcp\Schema\Request\ResourceUnsubscribeRequest;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Schema\Result\ListResourceTemplatesResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * Default deny for every request the SDK would otherwise answer from the
 * server registry without asking the endpoint.
 *
 * The SDK appends its own handlers after the custom ones (Builder::build()),
 * and they read the registry unfiltered: CallToolHandler and
 * ReadResourceHandler through getTool()/getResource(), GetPromptHandler,
 * CompletionCompleteHandler and the subscription handlers by name or URI,
 * ListPromptsHandler and ListResourceTemplatesHandler page through
 * everything (API Platform's SecureRegistry filters tools and resources
 * only). Registered with a low priority on `mcp.request_handler`, this
 * handler comes after {@see EndpointHandler} and before the SDK defaults:
 *
 * - tools/call it sees only for names API Platform's handler does not know
 *   (unknown names, tools registered through the SDK's own attribute): no
 *   endpoint offers those, so "method not found";
 * - resources/read, prompts/get, completion/complete, resources/subscribe
 *   and resources/unsubscribe: "method not found";
 * - prompts/list and resources/templates/list: an empty list, so a client
 *   that asks anyway gets an answer instead of an error.
 *
 * Endpoints publish tools only. tools/list and resources/list stay with the
 * SDK: SecureRegistry filters both through EndpointAccessChecker.
 *
 * @implements RequestHandlerInterface<mixed>
 */
final readonly class UnservedRequestHandler implements RequestHandlerInterface
{
    /** @var list<class-string<Request>> */
    private const array DENIED = [
        CallToolRequest::class,
        ReadResourceRequest::class,
        GetPromptRequest::class,
        CompletionCompleteRequest::class,
        ResourceSubscribeRequest::class,
        ResourceUnsubscribeRequest::class,
    ];

    public function supports(Request $request): bool
    {
        if ($request instanceof ListPromptsRequest || $request instanceof ListResourceTemplatesRequest) {
            return true;
        }

        foreach (self::DENIED as $class) {
            if ($request instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        return match (true) {
            $request instanceof ListPromptsRequest => new Response($request->getId(), new ListPromptsResult([])),
            $request instanceof ListResourceTemplatesRequest => new Response($request->getId(), new ListResourceTemplatesResult([])),
            default => self::methodNotFound($request),
        };
    }

    /**
     * The JSON-RPC answer for a request the endpoint does not serve; names
     * the tool, resource or prompt when the request has one.
     */
    public static function methodNotFound(Request $request): Error
    {
        $name = match (true) {
            $request instanceof CallToolRequest, $request instanceof GetPromptRequest => $request->name,
            $request instanceof ReadResourceRequest,
            $request instanceof ResourceSubscribeRequest,
            $request instanceof ResourceUnsubscribeRequest => $request->uri,
            default => $request::getMethod(),
        };

        return Error::forMethodNotFound(\sprintf('MCP operation "%s" not found.', $name), $request->getId());
    }
}
