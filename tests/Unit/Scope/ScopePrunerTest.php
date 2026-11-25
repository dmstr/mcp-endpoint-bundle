<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Unit\Scope;

use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Dmstr\McpEndpoint\Scope\ScopePruner;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * {@see ScopePruner} over stubbed endpoints, kernel-free: the id leaves
 * `scope.collections` of every endpoint (case-insensitively), other keys
 * and ids stay, an emptied key stays as an empty list, and the entity
 * manager is flushed once, only when something changed. Against the
 * database, with the audit log: {@see \Dmstr\McpEndpoint\Tests\Integration\DeletedScopeTargetTest}.
 */
final class ScopePrunerTest extends TestCase
{
    private const string DELETED = '0199c0de-0000-7000-8000-0000000000a1';
    private const string KEPT = '0199c0de-0000-7000-8000-0000000000a2';

    private int $flushes = 0;

    public function testTheIdLeavesTheScopeOfEveryEndpoint(): void
    {
        $both = self::endpoint('both', ['collections' => [self::KEPT, strtoupper(self::DELETED)], 'test_items' => [self::DELETED]]);
        $only = self::endpoint('only', ['collections' => [self::DELETED]]);
        $other = self::endpoint('other', ['collections' => [self::KEPT]]);
        $none = self::endpoint('none', []);

        $changed = $this->pruner([$both, $only, $other, $none])->removeCollection(self::DELETED);

        self::assertSame(2, $changed);
        self::assertSame(['collections' => [self::KEPT], 'test_items' => [self::DELETED]], $both->getScope());
        self::assertSame(['collections' => []], $only->getScope());
        self::assertSame(['collections' => [self::KEPT]], $other->getScope());
        self::assertSame([], $none->getScope());
        self::assertSame(1, $this->flushes);
    }

    public function testNothingToPruneDoesNotFlush(): void
    {
        $endpoint = self::endpoint('other', ['collections' => [self::KEPT]]);

        self::assertSame(0, $this->pruner([$endpoint])->removeCollection(self::DELETED));
        self::assertSame(0, $this->flushes);
    }

    public function testAnyScopeKey(): void
    {
        $endpoint = self::endpoint('items', ['test_items' => [self::DELETED, self::KEPT]]);

        self::assertSame(1, $this->pruner([$endpoint])->removeId('test_items', self::DELETED));
        self::assertSame(['test_items' => [self::KEPT]], $endpoint->getScope());
    }

    /**
     * @param list<McpEndpoint> $endpoints
     */
    private function pruner(array $endpoints): ScopePruner
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findAll')->willReturn($endpoints);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushes;
        });

        return new ScopePruner($entityManager);
    }

    /**
     * @param array<string, list<string>> $scope
     */
    private static function endpoint(string $slug, array $scope): McpEndpoint
    {
        return (new McpEndpoint())->setSlug($slug)->setName(ucfirst($slug))->setScope($scope);
    }
}
