<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:41:25 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Controller;

use Dmstr\McpEndpoint\Context\McpEndpointContext;
use Dmstr\McpEndpoint\Endpoint\McpEndpointProviderInterface;
use Mcp\Server\Builder;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * MCP server of one endpoint: `/mcp/{slug}`. The route belongs to the
 * application (the bundle ships none); this controller is its target.
 *
 * Resolves the endpoint (unknown or disabled: 404), checks its
 * `requiredPermission` against the caller (403, before the transport runs, so no
 * tool list leaks), puts it into {@see McpEndpointContext} for the tool gate,
 * the decorators and the session store, and builds the configured server
 * per request with the endpoint's description as `instructions`.
 */
final readonly class McpEndpointController
{
    public function __construct(
        private McpEndpointProviderInterface $endpoints,
        private McpEndpointContext $context,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Builder $builder,
        private MiddlewareFactory $middlewareFactory,
        private HttpMessageFactoryInterface $httpMessageFactory,
        private HttpFoundationFactoryInterface $httpFoundationFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(Request $request, string $slug): Response
    {
        $endpoint = $this->endpoints->findEnabledBySlug($slug);
        if (null === $endpoint || !$endpoint->isEnabled()) {
            throw new NotFoundHttpException(\sprintf('No MCP endpoint "%s".', $slug));
        }

        // The permission voter of the application decides, via its role map.
        if (!$this->authorizationChecker->isGranted($endpoint->getRequiredPermission())) {
            throw new AccessDeniedHttpException(\sprintf('The MCP endpoint "%s" requires the permission %s.', $slug, $endpoint->getRequiredPermission()));
        }

        $this->context->set($endpoint);

        // `instructions` are part of the server configuration, which the SDK
        // builder resolves once and memoizes on the first build(). A clone of
        // the (still unbuilt) container builder takes the per-request value;
        // the bundle's data collector clones it the same way.
        $builder = clone $this->builder;
        $description = $endpoint->getDescription();
        $server = $builder->setInstructions('' !== $description ? $description : null)->build();

        // The following lines repeat McpController::handle() of
        // symfony/mcp-bundle 0.15.0 (unchanged since 0.14.1): the bundle
        // offers no hook before build(), so its controller cannot set
        // per-endpoint instructions.
        $transport = new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $this->middlewareFactory->create(),
        );

        $psrResponse = $server->run($transport);
        $streamed = 'text/event-stream' === strtolower($psrResponse->getHeaderLine('Content-Type'));

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }
}
