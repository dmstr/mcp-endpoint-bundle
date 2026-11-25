<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Fixtures;

/**
 * Runs of the providers of {@see Tool\TestTools}, by tool name. Kept outside
 * the tool class, whose public properties are its serialized output.
 * Static, because the test client reboots the kernel between requests;
 * tests reset it in setUp() and tearDown().
 */
final class ToolCalls
{
    /** @var array<string, int> */
    private static array $calls = [];

    public static function record(string $tool): void
    {
        self::$calls[$tool] = self::count($tool) + 1;
    }

    public static function count(string $tool): int
    {
        return self::$calls[$tool] ?? 0;
    }

    public static function reset(): void
    {
        self::$calls = [];
    }
}
