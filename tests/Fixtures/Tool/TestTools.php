<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Fixtures\Tool;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use Dmstr\McpEndpoint\Tests\Fixtures\TestItemsScopeResolver;
use Dmstr\McpEndpoint\Tests\Fixtures\ToolCalls;
use Dmstr\McpEndpoint\Tool\ToolInspector;

/**
 * Test-only API Platform MCP tools, mapped in the test kernel through the
 * mapping path `tests/Fixtures/Tool`:
 *
 *  - {@see self::READ_TOOL}: read-only;
 *  - {@see self::WRITE_TOOL}: `readOnlyHint: false`;
 *  - {@see self::SCOPED_TOOL}: read-only, needs the scope `test_items`
 *    (`meta['de.dmstr/scope']`, resolved by {@see TestItemsScopeResolver});
 *  - {@see self::STRUCTURED_TOOL}: read-only, answers with structured
 *    content.
 *
 * No HTTP operation and no database: the providers are static callables
 * that count their runs in {@see ToolCalls}, so a test can prove that a
 * refused call never reached a provider.
 */
#[ApiResource(
    operations: [],
    mcp: [
        self::READ_TOOL => new McpTool(
            name: self::READ_TOOL,
            description: 'Test-only read tool that counts its calls.',
            annotations: ['readOnlyHint' => true],
            provider: [self::class, 'provide'],
        ),
        self::WRITE_TOOL => new McpTool(
            name: self::WRITE_TOOL,
            description: 'Test-only tool that changes data.',
            annotations: ['readOnlyHint' => false],
            provider: [self::class, 'provide'],
        ),
        self::SCOPED_TOOL => new McpTool(
            name: self::SCOPED_TOOL,
            description: 'Test-only read tool that needs the scope test_items.',
            annotations: ['readOnlyHint' => true],
            meta: [ToolInspector::SCOPE_META_KEY => TestItemsScopeResolver::KEY],
            provider: [self::class, 'provide'],
        ),
        self::STRUCTURED_TOOL => new McpTool(
            name: self::STRUCTURED_TOOL,
            description: 'Test-only read tool that answers with structured content.',
            structuredContent: true,
            annotations: ['readOnlyHint' => true],
            provider: [self::class, 'provide'],
        ),
    ],
)]
final class TestTools
{
    public const string READ_TOOL = 'test_read_tool';
    public const string WRITE_TOOL = 'test_write_tool';
    public const string SCOPED_TOOL = 'test_scoped_tool';
    public const string STRUCTURED_TOOL = 'test_structured_tool';

    public string $note = '';

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public static function provide(Operation $operation, array $uriVariables = [], array $context = []): self
    {
        $name = (string) $operation->getName();
        ToolCalls::record($name);

        $result = new self();
        $result->note = $name.' called';

        return $result;
    }
}
