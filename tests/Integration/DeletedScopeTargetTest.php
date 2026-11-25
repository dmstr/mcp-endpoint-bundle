<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Dmstr\McpEndpoint\Scope\ScopePruner;
use Dmstr\McpEndpoint\Tests\Fixtures\TestItemsScopeResolver;
use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;
use Dmstr\McpEndpoint\Tests\Kernel\TestDatabase;
use Dmstr\McpEndpoint\Validator\ValidMcpEndpointValidator;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * An endpoint whose scope holds the id of a deleted target can be saved
 * again. The owner of the target removes the id through {@see ScopePruner}
 * (audited); for a target deleted past it, the validator drops the id on
 * the next save. A new unknown id stays 422, and a scope a tool needs that
 * would end up empty is 422 naming the deleted id.
 *
 * Targets are the items of {@see TestItemsScopeResolver}; a test deletes
 * one by listing it in `TestItemsScopeResolver::$deleted`. Endpoints carry
 * the slug prefix {@see self::PREFIX}. Needs the PostgreSQL database from
 * `DATABASE_URL`.
 */
#[Group('database')]
final class DeletedScopeTargetTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const string PREFIX = 'test-deleted-target-';
    private const string ENDPOINTS = '/api/admin/mcp_endpoints';
    private const string UNKNOWN_ID = '0199c0de-0000-7000-8000-0000000000ff';

    protected function setUp(): void
    {
        TestItemsScopeResolver::$deleted = [];
        self::cleanUp();
    }

    protected function tearDown(): void
    {
        TestItemsScopeResolver::$deleted = [];
        self::cleanUp();
        parent::tearDown();
    }

    public function testThePrunerRemovesTheIdAndIsAudited(): void
    {
        [$alpha, $beta] = [TestItemsScopeResolver::ITEMS['alpha'], TestItemsScopeResolver::ITEMS['beta']];
        $endpointId = self::endpoint('pruned', ['alpha', 'beta']);
        self::assertSame(1, self::auditEntries($endpointId));

        TestItemsScopeResolver::$deleted = ['alpha'];
        static::bootKernel();
        /** @var ScopePruner $pruner */
        $pruner = static::getContainer()->get('test.mcp_endpoint.scope_pruner');
        self::assertSame(1, $pruner->removeId(TestItemsScopeResolver::KEY, strtoupper($alpha)));
        static::ensureKernelShutdown();

        self::assertSame(2, self::auditEntries($endpointId));
        self::assertSame([TestItemsScopeResolver::KEY => [$beta]], self::request('GET', self::ENDPOINTS.'/'.$endpointId)->toArray()['scope']);

        $response = self::request('PATCH', self::ENDPOINTS.'/'.$endpointId, ['description' => 'Changed']);
        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        self::assertSame([TestItemsScopeResolver::KEY => [$beta]], $response->toArray()['scope']);
    }

    public function testATargetDeletedPastThePrunerIsDroppedOnSave(): void
    {
        [$alpha, $beta] = [TestItemsScopeResolver::ITEMS['alpha'], TestItemsScopeResolver::ITEMS['beta']];
        $endpointId = self::endpoint('dropped', ['alpha', 'beta']);
        TestItemsScopeResolver::$deleted = ['alpha'];

        $response = self::request('PATCH', self::ENDPOINTS.'/'.$endpointId, ['description' => 'Changed']);
        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        self::assertSame([TestItemsScopeResolver::KEY => [$beta]], $response->toArray()['scope']);

        // Sending the stored scope again (dead id included) passes as well.
        self::dbal()->executeStatement('UPDATE mcp_endpoint SET scope = CAST(? AS json) WHERE id = ?', [json_encode([TestItemsScopeResolver::KEY => [$alpha, $beta]], \JSON_THROW_ON_ERROR), $endpointId]);
        $response = self::request('PATCH', self::ENDPOINTS.'/'.$endpointId, ['scope' => [TestItemsScopeResolver::KEY => [$alpha, $beta]]]);
        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        self::assertSame([TestItemsScopeResolver::KEY => [$beta]], $response->toArray()['scope']);
    }

    public function testANewUnknownIdIsStillRejected(): void
    {
        $endpointId = self::endpoint('unknown', ['alpha']);

        $response = self::request('PATCH', self::ENDPOINTS.'/'.$endpointId, ['scope' => [TestItemsScopeResolver::KEY => [TestItemsScopeResolver::ITEMS['alpha'], self::UNKNOWN_ID]]]);

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertArrayHasKey('scope/test_items/1', self::violations($response));
    }

    public function testTheLastIdOfARequiredScopeIsNamed(): void
    {
        $alpha = TestItemsScopeResolver::ITEMS['alpha'];
        $endpointId = self::endpoint('last', ['alpha']);
        TestItemsScopeResolver::$deleted = ['alpha'];

        $response = self::request('PATCH', self::ENDPOINTS.'/'.$endpointId, ['description' => 'Changed']);

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(
            strtr(ValidMcpEndpointValidator::SCOPE_DELETED_MESSAGE, ['{{ tool }}' => TestTools::SCOPED_TOOL, '{{ key }}' => TestItemsScopeResolver::KEY, '{{ references }}' => '"'.$alpha.'"']),
            self::violations($response)['scope/test_items'] ?? null,
        );
    }

    /**
     * An endpoint with the scoped test tool over $items, created through
     * the API; its id.
     *
     * @param list<string> $items
     */
    private static function endpoint(string $slug, array $items): string
    {
        $response = self::request('POST', self::ENDPOINTS, [
            'slug' => self::PREFIX.$slug,
            'name' => ucfirst($slug),
            'tools' => [TestTools::SCOPED_TOOL],
            'scope' => [TestItemsScopeResolver::KEY => $items],
        ]);
        self::assertSame(201, $response->getStatusCode(), $response->getContent(false));

        return (string) $response->toArray()['id'];
    }

    /**
     * One request with a fresh client as `test-admin` (ROLE_ADMIN); PATCH
     * is sent as merge patch.
     *
     * @param array<string, mixed>|null $json
     */
    private static function request(string $method, string $uri, ?array $json = null): ResponseInterface
    {
        $options = ['headers' => ['Accept' => 'application/ld+json']];
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

    private static function auditEntries(string $endpointId): int
    {
        return (int) self::dbal()->fetchOne(
            'SELECT COUNT(*) FROM dmstr_log_entries WHERE object_class = ? AND object_id = ?',
            [McpEndpoint::class, $endpointId],
        );
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

    private static function cleanUp(): void
    {
        TestDatabase::migrate(static::bootKernel());
        $database = self::dbal();
        $database->executeStatement(
            'DELETE FROM dmstr_log_entries WHERE object_class = ? AND object_id IN (SELECT id::text FROM mcp_endpoint WHERE slug LIKE ?)',
            [McpEndpoint::class, self::PREFIX.'%'],
        );
        $database->executeStatement('DELETE FROM mcp_endpoint WHERE slug LIKE ?', [self::PREFIX.'%']);
        static::ensureKernelShutdown();
    }
}
