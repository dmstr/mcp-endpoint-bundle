<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration\Server;

use Dmstr\McpEndpoint\Tests\Fixtures\InMemoryEndpointProvider;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API Platform's SecureRegistry filters after paging: with a page size of
 * 1 the cursor walks the whole registry, pages may be empty, and every tool
 * of the endpoint shows up exactly once. MCP clients cannot choose a page
 * size (tools/list has only `cursor`), so the kernel variant
 * {@see TestKernel::ENV_PAGING} sets `pagination_limit` 1. No database.
 */
final class ToolsListPagingTest extends WebTestCase
{
    use McpRpcTrait;

    protected function setUp(): void
    {
        $this->client = static::createClient(['environment' => TestKernel::ENV_PAGING]);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testToolsListPagingReturnsEveryToolOnce(): void
    {
        $session = $this->initialize('paging');
        $names = [];
        $cursor = null;
        $pages = 0;
        do {
            $response = $this->rpc('paging', 'tools/list', null === $cursor ? [] : ['cursor' => $cursor], session: $session);
            self::assertSame(200, $this->client->getResponse()->getStatusCode());
            self::assertArrayHasKey('result', $response, json_encode($response, \JSON_THROW_ON_ERROR));
            foreach ($response['result']['tools'] ?? [] as $tool) {
                $names[] = $tool['name'];
            }
            $cursor = $response['result']['nextCursor'] ?? null;
            ++$pages;
        } while (null !== $cursor && $pages < 100);

        $expected = InMemoryEndpointProvider::ENDPOINTS['paging']['tools'] ?? [];
        sort($expected);
        sort($names);

        self::assertGreaterThan(\count($expected), $pages, 'The registry holds more tools than the endpoint, so a page size of 1 needs several pages.');
        self::assertSame($expected, $names);
    }
}
