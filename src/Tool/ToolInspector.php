<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tool;

use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation\Factory\OperationMetadataFactoryInterface;
use Mcp\Schema\ToolAnnotations;

/**
 * The API Platform MCP tools as an endpoint sees them: lookup by name, the
 * read-only hint and the scope key.
 *
 * Tools are `McpTool` operations, found through API Platform's MCP
 * operation factory (`api_platform.mcp.metadata.operation.mcp_factory`),
 * the same lookup API Platform's MCP handler uses for `tools/call`.
 *
 * A tool declares the scope it needs in its MCP `meta`:
 * `meta: ['de.dmstr/scope' => 'collections']`. An endpoint that allows such
 * a tool must carry a non-empty `scope.collections`.
 */
final readonly class ToolInspector
{
    /** Key of the scope declaration in the `meta` of an McpTool. */
    public const string SCOPE_META_KEY = 'de.dmstr/scope';

    public function __construct(
        private OperationMetadataFactoryInterface $operationMetadataFactory,
    ) {
    }

    /**
     * The MCP tool named $name; null when there is none (an MCP resource of
     * that name is no tool) or the lookup fails.
     */
    public function find(string $name): ?McpTool
    {
        if ('' === $name) {
            return null;
        }

        try {
            $operation = $this->operationMetadataFactory->create($name);
        } catch (\Throwable) {
            return null;
        }

        return $operation instanceof McpTool ? $operation : null;
    }

    /**
     * Whether the tool declares `readOnlyHint: true` in its annotations
     * (array or {@see ToolAnnotations}). A missing hint counts as writing:
     * the MCP default of `readOnlyHint` is false.
     */
    public static function isReadOnly(McpTool $tool): bool
    {
        $annotations = $tool->getAnnotations();

        if ($annotations instanceof ToolAnnotations) {
            return true === $annotations->readOnlyHint;
        }
        if (\is_array($annotations)) {
            return true === ($annotations['readOnlyHint'] ?? null);
        }
        if (\is_object($annotations) && property_exists($annotations, 'readOnlyHint')) {
            return true === $annotations->readOnlyHint;
        }

        return false;
    }

    /**
     * The scope key the tool requires (`meta['de.dmstr/scope']`), null when
     * it requires none.
     */
    public static function scopeKey(McpTool $tool): ?string
    {
        $key = $tool->getMeta()[self::SCOPE_META_KEY] ?? null;

        return \is_string($key) && '' !== $key ? $key : null;
    }
}
