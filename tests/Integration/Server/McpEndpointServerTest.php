<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration\Server;

use Dmstr\McpEndpoint\Tests\Fixtures\InMemoryEndpointProvider;
use Dmstr\McpEndpoint\Tests\Fixtures\SwitchableEndpointPolicy;
use Dmstr\McpEndpoint\Tests\Fixtures\TestPermissions;
use Dmstr\McpEndpoint\Tests\Fixtures\Tool\TestTools;
use Dmstr\McpEndpoint\Tests\Fixtures\ToolCalls;
use Dmstr\McpEndpoint\Tests\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The MCP server per endpoint, `/mcp/{slug}`, in the test kernel: no
 * database. Enforcement by the bundle: controller, tool gate behind the
 * access checker and handler decorators, default-deny handler, slug-bound
 * session store.
 *
 * Endpoints come from {@see InMemoryEndpointProvider}: `docs` lists the
 * scoped and the structured test tool, `spy` the counting read tool, the
 * others serve the negative tests (disabled, required permission, write
 * tools, paging). The tool gate reads `allowWrite` from
 * {@see SwitchableEndpointPolicy}, aliased to the policy interface. The
 * test firewall authenticates as `test-admin` (ROLE_ADMIN) unless
 * `X-Test-Auth: anonymous` is sent; `X-Test-Roles` replaces the roles.
 * Sessions use the `file` store.
 */
final class McpEndpointServerTest extends WebTestCase
{
    use McpRpcTrait;

    protected function setUp(): void
    {
        ToolCalls::reset();
        SwitchableEndpointPolicy::$allowWrite = false;
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        SwitchableEndpointPolicy::$allowWrite = false;
        ToolCalls::reset();
        parent::tearDown();
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /** The handshake without an MCP-Protocol-Version header opens a session. */
    public function testInitializeOpensSession(): void
    {
        $response = $this->rpc('docs', 'initialize', $this->initializeParams());

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertNotSame('', $this->sessionId());
        self::assertSame(self::PROTOCOL, $response['result']['protocolVersion'] ?? null);
    }

    /**
     * The revision 2025-11-25: the SDK accepts it in the handshake, keeps the
     * session and serves the session's requests with the header.
     */
    public function testProtocolRevision20251125IsAccepted(): void
    {
        $params = ['protocolVersion' => '2025-11-25'] + $this->initializeParams();
        $response = $this->rpc('docs', 'initialize', $params);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('2025-11-25', $response['result']['protocolVersion'] ?? null);
        $session = $this->sessionId();
        self::assertNotSame('', $session, 'The handshake era keeps a session.');

        $tools = $this->rpc('docs', 'tools/list', [], session: $session, headers: ['HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertContains(TestTools::STRUCTURED_TOOL, array_map(static fn (array $tool): string => $tool['name'], $tools['result']['tools'] ?? []));
    }

    /** An invented session id is 404, never 400. */
    public function testUnknownSessionIsNotFound(): void
    {
        $this->rpc('docs', 'tools/list', [], session: 'f2b7c6a4-3c55-4c1e-9d1e-6f0e3f2b1a90');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /** A session belongs to the slug it was opened on. */
    public function testSessionOfAnotherSlugIsNotFound(): void
    {
        $session = $this->initialize('docs');

        $this->rpc('spy', 'tools/list', [], session: $session);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        $this->rpc('docs', 'tools/list', [], session: $session);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * DNS rebinding protection of mcp/sdk 0.9: an Origin header is checked
     * on its own, against `http.allowed_origins`, not against
     * `http.allowed_hosts`. A foreign origin is 403, a listed origin, the
     * server's own origin and a request without Origin pass.
     */
    public function testForeignOriginIsForbidden(): void
    {
        $session = $this->initialize('docs');

        $this->rpc('docs', 'tools/list', [], session: $session, headers: ['HTTP_ORIGIN' => 'https://evil.example']);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(TestTools::STRUCTURED_TOOL, (string) $this->client->getResponse()->getContent());

        // A listed host is not enough for the Origin.
        $this->rpc('docs', 'tools/list', [], session: $session, headers: ['HTTP_ORIGIN' => 'http://mcp.internal']);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());

        $this->rpc('docs', 'tools/list', [], session: $session, headers: ['HTTP_ORIGIN' => TestKernel::ALLOWED_ORIGINS[0]]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->rpc('docs', 'tools/list', [], session: $session, headers: ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->rpc('docs', 'tools/list', [], session: $session);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    /** A Host outside `http.allowed_hosts` is refused as well. */
    public function testForeignHostIsForbidden(): void
    {
        $this->rpc('docs', 'initialize', $this->initializeParams(), headers: ['HTTP_HOST' => 'evil.example']);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame('', $this->sessionId());
    }

    /** Without authentication 401, no tool list. */
    public function testUnauthenticatedIsUnauthorized(): void
    {
        $this->rpc('docs', 'tools/list', [], headers: self::anonymous());

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(TestTools::STRUCTURED_TOOL, (string) $this->client->getResponse()->getContent());

        $this->rpc('docs', 'initialize', $this->initializeParams(), headers: self::anonymous());
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertSame('', $this->sessionId());
    }

    /** The call guard blocks an unlisted tool before its provider runs. */
    public function testUnlistedToolIsMethodNotFound(): void
    {
        $session = $this->initialize('docs');

        $response = $this->rpc('docs', 'tools/call', ['name' => TestTools::READ_TOOL, 'arguments' => []], session: $session);

        self::assertSame(-32601, $response['error']['code'] ?? null, json_encode($response, \JSON_THROW_ON_ERROR));
        self::assertSame(0, ToolCalls::count(TestTools::READ_TOOL));
    }

    /** The guard lets a listed tool through. */
    public function testListedToolRuns(): void
    {
        $session = $this->initialize('spy');

        $response = $this->rpc('spy', 'tools/call', ['name' => TestTools::READ_TOOL, 'arguments' => []], session: $session);

        self::assertArrayNotHasKey('error', $response, json_encode($response, \JSON_THROW_ON_ERROR));
        self::assertFalse($response['result']['isError'] ?? false, json_encode($response, \JSON_THROW_ON_ERROR));
        self::assertSame(1, ToolCalls::count(TestTools::READ_TOOL));
    }

    /** A listed tool's structured content reaches the client. */
    public function testStructuredContentIsReturned(): void
    {
        $result = $this->callTool('docs', TestTools::STRUCTURED_TOOL);

        self::assertArrayNotHasKey('error', $result, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertFalse($result['isError'] ?? true);
        self::assertSame(TestTools::STRUCTURED_TOOL.' called', $result['result']['note'] ?? null);
        self::assertIsArray($result['structuredContent'] ?? null);
        self::assertSame(TestTools::STRUCTURED_TOOL.' called', $result['structuredContent']['note'] ?? null);
        self::assertSame(1, ToolCalls::count(TestTools::STRUCTURED_TOOL));
    }

    /** resources/read is rejected (endpoints publish no resources). */
    public function testResourceReadIsRejected(): void
    {
        $session = $this->initialize('docs');

        $response = $this->rpc('docs', 'resources/read', ['uri' => 'file:///etc/passwd'], session: $session);

        self::assertArrayHasKey('error', $response, json_encode($response, \JSON_THROW_ON_ERROR));
        self::assertSame(-32601, $response['error']['code'] ?? null, json_encode($response, \JSON_THROW_ON_ERROR));
    }

    /** The instructions come from the endpoint of the request. */
    public function testInstructionsComeFromEndpoint(): void
    {
        $docs = $this->rpc('docs', 'initialize', $this->initializeParams());
        $spy = $this->rpc('spy', 'initialize', $this->initializeParams());
        $plain = $this->rpc('restricted', 'initialize', $this->initializeParams());

        self::assertSame('Docs endpoint instructions.', $docs['result']['instructions'] ?? null);
        self::assertSame('Spy endpoint instructions.', $spy['result']['instructions'] ?? null);
        self::assertArrayNotHasKey('instructions', $plain['result'] ?? [], 'An empty description sends no instructions.');
    }

    /** tools/list of a slug shows exactly its allowlist. */
    public function testToolsListShowsOnlyAllowlist(): void
    {
        $docs = $this->toolNames('docs');
        sort($docs);

        self::assertSame([TestTools::SCOPED_TOOL, TestTools::STRUCTURED_TOOL], $docs);
        self::assertSame([TestTools::READ_TOOL], $this->toolNames('spy'));
    }

    public function testUnknownSlugIsNotFound(): void
    {
        $this->rpc('unknown', 'initialize', $this->initializeParams());

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testInvalidSlugIsNotFound(): void
    {
        $this->rpc('Docs', 'initialize', $this->initializeParams());

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testPathWithoutSlugIsNotFound(): void
    {
        $this->client->request('POST', '/mcp', server: $this->jsonServer(), content: '{}');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testDisabledEndpointIsNotFound(): void
    {
        $this->rpc('disabled', 'initialize', $this->initializeParams());

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        self::assertSame('', $this->sessionId());
    }

    /** requiredPermission is checked in the controller: 403 before the transport runs. */
    public function testMissingPermissionIsForbidden(): void
    {
        $userOnly = self::roles('ROLE_USER');

        $this->rpc('restricted', 'initialize', $this->initializeParams(), headers: $userOnly);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame('', $this->sessionId());

        $session = $this->initialize('restricted');
        $this->rpc('restricted', 'tools/list', [], session: $session, headers: $userOnly);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(TestTools::READ_TOOL, (string) $this->client->getResponse()->getContent());
    }

    /** A role the host application maps to the endpoint's permission suffices on its own. */
    public function testMappedRoleAloneIsGranted(): void
    {
        self::assertContains('ROLE_RESTRICTED', TestKernel::PERMISSIONS[TestPermissions::RESTRICTED]);

        self::assertSame([TestTools::READ_TOOL], $this->toolNames('restricted', self::roles('ROLE_RESTRICTED')));
        self::assertSame([TestTools::READ_TOOL], $this->toolNames('restricted'));
    }

    /** The default permission of an endpoint is decided by the voter too. */
    public function testDefaultPermissionFollowsTheRoleMap(): void
    {
        self::assertSame([TestTools::READ_TOOL], $this->toolNames('spy', self::roles('ROLE_USER')));

        $this->rpc('spy', 'initialize', $this->initializeParams(), headers: self::roles('ROLE_RESTRICTED'));
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    /** A read-only endpoint neither lists nor runs a write tool, even if its allowlist names one. */
    public function testReadOnlyEndpointRefusesWriteTool(): void
    {
        SwitchableEndpointPolicy::$allowWrite = true;

        self::assertSame([TestTools::READ_TOOL], $this->toolNames('read-only-write'));
        $this->assertCallRefused('read-only-write', TestTools::WRITE_TOOL);
    }

    /** readOnly: false is not enough while the policy forbids write tools (the kill switch). */
    public function testWriteToolNeedsThePolicy(): void
    {
        self::assertSame([TestTools::READ_TOOL], $this->toolNames('writer'));
        $this->assertCallRefused('writer', TestTools::WRITE_TOOL);
    }

    public function testWriteToolIsListedAndRunsWhenEndpointAndPolicyAllowIt(): void
    {
        SwitchableEndpointPolicy::$allowWrite = true;

        $names = $this->toolNames('writer');
        sort($names);
        self::assertSame([TestTools::READ_TOOL, TestTools::WRITE_TOOL], $names);

        $result = $this->callTool('writer', TestTools::WRITE_TOOL);
        self::assertArrayNotHasKey('error', $result, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertSame(1, ToolCalls::count(TestTools::WRITE_TOOL));
    }

    public function testPromptGetIsRejected(): void
    {
        $session = $this->initialize('docs');

        $response = $this->rpc('docs', 'prompts/get', ['name' => 'anything'], session: $session);

        self::assertSame(-32601, $response['error']['code'] ?? null, json_encode($response, \JSON_THROW_ON_ERROR));
    }

    public function testPromptAndTemplateListsAreEmpty(): void
    {
        $session = $this->initialize('docs');

        $prompts = $this->rpc('docs', 'prompts/list', [], session: $session);
        $templates = $this->rpc('docs', 'resources/templates/list', [], session: $session);

        self::assertSame([], $prompts['result']['prompts'] ?? null, json_encode($prompts, \JSON_THROW_ON_ERROR));
        self::assertSame([], $templates['result']['resourceTemplates'] ?? null, json_encode($templates, \JSON_THROW_ON_ERROR));
    }

    /** A name no tool has is "method not found" as well (the SDK's own CallToolHandler never runs). */
    public function testUnknownToolIsMethodNotFound(): void
    {
        $this->assertCallRefused('spy', 'no_such_tool');
    }

    private function assertCallRefused(string $slug, string $tool): void
    {
        $session = $this->initialize($slug);

        $response = $this->rpc($slug, 'tools/call', ['name' => $tool, 'arguments' => []], session: $session);

        self::assertSame(-32601, $response['error']['code'] ?? null, json_encode($response, \JSON_THROW_ON_ERROR));
        self::assertSame(0, ToolCalls::count($tool));
    }
}
