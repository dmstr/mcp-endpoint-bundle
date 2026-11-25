<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Class constraint of {@see \Dmstr\McpEndpoint\Entity\McpEndpoint}: tool
 * names, read-only rule, scope rule and resolution, retrieval structure.
 * See {@see ValidMcpEndpointValidator}.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ValidMcpEndpoint extends Constraint
{
    public const string UNKNOWN_TOOL_ERROR = '5d0c1f3e-8a4b-4e57-9c1d-2b7e6f0a9d31';
    public const string DUPLICATE_TOOL_ERROR = '7a2e4c90-1b3d-4f65-8e7a-9c0d1e2f3a42';
    public const string WRITE_TOOL_ERROR = '9e1b3d5f-7a2c-4e8b-a1d3-5f7b9c2e4a53';
    public const string SCOPE_REQUIRED_ERROR = 'b3d5f7a9-2c4e-4a6b-8d0f-1e3a5c7b9d64';
    public const string UNKNOWN_SCOPE_KEY_ERROR = 'c4e6a8b0-3d5f-4b7c-9e1a-2f4b6d8c0e75';
    public const string UNKNOWN_SCOPE_REFERENCE_ERROR = 'd5f7b9c1-4e6a-4c8d-a0b2-3a5c7e9d1f86';
    public const string INVALID_STRUCTURE_ERROR = 'e6a8c0d2-5f7b-4d9e-b1c3-4b6d8f0e2a97';

    protected const array ERROR_NAMES = [
        self::UNKNOWN_TOOL_ERROR => 'UNKNOWN_TOOL_ERROR',
        self::DUPLICATE_TOOL_ERROR => 'DUPLICATE_TOOL_ERROR',
        self::WRITE_TOOL_ERROR => 'WRITE_TOOL_ERROR',
        self::SCOPE_REQUIRED_ERROR => 'SCOPE_REQUIRED_ERROR',
        self::UNKNOWN_SCOPE_KEY_ERROR => 'UNKNOWN_SCOPE_KEY_ERROR',
        self::UNKNOWN_SCOPE_REFERENCE_ERROR => 'UNKNOWN_SCOPE_REFERENCE_ERROR',
        self::INVALID_STRUCTURE_ERROR => 'INVALID_STRUCTURE_ERROR',
    ];

    public string $unknownToolMessage = 'Unknown MCP tool "{{ tool }}".';
    public string $invalidToolMessage = 'A tool name must be a non-empty string.';
    public string $duplicateToolMessage = 'The tool "{{ tool }}" is listed more than once.';
    public string $writeToolMessage = 'The tool "{{ tool }}" is not read-only (readOnlyHint is not true); it cannot be listed while readOnly is true.';
    public string $scopeRequiredMessage = 'The tool "{{ tool }}" needs a non-empty scope "{{ key }}".';
    public string $unknownScopeKeyMessage = 'No resolver handles the scope key "{{ key }}"; it cannot hold references.';
    public string $unknownScopeReferenceMessage = 'Unknown or ambiguous reference "{{ reference }}" in scope "{{ key }}".';
    public string $invalidStructureMessage = 'Invalid value: {{ error }}';

    /**
     * @param string[]|null $groups
     */
    #[HasNamedArguments]
    public function __construct(?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
