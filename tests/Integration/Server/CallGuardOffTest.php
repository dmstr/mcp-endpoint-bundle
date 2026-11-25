<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration\Server;

use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;
use Dmstr\McpEndpoint\Tests\Fixtures\ToolCalls;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Counter-proof to {@see McpEndpointServerTest::testUnlistedToolIsMethodNotFound()}:
 * with the bundle option `call_guard` off (kernel variant
 * {@see TestKernel::ENV_NO_CALL_GUARD}) the same call of a tool that is
 * not on the endpoint's allowlist reaches its provider, so the handler
 * decorator is what blocks it. The listing filter stays in place.
 * No database.
 */
final class CallGuardOffTest extends WebTestCase
{
    use McpRpcTrait;

    protected function setUp(): void
    {
        ToolCalls::reset();
        $this->client = static::createClient(['environment' => TestKernel::ENV_NO_CALL_GUARD]);
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

    public function testUnlistedToolRunsWithoutGuard(): void
    {
        $session = $this->initialize('docs');

        $response = $this->rpc('docs', 'tools/call', ['name' => TestTools::READ_TOOL, 'arguments' => []], session: $session);

        self::assertArrayNotHasKey('error', $response, json_encode($response, \JSON_THROW_ON_ERROR));
        self::assertFalse($response['result']['isError'] ?? false, json_encode($response, \JSON_THROW_ON_ERROR));
        self::assertSame(1, ToolCalls::count(TestTools::READ_TOOL));
    }

    public function testListingIsStillFiltered(): void
    {
        $names = $this->toolNames('docs');
        sort($names);

        self::assertSame([TestTools::SCOPED_TOOL, TestTools::STRUCTURED_TOOL], $names);
    }
}
