<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dmstr\McpEndpoint\Tests\Kernel\TestDatabase;
use Dmstr\McpEndpoint\Tests\Kernel\TestHeaderAuthenticator;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Without a voter for `mcpendpoint:endpoint:manage` (kernel variant
 * {@see TestKernel::ENV_NO_VOTER}) every operation of the admin resource
 * answers 403 to an authenticated user, even one with the roles the test
 * voter would map, and nothing is stored; anonymous requests still get 401.
 * Needs the PostgreSQL database from `DATABASE_URL`.
 */
#[Group('database')]
final class NoVoterResourceTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const array KERNEL_OPTIONS = ['environment' => TestKernel::ENV_NO_VOTER];
    private const string PREFIX = 'test-no-voter-';

    protected function setUp(): void
    {
        self::cleanUp();
    }

    protected function tearDown(): void
    {
        self::cleanUp();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methods(): iterable
    {
        yield 'list' => ['GET'];
        yield 'create' => ['POST'];
    }

    #[DataProvider('methods')]
    public function testMappedRoleIsForbidden(string $method): void
    {
        $this->send($method, [TestHeaderAuthenticator::HEADER_ROLES => 'ROLE_ADMIN, ROLE_ENDPOINT_ADMIN']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, self::rowCount());
    }

    #[DataProvider('methods')]
    public function testAnonymousIsUnauthorized(string $method): void
    {
        $this->send($method, [TestHeaderAuthenticator::HEADER_AUTH => TestHeaderAuthenticator::ANONYMOUS]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(0, self::rowCount());
    }

    /**
     * @param array<string, string> $headers
     */
    private function send(string $method, array $headers): void
    {
        $options = ['headers' => ['Accept' => 'application/ld+json'] + $headers];
        if ('POST' === $method) {
            $options['headers']['Content-Type'] = 'application/ld+json';
            $options['json'] = ['slug' => self::PREFIX.'one', 'name' => 'One', 'tools' => []];
        }

        static::createClient(self::KERNEL_OPTIONS)->request($method, McpEndpointResourceTest::COLLECTION, $options);
    }

    private static function rowCount(): int
    {
        return (int) self::dbal()->fetchOne('SELECT COUNT(*) FROM mcp_endpoint WHERE slug LIKE ?', [self::PREFIX.'%']);
    }

    private static function dbal(): Connection
    {
        if (null === static::$kernel) {
            static::bootKernel(self::KERNEL_OPTIONS);
        }

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    private static function cleanUp(): void
    {
        TestDatabase::migrate(static::bootKernel(self::KERNEL_OPTIONS));
        self::dbal()->executeStatement('DELETE FROM mcp_endpoint WHERE slug LIKE ?', [self::PREFIX.'%']);
        static::ensureKernelShutdown();
    }
}
