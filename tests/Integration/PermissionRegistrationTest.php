<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration;

use Dmstr\McpEndpoint\Security\McpEndpointPermissions;
use Dmstr\McpEndpoint\Tests\Fixtures\TestPermissions;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Dmstr\McpEndpoint\Tests\Kernel\TestPermissionVoter;
use Dmstr\McpEndpoint\Validator\RegisteredPermissionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The permission contract: the bundle registers `mcpendpoint:endpoint:manage`
 * and `mcpendpoint:endpoint:use` at the `za7_core.permission` tag, the host
 * application collects the tags into `za7_core.permissions.registered`
 * (which {@see RegisteredPermissionValidator} reads) and its voter decides
 * with the map `za7_core.permissions`. Without any voter every voter
 * abstains and access is denied. Boots the kernel but touches no database.
 */
final class PermissionRegistrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheContractNamesAreUnchanged(): void
    {
        self::assertSame('za7_core.permission', TestKernel::PERMISSION_TAG);
        self::assertSame('za7_core.permissions', TestKernel::PERMISSION_MAP_PARAMETER);
        self::assertSame('za7_core.permissions.registered', TestKernel::PERMISSIONS_REGISTERED_PARAMETER);
        self::assertSame(TestKernel::PERMISSIONS_REGISTERED_PARAMETER, RegisteredPermissionValidator::PARAMETER);
        self::assertSame('mcpendpoint:endpoint:manage', McpEndpointPermissions::ENDPOINT_MANAGE);
        self::assertSame('mcpendpoint:endpoint:use', McpEndpointPermissions::ENDPOINT_USE);
    }

    public function testPermissionsAreRegisteredAtTheTag(): void
    {
        self::bootKernel();
        $registered = static::getContainer()->getParameter(TestKernel::PERMISSIONS_REGISTERED_PARAMETER);

        self::assertContains(McpEndpointPermissions::ENDPOINT_MANAGE, $registered);
        self::assertContains(McpEndpointPermissions::ENDPOINT_USE, $registered);
        self::assertContains(TestPermissions::RESTRICTED, $registered);
        self::assertSame(TestKernel::PERMISSIONS, static::getContainer()->getParameter(TestKernel::PERMISSION_MAP_PARAMETER));
    }

    public function testTheTestVoterIsRegistered(): void
    {
        self::bootKernel();

        self::assertTrue(static::getContainer()->has(TestPermissionVoter::class));
    }

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function decisions(): iterable
    {
        yield 'manage, mapped role' => [McpEndpointPermissions::ENDPOINT_MANAGE, ['ROLE_ADMIN'], true];
        yield 'manage, mapped role among others' => [McpEndpointPermissions::ENDPOINT_MANAGE, ['ROLE_USER', 'ROLE_ENDPOINT_ADMIN'], true];
        yield 'manage, unmapped role' => [McpEndpointPermissions::ENDPOINT_MANAGE, ['ROLE_USER'], false];
        yield 'use, mapped role' => [McpEndpointPermissions::ENDPOINT_USE, ['ROLE_USER'], true];
        yield 'use, no role' => [McpEndpointPermissions::ENDPOINT_USE, [], false];
        yield 'application permission' => [TestPermissions::RESTRICTED, ['ROLE_RESTRICTED'], true];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('decisions')]
    public function testDecision(string $permission, array $roles, bool $expected): void
    {
        self::assertSame($expected, self::isGranted(TestKernel::ENV_WITH_VOTER, $permission, $roles));
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('decisions')]
    public function testEverythingIsDeniedWithoutAVoter(string $permission, array $roles, bool $grantedWithVoter): void
    {
        unset($grantedWithVoter);

        self::assertFalse(self::isGranted(TestKernel::ENV_NO_VOTER, $permission, [...$roles, 'ROLE_ADMIN']));
    }

    /**
     * @param list<string> $roles
     */
    private static function isGranted(string $environment, string $permission, array $roles): bool
    {
        self::bootKernel(['environment' => $environment]);
        $container = static::getContainer();
        $container->get(TokenStorageInterface::class)->setToken(
            new UsernamePasswordToken(new InMemoryUser('alice', null, $roles), 'main', $roles),
        );

        return $container->get(AuthorizationCheckerInterface::class)->isGranted($permission);
    }
}
