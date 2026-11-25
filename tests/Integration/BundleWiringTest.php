<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration;

use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Dmstr\McpEndpoint\Policy\DefaultMcpEndpointPolicy;
use Dmstr\McpEndpoint\Tests\Fixtures\SwitchableEndpointPolicy;
use Dmstr\McpEndpoint\Tests\Fixtures\TestItemsScopeResolver;
use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Dmstr\McpEndpoint\Validator\ValidMcpEndpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Wiring of the bundle in the test kernel, without a database: the policy
 * alias of the application, the validator against the real API Platform
 * MCP tools ({@see TestTools}), the scope resolver tagged by
 * autoconfiguration, the admin routes, the seed command and every kernel
 * variant compiling.
 */
final class BundleWiringTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testThePolicyAliasOfTheApplicationIsUsed(): void
    {
        $policy = self::getContainer()->get('test.mcp_endpoint.policy');

        self::assertInstanceOf(SwitchableEndpointPolicy::class, $policy);
        self::assertNotInstanceOf(DefaultMcpEndpointPolicy::class, $policy);
    }

    public function testValidatorKnowsTheRegisteredMcpTools(): void
    {
        self::assertSame([], $this->violations([TestTools::READ_TOOL, TestTools::STRUCTURED_TOOL]));
        self::assertSame(['tools/0' => ValidMcpEndpoint::UNKNOWN_TOOL_ERROR], $this->violations(['no_such_tool']));
        self::assertSame(['tools/0' => ValidMcpEndpoint::WRITE_TOOL_ERROR], $this->violations([TestTools::WRITE_TOOL]));
        self::assertSame(['scope/test_items' => ValidMcpEndpoint::SCOPE_REQUIRED_ERROR], $this->violations([TestTools::SCOPED_TOOL]));
    }

    public function testAutoconfiguredScopeResolverResolvesReferences(): void
    {
        $endpoint = self::endpoint([TestTools::SCOPED_TOOL])->setScope([TestItemsScopeResolver::KEY => ['alpha']]);

        self::assertCount(0, $this->validator()->validate($endpoint, new ValidMcpEndpoint()));
        self::assertSame([TestItemsScopeResolver::ITEMS['alpha']], $endpoint->getScopeIds(TestItemsScopeResolver::KEY));
    }

    public function testRoutesExist(): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $paths = array_map(static fn ($route): string => $route->getPath(), iterator_to_array($router->getRouteCollection()));

        self::assertContains('/api/admin/mcp_endpoints.{_format}', $paths);
        self::assertContains('/api/admin/mcp_endpoints/{id}.{_format}', $paths);
        self::assertContains('/mcp/{slug}', $paths);
        self::assertNotContains('/mcp/_server', $paths, 'The route of symfony/mcp-bundle is not imported.');
    }

    public function testSeedCommandIsRegistered(): void
    {
        $application = new Application(self::bootKernel());

        self::assertTrue($application->has('mcp-endpoint:seed'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function environments(): iterable
    {
        yield TestKernel::ENV_WITH_VOTER => [TestKernel::ENV_WITH_VOTER];
        yield TestKernel::ENV_NO_VOTER => [TestKernel::ENV_NO_VOTER];
        yield TestKernel::ENV_NO_CALL_GUARD => [TestKernel::ENV_NO_CALL_GUARD];
        yield TestKernel::ENV_PAGING => [TestKernel::ENV_PAGING];
    }

    #[DataProvider('environments')]
    public function testEveryKernelVariantCompiles(string $environment): void
    {
        $kernel = self::bootKernel(['environment' => $environment]);

        self::assertSame($environment, $kernel->getEnvironment());
        self::assertSame(TestKernel::ENV_NO_CALL_GUARD !== $environment, self::getContainer()->getParameter('dmstr_mcp_endpoint.call_guard'));
    }

    /**
     * @param list<string> $tools
     */
    private static function endpoint(array $tools): McpEndpoint
    {
        return (new McpEndpoint())->setSlug('wiring')->setName('Wiring')->setTools($tools);
    }

    private function validator(): ValidatorInterface
    {
        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');

        return $validator;
    }

    /**
     * @param list<string> $tools
     *
     * @return array<string, string|null>
     */
    private function violations(array $tools): array
    {
        $result = [];
        foreach ($this->validator()->validate(self::endpoint($tools), new ValidMcpEndpoint()) as $violation) {
            $result[$violation->getPropertyPath()] = $violation->getCode();
        }

        return $result;
    }
}
