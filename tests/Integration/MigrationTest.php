<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration;

use Dmstr\McpEndpoint\Security\McpEndpointPermissions;
use Dmstr\McpEndpoint\Tests\Kernel\TestDatabase;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The schema the bundle's migrations create. Existing databases store the
 * migrations' class names, so they must not change. Needs the PostgreSQL
 * database from `DATABASE_URL`.
 */
#[Group('database')]
final class MigrationTest extends KernelTestCase
{
    private const string TABLE = 'mcp_endpoint';

    /** @var list<string> */
    private const array MIGRATIONS = [
        'Dmstr\\McpEndpoint\\Migrations\\Version20261008223000',
        'Dmstr\\McpEndpoint\\Migrations\\Version20261031200000',
    ];

    private Connection $connection;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        TestDatabase::migrate(self::bootKernel());
        $this->connection = self::getContainer()->get('doctrine.dbal.default_connection');
    }

    public function testBundleMigrationsAreExecuted(): void
    {
        $executed = $this->connection->fetchFirstColumn('SELECT version FROM doctrine_migration_versions');

        foreach (self::MIGRATIONS as $migration) {
            self::assertContains($migration, $executed);
        }
    }

    public function testTableHasExpectedColumns(): void
    {
        $columns = $this->connection->fetchAllKeyValue(
            "SELECT column_name, data_type FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ?",
            [self::TABLE],
        );

        self::assertSame([
            'created_at' => 'timestamp with time zone',
            'description' => 'text',
            'enabled' => 'boolean',
            'id' => 'uuid',
            'name' => 'character varying',
            'read_only' => 'boolean',
            'required_permission' => 'character varying',
            'retrieval' => 'json',
            'scope' => 'json',
            'slug' => 'character varying',
            'tools' => 'json',
            'updated_at' => 'timestamp with time zone',
        ], self::sorted($columns));
    }

    public function testRequiredPermissionDefaultsToTheUsePermission(): void
    {
        $default = $this->connection->fetchOne(
            "SELECT column_default FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ? AND column_name = 'required_permission'",
            [self::TABLE],
        );

        self::assertIsString($default);
        self::assertStringContainsString("'".McpEndpointPermissions::ENDPOINT_USE."'", $default);
    }

    public function testSlugIsUnique(): void
    {
        $definition = $this->connection->fetchOne(
            "SELECT indexdef FROM pg_indexes
             WHERE schemaname = current_schema() AND tablename = ? AND indexname = 'uniq_mcp_endpoint_slug'",
            [self::TABLE],
        );

        self::assertIsString($definition);
        self::assertStringContainsString('UNIQUE INDEX', $definition);
        self::assertStringContainsString('(slug)', $definition);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }
}
