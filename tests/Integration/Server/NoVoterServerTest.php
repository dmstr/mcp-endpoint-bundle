<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration\Server;

use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;
use Dmstr\McpEndpoint\Tests\Fixtures\ToolCalls;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Dmstr\McpEndpoint\Tests\Kernel\TestPermissionVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Without a voter for the endpoint permissions (kernel variant
 * {@see TestKernel::ENV_NO_VOTER}) every voter abstains on
 * `mcpendpoint:endpoint:use` and on the application's own permissions, the
 * access decision manager denies, and the controller answers 403 before the
 * MCP transport runs: no session, no tool list, no tool call. This holds
 * for an authenticated user with any role, ROLE_ADMIN included.
 * Unauthenticated requests still get 401 from the firewall. No database.
 */
final class NoVoterServerTest extends WebTestCase
{
    use McpRpcTrait;

    protected function setUp(): void
    {
        ToolCalls::reset();
        $this->client = static::createClient(['environment' => TestKernel::ENV_NO_VOTER]);
    }

    protected function tearDown(): void
    {
        ToolCalls::reset();
        parent::tearDown();
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testNoCustomVoterIsRegistered(): void
    {
        self::assertFalse(static::getContainer()->has(TestPermissionVoter::class));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function slugs(): iterable
    {
        yield 'default permission' => ['spy'];
        yield 'application permission' => ['restricted'];
    }

    #[DataProvider('slugs')]
    public function testEveryEndpointIsForbidden(string $slug): void
    {
        $this->rpc($slug, 'initialize', $this->initializeParams());
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame('', $this->sessionId());

        $this->rpc($slug, 'initialize', $this->initializeParams(), headers: self::roles('ROLE_ADMIN, ROLE_USER, ROLE_RESTRICTED'));
        self::assertSame(403, $this->client->getResponse()->getStatusCode());

        $this->rpc($slug, 'tools/call', ['name' => TestTools::READ_TOOL, 'arguments' => []]);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, ToolCalls::count(TestTools::READ_TOOL));
    }

    public function testUnauthenticatedIsUnauthorized(): void
    {
        $this->rpc('spy', 'initialize', $this->initializeParams(), headers: self::anonymous());

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnknownSlugIsStillNotFound(): void
    {
        $this->rpc('unknown', 'initialize', $this->initializeParams());

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }
}
