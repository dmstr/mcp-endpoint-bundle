<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use Dmstr\McpEndpoint\OpenApi\OpenApiTag;
use Dmstr\McpEndpoint\Security\McpEndpointPermissions;
use Dmstr\McpEndpoint\Validator\RegisteredPermission;
use Dmstr\McpEndpoint\Validator\ValidMcpEndpoint;
use Dmstr\OpenApiJsonSchema\Attribute\JsonSchema;
use Dmstr\OpenApiJsonSchema\Interface\JsonSchemaProviderInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An MCP endpoint profile: the URL `/mcp/{slug}` and the slice of the API
 * Platform MCP tools it publishes.
 *
 * - tools: the allowlist, names of API Platform `McpTool` operations; every
 *   name must exist (422 at `tools/<i>`).
 * - readOnly: only tools with `readOnlyHint: true` may be listed (422
 *   otherwise); write tools additionally need the policy's `allowWrite()`
 *   at runtime.
 * - requiredPermission: the permission a caller needs for this endpoint
 *   (checked per request, before the MCP transport; format and registration
 *   are validated on save).
 * - scope: per scope key the ids the tools may see, e.g.
 *   `{"collections": ["<uuid>"]}`; references are resolved to ids on save
 *   through the tagged scope resolvers (`Entity/McpEndpoint/scope.json`).
 *   A tool with `meta['de.dmstr/scope'] = <key>` requires a non-empty
 *   `scope.<key>`. No foreign key: a deleted target leaves a dead id.
 * - retrieval: `maxK` (1 to 50), `hybrid`, `hybridWeight` (0 to 1); unset
 *   keys take the policy defaults (`Entity/McpEndpoint/retrieval.json`).
 * - description: becomes the MCP `instructions` of the `initialize` answer.
 *
 * All operations require the permission `mcpendpoint:endpoint:manage`. Every configuration field is
 * audited (Gedmo loggable).
 */
#[ORM\Entity]
#[ORM\Table(name: 'mcp_endpoint')]
#[ORM\UniqueConstraint(name: 'uniq_mcp_endpoint_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
#[UniqueEntity(fields: ['slug'], message: 'Another MCP endpoint already uses this slug.')]
#[ValidMcpEndpoint]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(status: 201),
        new Patch(),
        new Delete(),
    ],
    routePrefix: '/admin',
    description: <<<'MD'
        An MCP endpoint publishes a selection of the MCP tools of this application under its own URL `/mcp/<slug>`, for MCP clients such as Claude Code or the application's own agents.

        Each endpoint lists the tools it allows, whether only read-only tools may be used, the permission a caller needs and the scope its tools see (for example the document collections a search may use). Its description is sent to the client as instructions when the client connects. A disabled endpoint answers 404.

        Related: the tools are the MCP tools of this API.
        MD,
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
    security: "is_granted('".McpEndpointPermissions::ENDPOINT_MANAGE."')",
    order: ['slug' => 'ASC'],
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    openapi: new OpenApiOperation(tags: [OpenApiTag::MCP_ENDPOINTS]),
)]
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['enabled'])]
class McpEndpoint implements JsonSchemaProviderInterface
{
    public const string GROUP_READ = 'mcp_endpoint:read';
    public const string GROUP_WRITE = 'mcp_endpoint:write';

    public const int MAX_SLUG_LENGTH = 100;
    public const int MAX_NAME_LENGTH = 190;
    public const int MAX_PERMISSION_LENGTH = 100;
    public const string SLUG_PATTERN = '/^[a-z0-9-]+$/';
    /**
     * Format of a permission name, the same pattern the host application's
     * permission voter uses. Kept here so that the bundle works without one;
     * whether the permission is registered is checked by
     * {@see RegisteredPermission} when the host application publishes the
     * registered permissions. The pattern excludes role names (uppercase).
     */
    public const string PERMISSION_PATTERN = '/^[a-z][a-z0-9]*(?::[a-z][a-z0-9_]*){1,2}\z/';
    public const string DEFAULT_PERMISSION = McpEndpointPermissions::ENDPOINT_USE;

    public const string SCOPE_SCHEMA_FILE = __DIR__.'/McpEndpoint/scope.json';
    public const string RETRIEVAL_SCHEMA_FILE = __DIR__.'/McpEndpoint/retrieval.json';

    /** @var array<string, array<string, mixed>> decoded schemas by field, read once */
    private static array $schemas = [];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ApiProperty(identifier: true, writable: false)]
    #[Groups([self::GROUP_READ])]
    private Uuid $id;

    /** URL segment of the endpoint: `/mcp/<slug>`. */
    #[ORM\Column(type: Types::STRING, length: self::MAX_SLUG_LENGTH)]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::MAX_SLUG_LENGTH)]
    #[Assert\Regex(pattern: self::SLUG_PATTERN, message: 'The slug may only contain lowercase letters, digits and dashes.')]
    #[ApiProperty(description: 'URL segment of the endpoint, `/mcp/<slug>`: lowercase letters, digits and dashes; unique.')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private string $slug = '';

    #[ORM\Column(type: Types::STRING, length: self::MAX_NAME_LENGTH)]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::MAX_NAME_LENGTH)]
    #[ApiProperty(description: 'Display name of the endpoint.')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private string $name = '';

    /** Sent to MCP clients as `instructions` of the `initialize` answer. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\Length(max: 4000)]
    #[ApiProperty(description: 'Sent to MCP clients as instructions when they connect: what the endpoint offers and how to use it.', schema: ['type' => 'string', 'x-format' => 'textarea'])]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private string $description = '';

    /** A disabled endpoint answers 404 like an unknown one. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[ApiProperty(description: 'A disabled endpoint answers 404 like an unknown one.')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private bool $enabled = true;

    /**
     * Allowlist: names of API Platform MCP tools.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[ApiProperty(description: 'Names of the MCP tools this endpoint offers; every name must be an MCP tool of this API.', schema: ['type' => 'array', 'items' => ['type' => 'string', 'minLength' => 1]])]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private array $tools = [];

    /** Only tools with `readOnlyHint: true` may be listed. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[ApiProperty(description: 'Only read-only tools (annotation `readOnlyHint: true`) may be listed. Write tools additionally need the endpoint policy to allow write tools.')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private bool $readOnly = true;

    #[ORM\Column(type: Types::STRING, length: self::MAX_PERMISSION_LENGTH, options: ['default' => self::DEFAULT_PERMISSION])]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::MAX_PERMISSION_LENGTH)]
    #[Assert\Regex(pattern: self::PERMISSION_PATTERN, message: 'The permission must look like prefix:resource:action (lowercase letters, digits and underscores, two or three parts); a role name is not a permission.')]
    #[RegisteredPermission]
    #[ApiProperty(description: 'Permission a caller needs for this endpoint, e.g. mcpendpoint:endpoint:use; it must be registered.')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private string $requiredPermission = self::DEFAULT_PERMISSION;

    /**
     * Scope ids per scope key (shape of `Entity/McpEndpoint/scope.json`).
     *
     * @var array<string, list<string>>
     */
    #[ORM\Column(type: Types::JSON)]
    #[JsonSchema]
    #[ApiProperty(description: 'What the tools see, per scope key, e.g. {"collections": ["<name or uuid>"]}; references are stored as resolved ids (schema `Entity/McpEndpoint/scope.json`).')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private array $scope = [];

    /**
     * Retrieval limits (shape of `Entity/McpEndpoint/retrieval.json`).
     *
     * @var array{maxK?: int, hybrid?: bool, hybridWeight?: float|int}
     */
    #[ORM\Column(type: Types::JSON)]
    #[JsonSchema]
    #[ApiProperty(description: 'Retrieval limits of the search tools: `maxK` (1 to 50), `hybrid`, `hybridWeight` (0 to 1); unset keys take the policy defaults (schema `Entity/McpEndpoint/retrieval.json`).')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Gedmo\Versioned]
    private array $retrieval = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Schemas of `scope` and `retrieval` for the OpenAPI description
     * ({@see \Dmstr\OpenApiJsonSchema\OpenApi\JsonFieldSchemaDecorator}) and
     * the structure check of the validator.
     *
     * @return array<string, mixed>|null
     */
    public static function getJsonSchema(string $fieldName): ?array
    {
        $file = match ($fieldName) {
            'scope' => self::SCOPE_SCHEMA_FILE,
            'retrieval' => self::RETRIEVAL_SCHEMA_FILE,
            default => null,
        };
        if (null === $file) {
            return null;
        }

        /** @var array<string, mixed> $schema */
        $schema = self::$schemas[$fieldName] ??= json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);

        return $schema;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description ?? '';

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Stored as sent; the validator reports non-string entries.
     *
     * @param array<mixed> $tools
     */
    public function setTools(array $tools): self
    {
        /** @var list<string> $list */
        $list = array_values($tools);
        $this->tools = $list;

        return $this;
    }

    /** Whether $toolName is on the allowlist. */
    public function allowsTool(string $toolName): bool
    {
        return \in_array($toolName, $this->tools, true);
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function setReadOnly(bool $readOnly): self
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    public function getRequiredPermission(): string
    {
        return $this->requiredPermission;
    }

    public function setRequiredPermission(string $requiredPermission): self
    {
        $this->requiredPermission = $requiredPermission;

        return $this;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getScope(): array
    {
        return $this->scope;
    }

    /**
     * The scope ids of one key; empty when the key is not set.
     *
     * @return list<string>
     */
    public function getScopeIds(string $scopeKey): array
    {
        $ids = $this->scope[$scopeKey] ?? [];

        return \is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
    }

    /**
     * Stored as sent; the validator checks the shape and replaces the
     * references with the resolved ids.
     *
     * @param array<mixed> $scope
     */
    public function setScope(array $scope): self
    {
        /** @var array<string, list<string>> $scope */
        $this->scope = $scope;

        return $this;
    }

    /**
     * @return array{maxK?: int, hybrid?: bool, hybridWeight?: float|int}
     */
    public function getRetrieval(): array
    {
        return $this->retrieval;
    }

    /**
     * @param array<mixed> $retrieval
     */
    public function setRetrieval(array $retrieval): self
    {
        /** @var array{maxK?: int, hybrid?: bool, hybridWeight?: float|int} $retrieval */
        $this->retrieval = $retrieval;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
