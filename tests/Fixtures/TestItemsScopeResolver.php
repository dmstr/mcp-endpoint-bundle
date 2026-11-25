<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Fixtures;

use Dmstr\McpEndpoint\Scope\ScopeResolverInterface;
use Dmstr\McpEndpoint\Scope\UnknownScopeReferenceException;

/**
 * Test-only scope resolver of the scope key `test_items`: the names in
 * {@see self::ITEMS} resolve to their ids, an id resolves to itself,
 * anything else is unknown. A test "deletes" an item by putting its name
 * into {@see self::$deleted}; tests that do so reset it.
 *
 * Tagged by autoconfiguration in the test kernel; the unit tests
 * instantiate it directly.
 */
final class TestItemsScopeResolver implements ScopeResolverInterface
{
    public const string KEY = 'test_items';

    /** Name => id. */
    public const array ITEMS = [
        'alpha' => '0199a1b2-0000-7000-8000-00000000000a',
        'beta' => '0199a1b2-0000-7000-8000-00000000000b',
    ];

    /** @var list<string> names of items that no longer exist */
    public static array $deleted = [];

    public function scopeKey(): string
    {
        return self::KEY;
    }

    public function resolve(array $refs): array
    {
        $items = array_diff_key(self::ITEMS, array_flip(self::$deleted));
        $ids = [];
        $unknown = [];
        foreach ($refs as $ref) {
            $id = $items[$ref] ?? (\in_array($ref, $items, true) ? $ref : null);
            if (null === $id) {
                $unknown[] = $ref;
                continue;
            }
            $ids[] = $id;
        }
        if ([] !== $unknown) {
            throw new UnknownScopeReferenceException($unknown);
        }

        return $ids;
    }
}
