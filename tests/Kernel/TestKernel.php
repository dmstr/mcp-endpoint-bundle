<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Kernel;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Dmstr\DoctrineAuditLog\DoctrineAuditLogBundle;
use Dmstr\DoctrineAuditLog\Entity\LogEntry;
use Dmstr\McpEndpoint\Controller\McpEndpointController;
use Dmstr\McpEndpoint\Endpoint\DoctrineMcpEndpointProvider;
use Dmstr\McpEndpoint\Endpoint\McpEndpointProviderInterface;
use Dmstr\McpEndpoint\McpEndpointBundle;
use Dmstr\McpEndpoint\Policy\McpEndpointPolicyInterface;
use Dmstr\McpEndpoint\Scope\ScopePruner;
use Dmstr\McpEndpoint\Tests\Fixtures\InMemoryEndpointProvider;
use Dmstr\McpEndpoint\Tests\Fixtures\SwitchableEndpointPolicy;
use Dmstr\McpEndpoint\Tests\Fixtures\TestItemsScopeResolver;
use Dmstr\McpEndpoint\Tests\Fixtures\TestPermissions;
use Dmstr\OpenApiJsonSchema\OpenApiJsonSchemaBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Gedmo\Loggable\Entity\MappedSuperclass\AbstractLogEntry;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Minimal host application for the integration tests of the bundle.
 *
 * - PostgreSQL from the `DATABASE_URL` environment variable (default in
 *   `phpunit.dist.xml`). The schema is created by running the migrations,
 *   the bundle's own ones included ({@see TestDatabase}); the container
 *   itself never connects, so tests without the `database` group run
 *   without a server.
 * - API Platform with its MCP layer and the test-only MCP tools of
 *   {@see \Dmstr\McpEndpoint\Tests\Fixtures\TestTools} (mapping path
 *   `tests/Fixtures/Tool`), the test scope resolver of `test_items`
 *   ({@see TestItemsScopeResolver}, tagged by autoconfiguration).
 * - One symfony/mcp-bundle server {@see self::MCP_SERVER} with the HTTP
 *   transport, the `file` session store and literal DNS rebinding lists
 *   ({@see self::ALLOWED_HOSTS}, {@see self::ALLOWED_ORIGINS}); the route
 *   `/mcp/{slug}` on the bundle's controller. The route of symfony/mcp-bundle
 *   is not imported.
 * - Endpoints of the protocol tests from memory
 *   ({@see InMemoryEndpointProvider}, aliased to the provider interface;
 *   every slug it does not hold goes to the Doctrine provider) and the
 *   endpoint policy {@see SwitchableEndpointPolicy}, aliased to the policy
 *   interface as an application would alias its own.
 * - Two stateless firewalls (`^/mcp/` and the rest) authenticated from
 *   request headers by {@see TestHeaderAuthenticator}; `^/mcp/` requires a
 *   full login.
 * - The audit log: dmstr/doctrine-audit-log-bundle provides the Gedmo
 *   loggable listener and the log entry entity; the kernel hands the
 *   listener {@see TestActorProvider} (see {@see self::process()}).
 * - The permission contract of a host application: {@see self::process()}
 *   collects the `za7_core.permission` tags into the parameter
 *   `za7_core.permissions.registered`, {@see self::PERMISSIONS} is the map
 *   parameter `za7_core.permissions`, and {@see TestPermissionVoter} decides
 *   with both.
 *
 * Variants, selected by the kernel environment:
 * - {@see self::ENV_WITH_VOTER}: everything above;
 * - {@see self::ENV_NO_VOTER}: no custom voter at all;
 * - {@see self::ENV_NO_CALL_GUARD}: the bundle option `call_guard` off;
 * - {@see self::ENV_PAGING}: `pagination_limit` 1 on the MCP server.
 *
 * Cache and logs live under the system temp directory, one directory per
 * variant, so a test run leaves no files in the repository.
 */
final class TestKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public const string ENV_WITH_VOTER = 'test';
    public const string ENV_NO_VOTER = 'test_no_voter';
    public const string ENV_NO_CALL_GUARD = 'test_no_call_guard';
    public const string ENV_PAGING = 'test_paging';

    public const string API_PREFIX = '/api';
    public const string MCP_SERVER = 'endpoints';

    /** Hosts of the DNS rebinding protection; the test client sends `localhost`. */
    public const array ALLOWED_HOSTS = ['localhost', 'mcp.internal'];

    /** Origins accepted besides same-origin requests. */
    public const array ALLOWED_ORIGINS = ['https://client.example'];

    public const int PAGINATION_LIMIT = 200;

    public const string PERMISSION_TAG = 'za7_core.permission';
    public const string PERMISSION_MAP_PARAMETER = 'za7_core.permissions';
    public const string PERMISSIONS_REGISTERED_PARAMETER = 'za7_core.permissions.registered';

    /** Permission => roles that hold it, as a host application would map it */
    public const array PERMISSIONS = [
        'mcpendpoint:endpoint:manage' => ['ROLE_ADMIN', 'ROLE_ENDPOINT_ADMIN'],
        'mcpendpoint:endpoint:use' => ['ROLE_ADMIN', 'ROLE_USER'],
        TestPermissions::RESTRICTED => ['ROLE_ADMIN', 'ROLE_RESTRICTED'],
    ];

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new ApiPlatformBundle();
        yield new McpBundle();
        yield new OpenApiJsonSchemaBundle();
        yield new DoctrineAuditLogBundle();
        yield new McpEndpointBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->tmpDir().'/cache';
    }

    public function getLogDir(): string
    {
        return $this->tmpDir().'/log';
    }

    public function hasPermissionVoter(): bool
    {
        return self::ENV_NO_VOTER !== $this->environment;
    }

    /**
     * Host-application wiring that needs the complete container: the list of
     * registered permissions and the actor of audit-log entries.
     */
    public function process(ContainerBuilder $container): void
    {
        $registered = [];
        foreach ($container->findTaggedServiceIds(self::PERMISSION_TAG) as $tags) {
            foreach ($tags as $attributes) {
                if (\is_string($attributes['permission'] ?? null)) {
                    $registered[$attributes['permission']] = true;
                }
            }
        }
        $registered = array_keys($registered);
        sort($registered);
        $container->setParameter(self::PERMISSIONS_REGISTERED_PARAMETER, $registered);

        if ($container->hasDefinition('gedmo.listener.loggable')) {
            $container->getDefinition('gedmo.listener.loggable')
                ->addMethodCall('setActorProvider', [new Reference(TestActorProvider::class)]);
        }
    }

    /**
     * Not the repository's `config/` (the bundle's service definitions): in
     * debug mode FrameworkBundle dumps `reference.php` into an existing
     * config directory. This one does not exist, so nothing is written.
     */
    private function getConfigDir(): string
    {
        return $this->tmpDir().'/config';
    }

    private function tmpDir(): string
    {
        return sys_get_temp_dir().'/dmstr-mcp-endpoint-bundle-tests/'.$this->environment;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set(self::PERMISSION_MAP_PARAMETER, self::PERMISSIONS);

        $container->extension('framework', [
            'test' => true,
            'secret' => 'test-secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'cache' => ['app' => 'cache.adapter.array'],
        ]);

        $authenticatedFirewall = [
            'stateless' => true,
            'provider' => 'test_users',
            'custom_authenticators' => [TestHeaderAuthenticator::class],
        ];
        $container->extension('security', [
            'providers' => [
                'test_users' => ['memory' => ['users' => []]],
            ],
            'firewalls' => [
                // The MCP endpoints; the controller checks the endpoint's
                // permission, the firewall only authentication.
                'mcp' => ['pattern' => '^/mcp/'] + $authenticatedFirewall,
                'main' => ['pattern' => '^/'] + $authenticatedFirewall,
            ],
            'access_control' => [
                // Without it an anonymous request would reach the controller
                // and get 403 instead of the entry point's 401.
                ['path' => '^/mcp/', 'roles' => 'IS_AUTHENTICATED_FULLY'],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => '%env(resolve:DATABASE_URL)%',
            ],
            'orm' => [
                'enable_native_lazy_objects' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                // AUTO ids of the audit-log entity: on DBAL 3 the ORM would
                // pick SEQUENCE and ask for `dmstr_log_entries_id_seq`, but
                // the table was created as `ext_log_entries` with a SERIAL
                // column and renamed, so its sequence keeps the old name.
                // IDENTITY reads the id back with LASTVAL() and works with
                // either name.
                'identity_generation_preferences' => [
                    PostgreSQLPlatform::class => 'identity',
                ],
                // The bundle maps its own entity (prepended configuration);
                // only the audit-log entity and its Gedmo superclass are
                // mapped here, as a host application would.
                'mappings' => [
                    'DoctrineAuditLog' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => \dirname((string) (new \ReflectionClass(LogEntry::class))->getFileName()),
                        'prefix' => 'Dmstr\\DoctrineAuditLog\\Entity',
                    ],
                    'GedmoLoggable' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => \dirname((string) (new \ReflectionClass(AbstractLogEntry::class))->getFileName()),
                        'prefix' => 'Gedmo\\Loggable\\Entity\\MappedSuperclass',
                    ],
                ],
            ],
        ]);

        $container->extension('doctrine_migrations', [
            'storage' => [
                'table_storage' => ['table_name' => 'doctrine_migration_versions'],
            ],
        ]);

        $container->extension('api_platform', [
            'title' => 'mcp-endpoint-bundle tests',
            'formats' => [
                'jsonld' => ['application/ld+json'],
                'json' => ['application/json'],
            ],
            'docs_formats' => [
                'jsonld' => ['application/ld+json'],
                'jsonopenapi' => ['application/vnd.openapi+json'],
            ],
            'patch_formats' => [
                'json' => ['application/merge-patch+json'],
            ],
            'serializer' => ['hydra_prefix' => true],
            'defaults' => ['stateless' => true],
            'enable_swagger_ui' => false,
            'enable_re_doc' => false,
            'mcp' => ['format' => 'json'],
            // The test-only MCP tools; the bundle prepends its entity path.
            'mapping' => [
                'paths' => [\dirname(__DIR__).'/Fixtures/Tool'],
            ],
        ]);

        $container->extension('mcp', [
            'servers' => [
                self::MCP_SERVER => [
                    'transports' => ['http' => true, 'stdio' => false],
                    'http' => [
                        // Only names the route of symfony/mcp-bundle, which
                        // is never imported; clients use /mcp/{slug}.
                        'path' => '/mcp/_server',
                        // Literal lists: `allowed_origins` refuses env
                        // placeholders (prototyped array node).
                        'allowed_hosts' => self::ALLOWED_HOSTS,
                        'allowed_origins' => self::ALLOWED_ORIGINS,
                    ],
                    'pagination_limit' => self::ENV_PAGING === $this->environment ? 1 : self::PAGINATION_LIMIT,
                    'protocol_versions' => [],
                    // Files below the kernel cache directory: the session
                    // outlives the reboot of the kernel between two requests
                    // of the test client.
                    'session' => ['store' => 'file'],
                    'registry' => ['tools' => '*'],
                ],
            ],
        ]);

        $container->extension('mcp_endpoint', [
            'server' => self::MCP_SERVER,
            'call_guard' => self::ENV_NO_CALL_GUARD !== $this->environment,
        ]);

        $services = $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();

        // Keep the test output free of framework log lines
        $services->set('logger', NullLogger::class);

        // Autoconfiguration tags it as a scope resolver
        $services->set(TestItemsScopeResolver::class);

        // A permission of the host application, required by one endpoint
        $services->set(TestPermissions::class)
            ->tag(self::PERMISSION_TAG, ['permission' => TestPermissions::RESTRICTED]);

        $services->set(InMemoryEndpointProvider::class)
            ->arg('$database', service(DoctrineMcpEndpointProvider::class));
        $services->alias(McpEndpointProviderInterface::class, InMemoryEndpointProvider::class);

        $services->set(SwitchableEndpointPolicy::class);
        $services->alias(McpEndpointPolicyInterface::class, SwitchableEndpointPolicy::class);
        // Public, so that tests can check which policy the bundle uses
        $services->alias('test.mcp_endpoint.policy', McpEndpointPolicyInterface::class)->public();

        // Public, so that database tests can call it as the owner of a
        // scope target would
        $services->alias('test.mcp_endpoint.scope_pruner', ScopePruner::class)->public();

        $services->set(TestHeaderAuthenticator::class);
        $services->set(TestActorProvider::class);

        if ($this->hasPermissionVoter()) {
            // Autoconfiguration adds the `security.voter` tag; public so that
            // tests can tell the variants apart
            $services->set(TestPermissionVoter::class)
                ->args([param(self::PERMISSION_MAP_PARAMETER), param(self::PERMISSIONS_REGISTERED_PARAMETER)])
                ->public();
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api_platform')->prefix(self::API_PREFIX);

        // The application's route of the endpoints, as in the README
        $routes->add('mcp_endpoint', '/mcp/{slug}')
            ->controller(McpEndpointController::class)
            ->methods(['GET', 'POST', 'DELETE', 'OPTIONS'])
            ->requirements(['slug' => '[a-z0-9-]+']);
    }
}
