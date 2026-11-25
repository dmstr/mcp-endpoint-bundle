<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:41:25 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Session;

use Dmstr\McpEndpoint\Context\McpEndpointContext;
use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Binds MCP sessions to the endpoint they were opened on: a session id from
 * `/mcp/a` is unknown on `/mcp/b` (the SDK answers 404).
 *
 * Decorates `mcp.server.<server>.session.store`. The SDK's store interface
 * takes {@see Uuid} objects, so the slug cannot be prefixed to the key as a
 * string. Instead every id the SDK hands in is mapped to a name-based UUID
 * (version 5) of the slug and the id, under a fixed namespace of this
 * bundle; the inner store (PSR-16 cache, file, …) keeps its own key scheme
 * on top. The client keeps seeing the id the SDK generated.
 *
 * Without an endpoint in {@see McpEndpointContext} (no endpoint request)
 * the ids map with an empty slug, a namespace no endpoint can share, because
 * a slug is never empty.
 *
 * gc() delegates; the ids it reports are the mapped ones, which the SDK
 * only logs.
 *
 * Since mcp/sdk 0.9 the session saves only the keys it changed, onto what
 * the store holds at that point (read, then write, with the same id), and
 * exists() may throw `Mcp\Exception\SessionStoreException` for an
 * unreachable store, which the SDK answers with 503 instead of 404. Both
 * pass through this decorator unchanged: every call maps the id the same
 * way, and exceptions of the inner store are not caught.
 */
final readonly class EndpointSessionStore implements SessionStoreInterface
{
    /** UUID v5 namespace of the session keys (random, fixed for this bundle). */
    public const string KEY_NAMESPACE = '6f1c2d6e-8a4b-4c39-9b1e-2f7d0c5a9e41';

    public function __construct(
        private SessionStoreInterface $inner,
        private McpEndpointContext $context,
    ) {
    }

    public function exists(Uuid $id): bool
    {
        return $this->inner->exists($this->key($id));
    }

    public function read(Uuid $id): string|false
    {
        return $this->inner->read($this->key($id));
    }

    public function write(Uuid $id, string $data): bool
    {
        return $this->inner->write($this->key($id), $data);
    }

    public function destroy(Uuid $id): bool
    {
        return $this->inner->destroy($this->key($id));
    }

    public function gc(): array
    {
        return $this->inner->gc();
    }

    /**
     * The store key of session $id on the current endpoint.
     */
    public function key(Uuid $id): Uuid
    {
        $slug = $this->context->get()?->getSlug() ?? '';

        return Uuid::v5(Uuid::fromString(self::KEY_NAMESPACE), $slug."\n".$id->toRfc4122());
    }
}
