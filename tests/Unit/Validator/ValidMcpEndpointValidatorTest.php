<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Unit\Validator;

use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operation\Factory\OperationMetadataFactoryInterface;
use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Dmstr\McpEndpoint\Scope\ScopeResolverRegistry;
use Dmstr\McpEndpoint\Tests\Fixtures\TestItemsScopeResolver;
use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;
use Dmstr\McpEndpoint\Tool\ToolInspector;
use Dmstr\McpEndpoint\Validator\RegisteredPermission;
use Dmstr\McpEndpoint\Validator\RegisteredPermissionValidator;
use Dmstr\McpEndpoint\Validator\ValidMcpEndpoint;
use Dmstr\McpEndpoint\Validator\ValidMcpEndpointValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Mcp\Schema\ToolAnnotations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Kernel- and database-free test of the McpEndpoint validation: the real
 * validator with a fake MCP operation factory (tools named like those of
 * {@see TestTools}, plus a plain read tool, a tool that needs the scope
 * `collections`, which has no resolver here, and a tool without annotations)
 * and the test-only scope resolver of `test_items`. Property constraints
 * (slug, permission) are checked
 * through validateProperty(), which leaves out the UniqueEntity class
 * constraint and with it the database.
 */
final class ValidMcpEndpointValidatorTest extends TestCase
{
    private const string READ_TOOL = 'plain_read_tool';
    private const string COLLECTIONS_TOOL = 'collections_tool';
    private const string THROWING_TOOL = 'throwing_tool';

    /** An id of the stored scope whose target was deleted (resolves to nothing). */
    private const string DELETED_ID = '0199a1b2-0000-7000-8000-0000000000de';

    private ValidatorInterface $validator;

    private OperationMetadataFactoryInterface $operations;

    protected function setUp(): void
    {
        $tools = [
            self::READ_TOOL => new McpTool(name: self::READ_TOOL, annotations: new ToolAnnotations(readOnlyHint: true)),
            self::COLLECTIONS_TOOL => new McpTool(name: self::COLLECTIONS_TOOL, annotations: ['readOnlyHint' => true], meta: ['de.dmstr/scope' => 'collections']),
            TestTools::SCOPED_TOOL => new McpTool(name: TestTools::SCOPED_TOOL, annotations: ['readOnlyHint' => true], meta: ['de.dmstr/scope' => TestItemsScopeResolver::KEY]),
            TestTools::WRITE_TOOL => new McpTool(name: TestTools::WRITE_TOOL, annotations: ['readOnlyHint' => false]),
            'no_hint_tool' => new McpTool(name: 'no_hint_tool'),
        ];

        $factory = new class($tools) implements OperationMetadataFactoryInterface {
            /** @param array<string, McpTool> $tools */
            public function __construct(private readonly array $tools)
            {
            }

            public function create(string $uriTemplate, array $context = []): ?Operation
            {
                if (ValidMcpEndpointValidatorTest::throwingTool() === $uriTemplate) {
                    throw new \RuntimeException('metadata failure');
                }

                return $this->tools[$uriTemplate] ?? null;
            }
        };

        $this->operations = $factory;
        $this->validator = $this->validatorWith(null);
    }

    public static function throwingTool(): string
    {
        return self::THROWING_TOOL;
    }

    public function testValidEndpointHasNoViolations(): void
    {
        $endpoint = self::endpoint([self::READ_TOOL]);

        self::assertSame([], $this->violations($endpoint));
    }

    public function testUnknownToolIsReportedAtItsIndex(): void
    {
        $endpoint = self::endpoint([self::READ_TOOL, 'no_such_tool']);

        self::assertSame(['tools/1' => ValidMcpEndpoint::UNKNOWN_TOOL_ERROR], $this->violations($endpoint));
    }

    public function testFailingToolLookupCountsAsUnknown(): void
    {
        self::assertSame(['tools/0' => ValidMcpEndpoint::UNKNOWN_TOOL_ERROR], $this->violations(self::endpoint([self::THROWING_TOOL])));
    }

    public function testEmptyAndNonStringToolNamesAreRejected(): void
    {
        $endpoint = self::endpoint(['', 42]);

        self::assertSame([
            'tools/0' => ValidMcpEndpoint::UNKNOWN_TOOL_ERROR,
            'tools/1' => ValidMcpEndpoint::UNKNOWN_TOOL_ERROR,
        ], $this->violations($endpoint));
    }

    public function testDuplicateToolIsRejected(): void
    {
        self::assertSame(['tools/1' => ValidMcpEndpoint::DUPLICATE_TOOL_ERROR], $this->violations(self::endpoint([self::READ_TOOL, self::READ_TOOL])));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writeTools(): iterable
    {
        yield 'readOnlyHint false' => [TestTools::WRITE_TOOL];
        yield 'no annotations' => ['no_hint_tool'];
    }

    #[DataProvider('writeTools')]
    public function testWriteToolIsRejectedOnReadOnlyEndpoint(string $tool): void
    {
        self::assertSame(['tools/0' => ValidMcpEndpoint::WRITE_TOOL_ERROR], $this->violations(self::endpoint([$tool])));
    }

    #[DataProvider('writeTools')]
    public function testWriteToolIsAcceptedWhenNotReadOnly(string $tool): void
    {
        self::assertSame([], $this->violations(self::endpoint([$tool])->setReadOnly(false)));
    }

    public function testScopeRequiredToolWithEmptyScopeIsRejected(): void
    {
        self::assertSame(['scope/collections' => ValidMcpEndpoint::SCOPE_REQUIRED_ERROR], $this->violations(self::endpoint([self::COLLECTIONS_TOOL])));
        self::assertSame(['scope/collections' => ValidMcpEndpoint::SCOPE_REQUIRED_ERROR], $this->violations(self::endpoint([self::COLLECTIONS_TOOL], ['collections' => []])));
    }

    public function testScopeKeyWithoutResolverRejectsReferences(): void
    {
        $endpoint = self::endpoint([self::COLLECTIONS_TOOL], ['collections' => ['handbook']]);

        self::assertSame(['scope/collections' => ValidMcpEndpoint::UNKNOWN_SCOPE_KEY_ERROR], $this->violations($endpoint));
        self::assertSame(['collections' => ['handbook']], $endpoint->getScope());
    }

    public function testScopeReferencesAreStoredAsResolvedIds(): void
    {
        $endpoint = self::endpoint([TestTools::SCOPED_TOOL], ['test_items' => ['alpha', TestItemsScopeResolver::ITEMS['beta'], 'alpha']]);

        self::assertSame([], $this->violations($endpoint));
        self::assertSame(['test_items' => [TestItemsScopeResolver::ITEMS['alpha'], TestItemsScopeResolver::ITEMS['beta']]], $endpoint->getScope());

        // Idempotent: the ids resolve to themselves.
        self::assertSame([], $this->violations($endpoint));
        self::assertSame(['test_items' => [TestItemsScopeResolver::ITEMS['alpha'], TestItemsScopeResolver::ITEMS['beta']]], $endpoint->getScope());
    }

    public function testUnknownScopeReferenceIsReportedAtItsIndexAndNothingIsResolved(): void
    {
        $endpoint = self::endpoint([TestTools::SCOPED_TOOL], ['test_items' => ['alpha', 'gamma']]);

        self::assertSame(['scope/test_items/1' => ValidMcpEndpoint::UNKNOWN_SCOPE_REFERENCE_ERROR], $this->violations($endpoint));
        self::assertSame(['test_items' => ['alpha', 'gamma']], $endpoint->getScope());
    }

    /**
     * An id of the stored scope whose target was deleted is dropped
     * silently; the rest is resolved as usual.
     */
    public function testADeletedIdOfTheStoredScopeIsDropped(): void
    {
        $alpha = TestItemsScopeResolver::ITEMS['alpha'];
        $endpoint = self::endpoint([TestTools::SCOPED_TOOL], ['test_items' => [self::DELETED_ID, 'beta']]);

        self::assertSame([], $this->violations($endpoint, ['test_items' => [$alpha, strtoupper(self::DELETED_ID)]]));
        self::assertSame(['test_items' => [TestItemsScopeResolver::ITEMS['beta']]], $endpoint->getScope());
    }

    public function testANewUnknownIdNextToADeletedOneIsStillRejected(): void
    {
        $endpoint = self::endpoint([TestTools::SCOPED_TOOL], ['test_items' => [self::DELETED_ID, 'gamma', 'alpha']]);

        self::assertSame(['scope/test_items/1' => ValidMcpEndpoint::UNKNOWN_SCOPE_REFERENCE_ERROR], $this->violations($endpoint, ['test_items' => [self::DELETED_ID]]));
        self::assertSame(['test_items' => [self::DELETED_ID, 'gamma', 'alpha']], $endpoint->getScope());
    }

    public function testAnUnknownIdOfANewEndpointIsRejected(): void
    {
        $endpoint = self::endpoint([TestTools::SCOPED_TOOL], ['test_items' => [self::DELETED_ID, 'alpha']]);

        self::assertSame(['scope/test_items/0' => ValidMcpEndpoint::UNKNOWN_SCOPE_REFERENCE_ERROR], $this->violations($endpoint, null));
    }

    /**
     * Dropping the last ids of a scope a tool needs: still the
     * scope-required violation, naming the deleted ids.
     */
    public function testDroppingTheLastIdOfARequiredScopeNamesIt(): void
    {
        $endpoint = self::endpoint([TestTools::SCOPED_TOOL], ['test_items' => [self::DELETED_ID]]);

        $violations = $this->validatorWith(['test_items' => [self::DELETED_ID]])->validate($endpoint, new ValidMcpEndpoint());

        self::assertCount(1, $violations);
        self::assertSame('scope/test_items', $violations[0]->getPropertyPath());
        self::assertSame(ValidMcpEndpoint::SCOPE_REQUIRED_ERROR, $violations[0]->getCode());
        self::assertSame(\sprintf('The tool "%s" needs a non-empty scope "test_items"; its last reference(s) "%s" no longer exist (deleted). Add another one.', TestTools::SCOPED_TOOL, self::DELETED_ID), $violations[0]->getMessage());
    }

    public function testDroppingTheLastIdOfAScopeNoToolNeedsPasses(): void
    {
        $endpoint = self::endpoint([self::READ_TOOL], ['test_items' => [self::DELETED_ID]]);

        self::assertSame([], $this->violations($endpoint, ['test_items' => [self::DELETED_ID]]));
        self::assertSame(['test_items' => []], $endpoint->getScope());
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidScopes(): iterable
    {
        yield 'list instead of object' => [['alpha'], 'scope'];
        yield 'string instead of list' => [['test_items' => 'alpha'], 'scope/test_items'];
        yield 'empty reference' => [['test_items' => ['']], 'scope/test_items/0'];
        yield 'invalid key' => [['Test Items' => ['alpha']], 'scope'];
    }

    /**
     * @param array<mixed> $scope
     */
    #[DataProvider('invalidScopes')]
    public function testScopeStructureIsChecked(array $scope, string $path): void
    {
        $violations = $this->violations(self::endpoint([self::READ_TOOL], $scope));

        self::assertArrayHasKey($path, $violations);
        self::assertSame(ValidMcpEndpoint::INVALID_STRUCTURE_ERROR, $violations[$path]);
    }

    /**
     * @return iterable<string, array{array<mixed>, string|null}>
     */
    public static function retrievals(): iterable
    {
        yield 'empty' => [[], null];
        yield 'complete' => [['maxK' => 50, 'hybrid' => true, 'hybridWeight' => 0.3], null];
        yield 'weight as integer' => [['hybridWeight' => 1], null];
        yield 'maxK above 50' => [['maxK' => 51], 'retrieval/maxK'];
        yield 'maxK zero' => [['maxK' => 0], 'retrieval/maxK'];
        yield 'maxK string' => [['maxK' => '10'], 'retrieval/maxK'];
        yield 'weight above 1' => [['hybridWeight' => 1.5], 'retrieval/hybridWeight'];
        yield 'hybrid string' => [['hybrid' => 'yes'], 'retrieval/hybrid'];
        yield 'unknown key' => [['topK' => 5], 'retrieval'];
    }

    /**
     * @param array<mixed> $retrieval
     */
    #[DataProvider('retrievals')]
    public function testRetrievalStructureIsChecked(array $retrieval, ?string $path): void
    {
        $violations = $this->violations(self::endpoint([self::READ_TOOL])->setRetrieval($retrieval));

        if (null === $path) {
            self::assertSame([], $violations);

            return;
        }
        self::assertArrayHasKey($path, $violations);
        self::assertSame(ValidMcpEndpoint::INVALID_STRUCTURE_ERROR, $violations[$path]);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function slugs(): iterable
    {
        yield 'lowercase with dash' => ['docs-base-2', true];
        yield 'space and uppercase' => ['Foo Bar', false];
        yield 'underscore' => ['foo_bar', false];
        yield 'empty' => ['', false];
        yield 'too long' => [str_repeat('a', McpEndpoint::MAX_SLUG_LENGTH + 1), false];
    }

    #[DataProvider('slugs')]
    public function testSlugPattern(string $slug, bool $valid): void
    {
        $violations = $this->validator->validateProperty(self::endpoint([])->setSlug($slug), 'slug');

        self::assertSame($valid, 0 === \count($violations));
    }

    public function testRequiredPermissionPattern(): void
    {
        foreach (['mcpendpoint:endpoint:use', 'testapp:endpoint:use_reports', 'reports:read'] as $valid) {
            self::assertCount(0, $this->validator->validateProperty(self::endpoint([])->setRequiredPermission($valid), 'requiredPermission'), $valid);
        }
        foreach (['ROLE_REPORTS_ADMIN', 'ROLE_USER', 'admin', 'Testapp:endpoint:use', 'one', 'a:b:c:d', 'a:b-c'] as $invalid) {
            self::assertGreaterThan(0, \count($this->validator->validateProperty(self::endpoint([])->setRequiredPermission($invalid), 'requiredPermission')), $invalid);
        }
    }

    /** When the host application publishes the registered permissions (the parameter exists), only those pass. */
    public function testRequiredPermissionMustBeRegistered(): void
    {
        $validator = $this->validatorWith(null, ['mcpendpoint:endpoint:use', 'testapp:endpoint:use_reports']);

        $accepted = $validator->validateProperty(self::endpoint([])->setRequiredPermission('testapp:endpoint:use_reports'), 'requiredPermission');
        $refused = $validator->validateProperty(self::endpoint([])->setRequiredPermission('testapp:endpoint:use_unknown'), 'requiredPermission');

        self::assertCount(0, $accepted);
        self::assertCount(1, $refused);
        self::assertSame(RegisteredPermission::NOT_REGISTERED_ERROR, $refused[0]->getCode());
    }

    /** Without a published list (no parameter) the format check alone applies. */
    public function testRegistrationIsNotCheckedWithoutTheParameter(): void
    {
        $endpoint = self::endpoint([])->setRequiredPermission('anything:goes:here');

        self::assertCount(0, $this->validator->validateProperty($endpoint, 'requiredPermission'));
    }

    /** A role name fails the format check, also when it happens to be listed. */
    public function testRoleNameIsRefusedEvenIfListed(): void
    {
        $validator = $this->validatorWith(null, ['ROLE_ADMIN']);

        self::assertGreaterThan(0, \count($validator->validateProperty(self::endpoint([])->setRequiredPermission('ROLE_ADMIN'), 'requiredPermission')));
    }

    public function testDefaults(): void
    {
        $endpoint = new McpEndpoint();

        self::assertTrue($endpoint->isEnabled());
        self::assertTrue($endpoint->isReadOnly());
        self::assertSame('mcpendpoint:endpoint:use', $endpoint->getRequiredPermission());
        self::assertSame([], $endpoint->getTools());
        self::assertSame([], $endpoint->getScope());
        self::assertSame([], $endpoint->getRetrieval());
    }

    /**
     * @param array<mixed> $tools
     * @param array<mixed> $scope
     */
    private static function endpoint(array $tools, array $scope = []): McpEndpoint
    {
        return (new McpEndpoint())
            ->setSlug('test-endpoint')
            ->setName('Test endpoint')
            ->setTools($tools)
            ->setScope($scope);
    }

    /**
     * The validator; with $storedScope an entity manager that manages every
     * endpoint and reports $storedScope as its stored `scope`.
     *
     * @param array<string, list<string>>|null $storedScope
     */
    private function validatorWith(?array $storedScope, ?array $registeredPermissions = null): ValidatorInterface
    {
        $entityManager = null;
        if (null !== $storedScope) {
            $unitOfWork = $this->createStub(UnitOfWork::class);
            $unitOfWork->method('getOriginalEntityData')->willReturn(['scope' => $storedScope]);
            $entityManager = $this->createStub(EntityManagerInterface::class);
            $entityManager->method('contains')->willReturn(true);
            $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        }

        $endpointValidator = new ValidMcpEndpointValidator(
            new ToolInspector($this->operations),
            new ScopeResolverRegistry([new TestItemsScopeResolver()]),
            $entityManager,
        );

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ValidMcpEndpointValidator::class => $endpointValidator,
                RegisteredPermissionValidator::class => new RegisteredPermissionValidator(new ParameterBag(null === $registeredPermissions ? [] : [RegisteredPermissionValidator::PARAMETER => $registeredPermissions])),
            ]))
            ->getValidator();
    }

    /**
     * Violations of the class constraint only, as path => code; with
     * $storedScope as the stored scope of a managed endpoint.
     *
     * @param array<string, list<string>>|false|null $storedScope false: the default validator (no entity manager)
     *
     * @return array<string, string|null>
     */
    private function violations(McpEndpoint $endpoint, array|false|null $storedScope = false): array
    {
        $validator = false === $storedScope ? $this->validator : $this->validatorWith($storedScope);
        $result = [];
        foreach ($validator->validate($endpoint, new ValidMcpEndpoint()) as $violation) {
            $result[$violation->getPropertyPath()] = $violation->getCode();
        }

        return $result;
    }
}
