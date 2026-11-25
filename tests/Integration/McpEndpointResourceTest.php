<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dmstr\McpEndpoint\Endpoint\DoctrineMcpEndpointProvider;
use Dmstr\McpEndpoint\Endpoint\McpEndpointProviderInterface;
use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Dmstr\McpEndpoint\Tests\Fixtures\TestItemsScopeResolver;
use Dmstr\McpEndpoint\Tests\Fixtures\TestPermissions;
use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;
use Dmstr\McpEndpoint\Tests\Kernel\TestDatabase;
use Dmstr\McpEndpoint\Tests\Kernel\TestHeaderAuthenticator;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Admin CRUD of MCP endpoints at `/api/admin/mcp_endpoints`: lifecycle,
 * the 422 cases with their property paths (unknown tool `tools/0`, slug
 * pattern and uniqueness, unregistered permission, write tool on a
 * read-only endpoint, scope-required tool with empty scope, unknown scope
 * reference, `maxK` above 50), the resolution of scope references to ids,
 * access (permission `mcpendpoint:endpoint:manage`: 403 for `ROLE_USER`,
 * 401 anonymous), the Doctrine endpoint lookup and the audit log.
 *
 * Tools: the test-only MCP tools of {@see TestTools}; the scope key
 * `test_items` is resolved by {@see TestItemsScopeResolver}.
 *
 * Rows carry the slug prefix `test-mcp-endpoint-`. Needs the PostgreSQL
 * database from `DATABASE_URL`.
 */
#[Group('database')]
final class McpEndpointResourceTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public const string PREFIX = 'test-mcp-endpoint-';
    public const string COLLECTION = '/api/admin/mcp_endpoints';

    protected function setUp(): void
    {
        self::cleanUp();
    }

    protected function tearDown(): void
    {
        self::cleanUp();
        parent::tearDown();
    }

    public function testCreateGetsTheDefaults(): void
    {
        $response = self::request('POST', self::COLLECTION, null, ['slug' => self::PREFIX.'spy', 'name' => 'Spy', 'tools' => [TestTools::READ_TOOL]]);

        self::assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        self::assertSame(self::PREFIX.'spy', $data['slug']);
        self::assertSame([TestTools::READ_TOOL], $data['tools']);
        self::assertTrue($data['enabled']);
        self::assertTrue($data['readOnly']);
        self::assertSame('mcpendpoint:endpoint:use', $data['requiredPermission']);
        self::assertSame('', $data['description']);
        self::assertEmpty($data['scope']);
        self::assertEmpty($data['retrieval']);
        self::assertNotEmpty($data['createdAt']);
    }

    public function testLifecycle(): void
    {
        $id = self::create('life', ['description' => 'Product documentation.', 'retrieval' => ['maxK' => 20, 'hybrid' => true, 'hybridWeight' => 0.3]]);
        $item = self::COLLECTION.'/'.$id;

        $data = self::request('GET', $item, null)->toArray();
        self::assertSame('Product documentation.', $data['description']);
        self::assertSame(['maxK' => 20, 'hybrid' => true, 'hybridWeight' => 0.3], $data['retrieval']);

        $list = self::request('GET', self::COLLECTION.'?slug='.self::PREFIX.'life', null)->toArray();
        self::assertSame([$id], array_column($list['hydra:member'], 'id'));

        self::request('PATCH', $item, null, ['enabled' => false, 'requiredPermission' => TestPermissions::RESTRICTED]);
        self::assertResponseStatusCodeSame(200);
        self::assertFalse((bool) self::dbal()->fetchOne('SELECT enabled FROM mcp_endpoint WHERE id = :id', ['id' => $id]));

        self::request('DELETE', $item, null);
        self::assertResponseStatusCodeSame(204);
        self::request('GET', $item, null);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownToolIsRejectedAtItsIndex(): void
    {
        $id = self::create('unknown');

        $response = self::request('PATCH', self::COLLECTION.'/'.$id, null, ['tools' => ['no_such_tool']]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('tools/0', self::violations($response));
        self::assertSame([TestTools::READ_TOOL], json_decode((string) self::dbal()->fetchOne('SELECT tools FROM mcp_endpoint WHERE id = :id', ['id' => $id]), true));
    }

    public function testSlugOutsideThePatternIsRejected(): void
    {
        $response = self::request('POST', self::COLLECTION, null, ['slug' => 'Foo Bar', 'name' => 'Foo', 'tools' => [TestTools::READ_TOOL]]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('slug', self::violations($response));
    }

    #[DataProvider('refusedPermissions')]
    public function testUnregisteredPermissionOrRoleIsRejected(string $permission): void
    {
        $id = self::create('perm');

        $response = self::request('PATCH', self::COLLECTION.'/'.$id, null, ['requiredPermission' => $permission]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('requiredPermission', self::violations($response));
        self::assertSame('mcpendpoint:endpoint:use', self::dbal()->fetchOne('SELECT required_permission FROM mcp_endpoint WHERE id = :id', ['id' => $id]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedPermissions(): iterable
    {
        yield 'not registered' => ['testapp:endpoint:unknown'];
        yield 'role name' => ['ROLE_RESTRICTED'];
        yield 'role name of the old default' => ['ROLE_USER'];
    }

    public function testDuplicateSlugIsRejected(): void
    {
        self::create('twice');

        $response = self::request('POST', self::COLLECTION, null, ['slug' => self::PREFIX.'twice', 'name' => 'Again', 'tools' => [TestTools::READ_TOOL]]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('slug', self::violations($response));
    }

    public function testWriteToolOnReadOnlyEndpointIsRejected(): void
    {
        $response = self::request('POST', self::COLLECTION, null, ['slug' => self::PREFIX.'write', 'name' => 'Write', 'tools' => [TestTools::WRITE_TOOL]]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('tools/0', self::violations($response));

        self::request('POST', self::COLLECTION, null, ['slug' => self::PREFIX.'write', 'name' => 'Write', 'tools' => [TestTools::WRITE_TOOL], 'readOnly' => false]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testScopeRequiredToolWithEmptyScopeIsRejected(): void
    {
        $response = self::request('POST', self::COLLECTION, null, ['slug' => self::PREFIX.'scoped', 'name' => 'Scoped', 'tools' => [TestTools::SCOPED_TOOL]]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('scope/test_items', self::violations($response));
    }

    public function testScopeReferencesAreStoredAsIds(): void
    {
        $id = self::create('items', ['tools' => [TestTools::SCOPED_TOOL], 'scope' => ['test_items' => ['alpha', 'beta']]]);

        $data = self::request('GET', self::COLLECTION.'/'.$id, null)->toArray();
        self::assertSame(['test_items' => array_values(TestItemsScopeResolver::ITEMS)], $data['scope']);
    }

    public function testUnknownScopeReferenceIsRejected(): void
    {
        $response = self::request('POST', self::COLLECTION, null, ['slug' => self::PREFIX.'items', 'name' => 'Items', 'tools' => [TestTools::SCOPED_TOOL], 'scope' => ['test_items' => ['alpha', 'gamma']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('scope/test_items/1', self::violations($response));
    }

    public function testMaxKAboveTheLimitIsRejected(): void
    {
        $response = self::request('POST', self::COLLECTION, null, ['slug' => self::PREFIX.'maxk', 'name' => 'MaxK', 'tools' => [TestTools::READ_TOOL], 'retrieval' => ['maxK' => 51]]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('retrieval/maxK', self::violations($response));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function operations(): iterable
    {
        yield 'list' => ['GET', false];
        yield 'create' => ['POST', false];
        yield 'item' => ['GET', true];
        yield 'patch' => ['PATCH', true];
        yield 'delete' => ['DELETE', true];
    }

    #[DataProvider('operations')]
    public function testUserWithoutTheManagePermissionIsForbidden(string $method, bool $onItem): void
    {
        $uri = $onItem ? self::COLLECTION.'/'.self::create('access') : self::COLLECTION;
        $json = \in_array($method, ['POST', 'PATCH'], true) ? ['slug' => self::PREFIX.'other', 'name' => 'Other', 'tools' => [TestTools::READ_TOOL]] : null;

        self::request($method, $uri, 'ROLE_USER', $json);

        self::assertResponseStatusCodeSame(403);
        self::assertSame($onItem ? 1 : 0, (int) self::dbal()->fetchOne('SELECT COUNT(*) FROM mcp_endpoint WHERE slug LIKE :prefix', ['prefix' => self::PREFIX.'%']));
    }

    /** A role the host application maps to `mcpendpoint:endpoint:manage` suffices on its own. */
    public function testMappedRoleSuffices(): void
    {
        self::request('GET', self::COLLECTION, 'ROLE_ENDPOINT_ADMIN');

        self::assertResponseStatusCodeSame(200);
    }

    public function testAnonymousIsUnauthorized(): void
    {
        self::request('GET', self::COLLECTION, null, null, [TestHeaderAuthenticator::HEADER_AUTH => TestHeaderAuthenticator::ANONYMOUS]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testProviderFindsOnlyEnabledEndpoints(): void
    {
        $id = self::create('lookup');
        self::create('off', ['enabled' => false]);

        // The Doctrine provider itself and the interface (the test kernel's
        // in-memory provider hands unknown slugs to it).
        foreach ([DoctrineMcpEndpointProvider::class, McpEndpointProviderInterface::class] as $service) {
            /** @var McpEndpointProviderInterface $provider */
            $provider = self::getContainer()->get($service);

            self::assertSame($id, $provider->findEnabledBySlug(self::PREFIX.'lookup')?->getId()->toRfc4122(), $service);
            self::assertNull($provider->findEnabledBySlug(self::PREFIX.'off'), $service);
            self::assertNull($provider->findEnabledBySlug(self::PREFIX.'missing'), $service);
            self::assertNull($provider->findEnabledBySlug('Not A Slug'), $service);
        }
    }

    public function testChangesAreAudited(): void
    {
        $id = self::create('audit');
        self::request('PATCH', self::COLLECTION.'/'.$id, null, ['tools' => [TestTools::READ_TOOL], 'enabled' => false]);

        $count = (int) self::dbal()->fetchOne('SELECT COUNT(*) FROM dmstr_log_entries WHERE object_class = :class AND object_id = :id', ['class' => McpEndpoint::class, 'id' => $id]);
        self::assertSame(2, $count);
    }

    /**
     * Creates an endpoint `<prefix><slug>` with the read tool; returns its id.
     *
     * @param array<string, mixed> $fields
     */
    public static function create(string $slug, array $fields = []): string
    {
        $response = self::request('POST', self::COLLECTION, null, $fields + ['slug' => self::PREFIX.$slug, 'name' => ucfirst($slug), 'tools' => [TestTools::READ_TOOL]]);
        self::assertResponseStatusCodeSame(201, (string) $response->getContent(false));

        return (string) $response->toArray()['id'];
    }

    /**
     * One request with a fresh client; requests without $roles run as
     * `test-admin` with `ROLE_ADMIN`. PATCH is sent as merge patch.
     *
     * @param array<string, mixed>|null $json
     * @param array<string, string>     $headers
     */
    public static function request(string $method, string $uri, ?string $roles, ?array $json = null, array $headers = []): ResponseInterface
    {
        $options = ['headers' => ['Accept' => 'application/ld+json'] + (null === $roles ? [] : [TestHeaderAuthenticator::HEADER_ROLES => $roles]) + $headers];
        if (null !== $json) {
            $options['headers']['Content-Type'] = 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json';
            $options['body'] = json_encode($json, \JSON_THROW_ON_ERROR);
        }

        return static::createClient()->request($method, $uri, $options);
    }

    /**
     * @return array<string, string> message by property path
     */
    private static function violations(ResponseInterface $response): array
    {
        return array_column($response->toArray(false)['violations'] ?? [], 'message', 'propertyPath');
    }

    private static function dbal(): Connection
    {
        if (null === static::$kernel) {
            static::bootKernel();
        }

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    /** Removes the test endpoints and every audit entry of McpEndpoint. */
    public static function cleanUp(): void
    {
        TestDatabase::migrate(static::bootKernel());
        $connection = self::dbal();
        $connection->executeStatement('DELETE FROM mcp_endpoint WHERE slug LIKE :prefix', ['prefix' => self::PREFIX.'%']);
        $connection->executeStatement('DELETE FROM dmstr_log_entries WHERE object_class = :class', ['class' => McpEndpoint::class]);
        static::ensureKernelShutdown();
    }
}
