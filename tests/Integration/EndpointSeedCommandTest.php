<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration;

use Dmstr\McpEndpoint\Tests\Fixtures\TestItemsScopeResolver;
use Dmstr\McpEndpoint\Tests\Fixtures\TestPermissions;
use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;
use Dmstr\McpEndpoint\Tests\Kernel\TestDatabase;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `mcp-endpoint:seed` against the database: create, no-op (no flush,
 * `updated_at` unchanged), update in place, scope references stored as ids,
 * and rejected input written nowhere. Rows carry the slug prefix
 * `test-mcp-endpoint-seed-`. The tools are the test-only MCP tools of
 * {@see TestTools}. Needs the PostgreSQL database from `DATABASE_URL`.
 */
#[Group('database')]
final class EndpointSeedCommandTest extends KernelTestCase
{
    private const string PREFIX = 'test-mcp-endpoint-seed-';

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        TestDatabase::migrate(self::bootKernel());
        self::cleanUp();
    }

    protected function tearDown(): void
    {
        self::cleanUp();
        parent::tearDown();
    }

    public function testFirstRunCreatesTheEndpointWithDefaults(): void
    {
        $tester = $this->seed(['slug' => self::PREFIX.'docs', '--tools' => TestTools::READ_TOOL]);

        self::assertStringContainsString('created', $tester->getDisplay());
        $row = self::row(self::PREFIX.'docs');
        self::assertSame(self::PREFIX.'docs', $row['name']);
        self::assertStringContainsString('prompt injection', (string) $row['description']);
        self::assertTrue((bool) $row['enabled']);
        self::assertTrue((bool) $row['read_only']);
        self::assertSame('mcpendpoint:endpoint:use', $row['required_permission']);
        self::assertSame([TestTools::READ_TOOL], json_decode((string) $row['tools'], true));
    }

    public function testSecondRunWithTheSameArgumentsChangesNothing(): void
    {
        $arguments = ['slug' => self::PREFIX.'same', '--tools' => TestTools::SCOPED_TOOL, '--scope' => [TestItemsScopeResolver::KEY.':alpha,beta'], '--max-k' => '20'];
        $this->seed($arguments);
        $before = self::row(self::PREFIX.'same');

        $tester = $this->seed($arguments);

        self::assertStringContainsString('unchanged', $tester->getDisplay());
        self::assertSame($before['updated_at'], self::row(self::PREFIX.'same')['updated_at']);
        self::assertSame([TestItemsScopeResolver::KEY => array_values(TestItemsScopeResolver::ITEMS)], json_decode((string) $before['scope'], true));
    }

    public function testChangedArgumentsUpdateInPlace(): void
    {
        $this->seed(['slug' => self::PREFIX.'upd', '--tools' => TestTools::READ_TOOL]);
        $id = self::row(self::PREFIX.'upd')['id'];

        $tester = $this->seed(['slug' => self::PREFIX.'upd', '--tools' => TestTools::WRITE_TOOL, '--read-only' => 'false', '--required-permission' => TestPermissions::RESTRICTED, '--disabled' => true]);

        self::assertStringContainsString('updated', $tester->getDisplay());
        $row = self::row(self::PREFIX.'upd');
        self::assertSame($id, $row['id']);
        self::assertFalse((bool) $row['read_only']);
        self::assertFalse((bool) $row['enabled']);
        self::assertSame(TestPermissions::RESTRICTED, $row['required_permission']);
    }

    public function testInvalidDefinitionIsRejectedAndNothingIsWritten(): void
    {
        $tester = $this->seed(['slug' => self::PREFIX.'bad', '--tools' => TestTools::SCOPED_TOOL], Command::FAILURE);

        self::assertStringContainsString('scope/test_items', $tester->getDisplay());
        self::assertFalse(self::row(self::PREFIX.'bad'));
    }

    public function testRejectedUpdateKeepsTheStoredEndpoint(): void
    {
        $this->seed(['slug' => self::PREFIX.'keep', '--tools' => TestTools::READ_TOOL]);

        $tester = $this->seed(['slug' => self::PREFIX.'keep', '--tools' => 'no_such_tool'], Command::FAILURE);

        self::assertStringContainsString('tools/0', $tester->getDisplay());
        self::assertSame([TestTools::READ_TOOL], json_decode((string) self::row(self::PREFIX.'keep')['tools'], true));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function seed(array $input, int $expected = Command::SUCCESS): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('mcp-endpoint:seed'));
        self::assertSame($expected, $tester->execute($input), $tester->getDisplay());

        return $tester;
    }

    /**
     * @return array<string, mixed>|false
     */
    private static function row(string $slug): array|false
    {
        return self::dbal()->fetchAssociative('SELECT * FROM mcp_endpoint WHERE slug = :slug', ['slug' => $slug]);
    }

    private static function dbal(): Connection
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    private static function cleanUp(): void
    {
        self::dbal()->executeStatement('DELETE FROM mcp_endpoint WHERE slug LIKE :prefix', ['prefix' => self::PREFIX.'%']);
    }
}
