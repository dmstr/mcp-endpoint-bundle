<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:03:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Tests\Integration\Server;

use Dmstr\McpEndpoint\Tests\Kernel\TestHeaderAuthenticator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * JSON-RPC helpers of the MCP endpoint tests (/mcp/{slug}) on the test
 * client: handshake, one request per call, tool names of tools/list.
 *
 * The using test assigns `$this->client` (static::createClient()) in
 * setUp(). Headers are sent with every request of a helper, because the
 * test firewall authenticates each request on its own (`X-Test-Roles`,
 * `X-Test-Auth`, see {@see TestHeaderAuthenticator}).
 */
trait McpRpcTrait
{
    private const string PROTOCOL = '2024-11-05';

    private KernelBrowser $client;

    /**
     * Opens a session (initialize plus notifications/initialized).
     *
     * @param array<string, string> $headers server keys (`HTTP_...`)
     */
    private function initialize(string $slug, array $headers = []): string
    {
        $this->rpc($slug, 'initialize', $this->initializeParams(), headers: $headers);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $session = $this->sessionId();

        $this->client->request('POST', '/mcp/' . $slug, server: $this->jsonServer(['HTTP_MCP_SESSION_ID' => $session, ...$headers]), content: json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], \JSON_THROW_ON_ERROR));
        self::assertSame(202, $this->client->getResponse()->getStatusCode());

        return $session;
    }

    /**
     * Names of the tools in tools/list of a fresh session (first page).
     *
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private function toolNames(string $slug, array $headers = []): array
    {
        $session = $this->initialize($slug, $headers);
        $response = $this->rpc($slug, 'tools/list', [], session: $session, headers: $headers);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return array_map(static fn (array $tool): string => $tool['name'], $response['result']['tools'] ?? []);
    }

    /**
     * Calls a tool in a fresh session. A JSON-RPC error comes back as
     * `['error' => ['code' => ..., 'message' => ...]]`; a result as
     * `['result' => <decoded text content>, 'isError' => bool,
     * 'structuredContent' => <structured content or null>]`, the text
     * content being the serialized tool output.
     *
     * @param array<string, mixed>  $arguments
     * @param array<string, string> $headers
     *
     * @return array{error?: array<string, mixed>, result?: array<string, mixed>, isError?: bool, structuredContent?: mixed}
     */
    private function callTool(string $slug, string $tool, array $arguments = [], array $headers = []): array
    {
        $session = $this->initialize($slug, $headers);
        $response = $this->rpc($slug, 'tools/call', ['name' => $tool, 'arguments' => [] === $arguments ? new \stdClass() : $arguments], session: $session, headers: $headers);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }

        $text = $response['result']['content'][0]['text'] ?? null;
        self::assertIsString($text, json_encode($response, \JSON_THROW_ON_ERROR));
        $decoded = json_decode($text, true);

        return [
            'result' => \is_array($decoded) ? $decoded : ['text' => $text],
            'isError' => (bool) ($response['result']['isError'] ?? false),
            'structuredContent' => $response['result']['structuredContent'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initializeParams(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL,
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'mcp-endpoint-test', 'version' => '1.0.0'],
        ];
    }

    /**
     * Sends one JSON-RPC request and decodes the answer (JSON or one SSE
     * `data:` line); an empty or non-JSON body decodes to [].
     *
     * @param array<string, mixed>  $params
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    private function rpc(string $slug, string $method, array $params, ?string $session = null, array $headers = []): array
    {
        if (null !== $session) {
            $headers['HTTP_MCP_SESSION_ID'] = $session;
        }

        $this->client->request('POST', '/mcp/' . $slug, server: $this->jsonServer($headers), content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => [] === $params ? new \stdClass() : $params,
        ], \JSON_THROW_ON_ERROR));

        $content = (string) $this->client->getResponse()->getContent();
        if (preg_match('/^data: (.+)$/m', $content, $match)) {
            $content = $match[1];
        }

        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private function jsonServer(array $headers = []): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            ...$headers,
        ];
    }

    private function sessionId(): string
    {
        return (string) $this->client->getResponse()->headers->get('Mcp-Session-Id', '');
    }

    /**
     * Server key of a request header for the test client (`HTTP_` prefix
     * added by the caller).
     */
    private static function serverKey(string $header): string
    {
        return strtoupper(str_replace('-', '_', $header));
    }

    /**
     * Server keys that replace the roles of the test user.
     *
     * @return array<string, string>
     */
    private static function roles(string $roles): array
    {
        return ['HTTP_'.self::serverKey(TestHeaderAuthenticator::HEADER_ROLES) => $roles];
    }

    /**
     * Server keys of an unauthenticated request.
     *
     * @return array<string, string>
     */
    private static function anonymous(): array
    {
        return ['HTTP_'.self::serverKey(TestHeaderAuthenticator::HEADER_AUTH) => TestHeaderAuthenticator::ANONYMOUS];
    }
}
