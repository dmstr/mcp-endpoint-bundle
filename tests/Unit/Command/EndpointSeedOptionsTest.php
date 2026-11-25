<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Unit\Command;

use Dmstr\McpEndpoint\Command\EndpointSeedCommand;
use Dmstr\McpEndpoint\Entity\McpEndpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Kernel- and database-free test of the option parsing and the comparable
 * definition of `mcp-endpoint:seed`.
 */
final class EndpointSeedOptionsTest extends TestCase
{
    public function testCsvIsTrimmedDeduplicatedAndSkipsEmptyEntries(): void
    {
        self::assertSame(['search_documents', 'get_document'], EndpointSeedCommand::parseCsv(' search_documents, ,get_document,search_documents,'));
        self::assertSame([], EndpointSeedCommand::parseCsv(null));
        self::assertSame([], EndpointSeedCommand::parseCsv(''));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function booleans(): iterable
    {
        yield 'true' => ['true', true];
        yield 'TRUE' => ['TRUE', true];
        yield '1' => ['1', true];
        yield 'yes' => ['yes', true];
        yield 'false' => ['false', false];
        yield '0' => ['0', false];
        yield 'off' => ['off', false];
        yield 'bool' => [false, false];
    }

    #[DataProvider('booleans')]
    public function testBooleanValues(mixed $value, bool $expected): void
    {
        self::assertSame($expected, EndpointSeedCommand::parseBool($value, 'read-only'));
    }

    public function testInvalidBooleanIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--read-only must be true or false.');

        EndpointSeedCommand::parseBool('maybe', 'read-only');
    }

    public function testScopeCollectionsShortcut(): void
    {
        self::assertSame(['collections' => ['product-docs', 'handbook']], EndpointSeedCommand::parseScope([], 'product-docs, handbook'));
    }

    public function testRepeatableScopeIsMergedWithTheShortcut(): void
    {
        self::assertSame(
            ['collections' => ['a', 'b', 'c'], 'test_items' => ['alpha']],
            EndpointSeedCommand::parseScope(['collections:a,b', 'test_items:alpha', 'collections:b'], 'c'),
        );
    }

    public function testNoScopeOptionsGiveAnEmptyScope(): void
    {
        self::assertSame([], EndpointSeedCommand::parseScope([], null));
    }

    public function testScopeWithoutKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EndpointSeedCommand::parseScope(['a,b'], null);
    }

    public function testRetrievalOnlyContainsGivenOptions(): void
    {
        self::assertSame([], EndpointSeedCommand::parseRetrieval(null, null, null));
        self::assertSame(['maxK' => 20, 'hybrid' => true, 'hybridWeight' => 0.25], EndpointSeedCommand::parseRetrieval('20', 'true', '0.25'));
    }

    public function testRetrievalValuesOfTheWrongTypeAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--max-k must be an integer.');

        EndpointSeedCommand::parseRetrieval('ten', null, null);
    }

    public function testDefaultDescriptionCarriesThePromptInjectionHint(): void
    {
        $description = \sprintf(EndpointSeedCommand::DEFAULT_DESCRIPTION, 'docs');

        self::assertStringContainsString('"docs"', $description);
        self::assertStringContainsString('untrusted data', $description);
        self::assertStringContainsString('prompt injection', $description);
    }

    public function testDefinitionIgnoresKeyOrder(): void
    {
        $a = (new McpEndpoint())->setRetrieval(['maxK' => 5, 'hybrid' => true])->setScope(['x' => ['1'], 'collections' => ['2']]);
        $b = (new McpEndpoint())->setRetrieval(['hybrid' => true, 'maxK' => 5])->setScope(['collections' => ['2'], 'x' => ['1']]);

        self::assertSame(EndpointSeedCommand::definition($a), EndpointSeedCommand::definition($b));
    }

    public function testDefinitionKeepsListOrder(): void
    {
        $a = (new McpEndpoint())->setTools(['a', 'b']);
        $b = (new McpEndpoint())->setTools(['b', 'a']);

        self::assertNotSame(EndpointSeedCommand::definition($a), EndpointSeedCommand::definition($b));
    }
}
