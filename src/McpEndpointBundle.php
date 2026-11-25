<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint;

use Dmstr\McpEndpoint\Context\McpEndpointContext;
use Dmstr\McpEndpoint\Controller\McpEndpointController;
use Dmstr\McpEndpoint\Endpoint\McpEndpointProviderInterface;
use Dmstr\McpEndpoint\Scope\ScopeResolverInterface;
use Dmstr\McpEndpoint\Session\EndpointSessionStore;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * MCP endpoint profiles: one {@see Entity\McpEndpoint} per URL slug, each
 * publishing a slice of the API Platform MCP tools (tool allowlist,
 * read-only flag, required permission, scope, retrieval limits).
 *
 * The bundle provides
 *  - the entity with its admin CRUD (`/api/admin/mcp_endpoints`,
 *    permission `mcpendpoint:endpoint:manage`) and its migration;
 *  - the validation of an endpoint against the registered MCP tools
 *    ({@see Validator\ValidMcpEndpointValidator});
 *  - the scope port: services implementing
 *    {@see Scope\ScopeResolverInterface} are tagged
 *    `dmstr_mcp_endpoint.scope_resolver` by autoconfiguration (services
 *    defined in other bundles tag themselves explicitly);
 *  - the policy port {@see Policy\McpEndpointPolicyInterface} with a
 *    fail-closed default the application may replace by an alias;
 *  - the endpoint lookup {@see Endpoint\McpEndpointProviderInterface};
 *  - the seed command `mcp-endpoint:seed`;
 *  - the request-time enforcement: {@see Controller\McpEndpointController}
 *    as the target of the application's `/mcp/{slug}` route, the shared
 *    {@see Security\EndpointToolGate} behind the decorators of API
 *    Platform's MCP access checker ({@see Security\EndpointAccessChecker})
 *    and MCP handler ({@see Server\EndpointHandler}), the default-deny
 *    {@see Server\UnservedRequestHandler} and the slug-bound session store
 *    ({@see Session\EndpointSessionStore}).
 *
 * Configuration (`mcp_endpoint`):
 *  - `server`: name of the symfony/mcp-bundle server the endpoints run on
 *    (`mcp.servers.<name>`, with `transports.http` on). Without it the
 *    controller and the session store decorator are not registered; the
 *    access checker and handler decorators still deny every tool.
 *  - `call_guard` (default true): off only in a test kernel, for the
 *    counter-proof that the handler decorator blocks unlisted tools.
 */
final class McpEndpointBundle extends AbstractBundle
{
    public const string SCOPE_RESOLVER_TAG = 'dmstr_mcp_endpoint.scope_resolver';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->stringNode('server')
                    ->info('Name of the symfony/mcp-bundle server (mcp.servers.<name>) that serves the endpoints.')
                    ->defaultNull()
                ->end()
                ->booleanNode('call_guard')
                    ->info('Whether tools/call is checked against the endpoint. Switch off in test kernels only.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'DmstrMcpEndpoint' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Dmstr\\McpEndpoint\\Entity',
                        'alias' => 'DmstrMcpEndpoint',
                    ],
                ],
            ],
        ]);

        $builder->prependExtensionConfig('api_platform', [
            'mapping' => [
                'paths' => [__DIR__.'/Entity'],
            ],
        ]);

        $builder->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Dmstr\\McpEndpoint\\Migrations' => __DIR__.'/../migrations',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Applies to services with autoconfigure (e.g. the application's own
        // service resource); definitions with autoconfigure off add the tag themselves.
        $builder->registerForAutoconfiguration(ScopeResolverInterface::class)
            ->addTag(self::SCOPE_RESOLVER_TAG);

        $container->parameters()->set('dmstr_mcp_endpoint.call_guard', $config['call_guard']);

        $container->import(__DIR__.'/../config/services.yaml');

        $server = $config['server'];
        if (null === $server || '' === $server) {
            return;
        }

        $services = $container->services();

        // The server's builder and middleware factory exist while the
        // server has `transports.http` on.
        $services->alias('dmstr_mcp_endpoint.server.builder', \sprintf('mcp.server.%s.builder', $server));
        $services->alias('dmstr_mcp_endpoint.server.middleware_factory', \sprintf('mcp.server.%s.middleware_factory', $server));

        // Target of the application's /mcp/{slug} route.
        $services->set(McpEndpointController::class)
            ->args([
                service(McpEndpointProviderInterface::class),
                service(McpEndpointContext::class),
                service('security.authorization_checker'),
                service('dmstr_mcp_endpoint.server.builder'),
                service('dmstr_mcp_endpoint.server.middleware_factory'),
                service('mcp.psr_http_factory'),
                service('mcp.http_foundation_factory'),
                service('mcp.psr17_factory'),
                service('mcp.psr17_factory'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->public()
            ->tag('monolog.logger', ['channel' => 'mcp']);

        // Session ids bound to the slug they were opened on.
        $services->set(EndpointSessionStore::class)
            ->decorate(\sprintf('mcp.server.%s.session.store', $server))
            ->args([
                service('.inner'),
                service(McpEndpointContext::class),
            ]);
    }
}
