<?php

// file generated with AI assistance: Claude Code - 2026-10-08 22:21:34 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Validator;

use ApiPlatform\Metadata\McpTool;
use Dmstr\McpEndpoint\Entity\McpEndpoint;
use Dmstr\McpEndpoint\Scope\ScopeResolverRegistry;
use Dmstr\McpEndpoint\Scope\UnknownScopeReferenceException;
use Dmstr\McpEndpoint\Tool\ToolInspector;
use Doctrine\ORM\EntityManagerInterface;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates {@see ValidMcpEndpoint}:
 *
 *  1. `tools`: every entry is a non-empty string, listed once, and the name
 *     of an API Platform MCP tool ({@see ToolInspector::find()}); with
 *     `readOnly` true every tool must declare `readOnlyHint: true`.
 *     Violations at `tools/<i>`.
 *  2. `retrieval` and `scope` match `Entity/McpEndpoint/*.json` (opis,
 *     draft 2020-12); violations at `retrieval/<key>`, `scope/<key>/<i>`.
 *  3. Every non-empty `scope.<key>` is resolved by the scope resolver of
 *     that key; a key without resolver is a violation at `scope/<key>`, an
 *     unknown reference one at `scope/<key>/<i>`. When everything resolves,
 *     the scope of the endpoint is REPLACED by the resolved ids (duplicates
 *     removed), so the ids are what is stored. This is the only side effect
 *     of the validation; it is idempotent (ids resolve to themselves).
 *     A reference that no longer resolves but is an id of the STORED scope
 *     (the original data of the unit of work: its target was deleted after
 *     the endpoint was saved) is dropped silently; a new unknown
 *     reference stays a violation. Without an entity manager (or for a new
 *     endpoint) nothing is stored, so nothing is dropped.
 *  4. Every allowed tool that declares a scope key
 *     (`meta['de.dmstr/scope']`) needs a non-empty `scope.<key>`: violation
 *     at `scope/<key>`. When the scope became empty only because dropped ids
 *     were its last ones, the violation names them
 *     ({@see SCOPE_DELETED_MESSAGE}), so the caller sees why.
 *
 * Paths are built with `/` like the other JSON fields of the API (the
 * pointer of the offending value), not through `atPath()`, which would join
 * them with dots.
 */
final class ValidMcpEndpointValidator extends ConstraintValidator
{
    /**
     * Message of the scope-required violation when the scope lost its last
     * ids because their targets were deleted (code
     * {@see ValidMcpEndpoint::SCOPE_REQUIRED_ERROR}).
     */
    public const string SCOPE_DELETED_MESSAGE = 'The tool "{{ tool }}" needs a non-empty scope "{{ key }}"; its last reference(s) {{ references }} no longer exist (deleted). Add another one.';

    public function __construct(
        private readonly ToolInspector $tools,
        private readonly ScopeResolverRegistry $scopeResolvers,
        // Reads the stored scope; optional for kernel-free use.
        private readonly ?EntityManagerInterface $entityManager = null,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidMcpEndpoint) {
            throw new UnexpectedTypeException($constraint, ValidMcpEndpoint::class);
        }
        if (null === $value) {
            return;
        }
        if (!$value instanceof McpEndpoint) {
            throw new UnexpectedValueException($value, McpEndpoint::class);
        }

        $allowed = $this->validateTools($value, $constraint);

        foreach (self::schemaErrors('retrieval', $value->getRetrieval()) as $pointer => $error) {
            $this->violation($constraint->invalidStructureMessage, ['{{ error }}' => $error], 'retrieval'.$pointer, $value->getRetrieval(), ValidMcpEndpoint::INVALID_STRUCTURE_ERROR, $constraint);
        }

        $scopeErrors = self::schemaErrors('scope', $value->getScope());
        foreach ($scopeErrors as $pointer => $error) {
            $this->violation($constraint->invalidStructureMessage, ['{{ error }}' => $error], 'scope'.$pointer, $value->getScope(), ValidMcpEndpoint::INVALID_STRUCTURE_ERROR, $constraint);
        }
        $dropped = [];
        if ([] === $scopeErrors) {
            $dropped = $this->resolveScope($value, $constraint);
        }

        $reported = [];
        foreach ($allowed as $name => $tool) {
            $key = ToolInspector::scopeKey($tool);
            if (null === $key || isset($reported[$key])) {
                continue;
            }
            $ids = $value->getScope()[$key] ?? [];
            if (!\is_array($ids) || [] === $ids) {
                $reported[$key] = true;
                if ([] !== ($dropped[$key] ?? [])) {
                    $references = implode(', ', array_map(static fn (string $id): string => '"'.$id.'"', $dropped[$key]));
                    $this->violation(self::SCOPE_DELETED_MESSAGE, ['{{ tool }}' => $name, '{{ key }}' => $key, '{{ references }}' => $references], 'scope/'.$key, $ids, ValidMcpEndpoint::SCOPE_REQUIRED_ERROR, $constraint);
                    continue;
                }
                $this->violation($constraint->scopeRequiredMessage, ['{{ tool }}' => $name, '{{ key }}' => $key], 'scope/'.$key, $ids, ValidMcpEndpoint::SCOPE_REQUIRED_ERROR, $constraint);
            }
        }
    }

    /**
     * Checks the allowlist; returns the tools that exist, by name.
     *
     * @return array<string, McpTool>
     */
    private function validateTools(McpEndpoint $endpoint, ValidMcpEndpoint $constraint): array
    {
        $allowed = [];
        foreach ($endpoint->getTools() as $index => $name) {
            $path = 'tools/'.$index;
            if (!\is_string($name) || '' === $name) {
                $this->violation($constraint->invalidToolMessage, [], $path, $name, ValidMcpEndpoint::UNKNOWN_TOOL_ERROR, $constraint);
                continue;
            }
            if (isset($allowed[$name])) {
                $this->violation($constraint->duplicateToolMessage, ['{{ tool }}' => $name], $path, $name, ValidMcpEndpoint::DUPLICATE_TOOL_ERROR, $constraint);
                continue;
            }

            $tool = $this->tools->find($name);
            if (null === $tool) {
                $this->violation($constraint->unknownToolMessage, ['{{ tool }}' => $name], $path, $name, ValidMcpEndpoint::UNKNOWN_TOOL_ERROR, $constraint);
                continue;
            }
            if ($endpoint->isReadOnly() && !ToolInspector::isReadOnly($tool)) {
                $this->violation($constraint->writeToolMessage, ['{{ tool }}' => $name], $path, $name, ValidMcpEndpoint::WRITE_TOOL_ERROR, $constraint);
            }

            $allowed[$name] = $tool;
        }

        return $allowed;
    }

    /**
     * Resolves every non-empty scope key; replaces the scope with the ids
     * when all of them resolve. Expects a structurally valid scope. Ids of
     * the stored scope that no longer resolve are dropped; returns them per
     * key.
     *
     * @return array<string, list<string>> dropped ids by scope key
     */
    private function resolveScope(McpEndpoint $endpoint, ValidMcpEndpoint $constraint): array
    {
        $scope = $endpoint->getScope();
        $resolved = [];
        $dropped = [];
        $failed = false;

        foreach ($scope as $key => $refs) {
            $key = (string) $key;
            /** @var list<string> $refs */
            $refs = array_values($refs);
            if ([] === $refs) {
                $resolved[$key] = [];
                continue;
            }

            $resolver = $this->scopeResolvers->get($key);
            if (null === $resolver) {
                $failed = true;
                $this->violation($constraint->unknownScopeKeyMessage, ['{{ key }}' => $key], 'scope/'.$key, $refs, ValidMcpEndpoint::UNKNOWN_SCOPE_KEY_ERROR, $constraint);
                continue;
            }

            try {
                $resolved[$key] = array_values(array_unique($resolver->resolve($refs)));
                continue;
            } catch (UnknownScopeReferenceException $e) {
                $unknown = $e;
            }

            // Ids of the stored scope whose target was deleted: dropped,
            // the rest is resolved again. Only when every unknown reference
            // is such an id; new unknown references are reported below,
            // the dead ids next to them are not.
            $dead = $this->deadStoredIds($endpoint, $key, $unknown->references);
            $new = array_values(array_diff($unknown->references, $dead));
            if ([] !== $dead && [] === $new) {
                $remaining = array_values(array_filter($refs, static fn (string $ref): bool => !\in_array($ref, $dead, true)));
                try {
                    $resolved[$key] = [] === $remaining ? [] : array_values(array_unique($resolver->resolve($remaining)));
                    $dropped[$key] = $dead;
                    continue;
                } catch (UnknownScopeReferenceException $e) {
                    $unknown = $e;
                    $new = $e->references;
                }
            }

            $failed = true;
            $reported = false;
            foreach ($refs as $index => $ref) {
                if (\in_array($ref, $new, true)) {
                    $reported = true;
                    $this->violation($constraint->unknownScopeReferenceMessage, ['{{ reference }}' => $ref, '{{ key }}' => $key], 'scope/'.$key.'/'.$index, $ref, ValidMcpEndpoint::UNKNOWN_SCOPE_REFERENCE_ERROR, $constraint);
                }
            }
            if (!$reported) {
                $this->violation($constraint->unknownScopeReferenceMessage, ['{{ reference }}' => implode(', ', [] !== $new ? $new : $unknown->references), '{{ key }}' => $key], 'scope/'.$key, $refs, ValidMcpEndpoint::UNKNOWN_SCOPE_REFERENCE_ERROR, $constraint);
            }
        }

        if (!$failed && $resolved !== $scope) {
            $endpoint->setScope($resolved);
        }

        return $failed ? [] : $dropped;
    }

    /**
     * The references of $unknown that are ids of the stored `scope.<$key>`
     * of $endpoint (original data of the unit of work); empty for an
     * endpoint that is not managed (new) or without an entity manager.
     *
     * @param list<string> $unknown
     *
     * @return list<string>
     */
    private function deadStoredIds(McpEndpoint $endpoint, string $key, array $unknown): array
    {
        if (null === $this->entityManager || !$this->entityManager->contains($endpoint)) {
            return [];
        }

        $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($endpoint);
        $stored = $original['scope'][$key] ?? [];
        if (!\is_array($stored)) {
            return [];
        }
        $stored = array_map(static fn (mixed $id): string => strtolower((string) $id), array_filter($stored, 'is_string'));

        return array_values(array_unique(array_filter(
            $unknown,
            static fn (string $reference): bool => \in_array(strtolower($reference), $stored, true),
        )));
    }

    /**
     * Structure errors of `scope` or `retrieval` against the field's schema
     * file, by JSON pointer relative to the field ('' for the field itself).
     *
     * @param array<mixed> $value
     *
     * @return array<string, string>
     */
    public static function schemaErrors(string $field, array $value): array
    {
        $file = match ($field) {
            'scope' => McpEndpoint::SCOPE_SCHEMA_FILE,
            'retrieval' => McpEndpoint::RETRIEVAL_SCHEMA_FILE,
            default => throw new \InvalidArgumentException(\sprintf('No schema for the field "%s".', $field)),
        };
        /** @var object $schema */
        $schema = json_decode((string) file_get_contents($file), false, 512, \JSON_THROW_ON_ERROR);

        $validator = new Validator();
        $validator->setMaxErrors(20);
        $result = $validator->validate(self::toJsonValue($value, true), $schema);
        if ($result->isValid()) {
            return [];
        }

        $errors = [];
        foreach ((new ErrorFormatter())->format($result->error(), false) as $pointer => $message) {
            $errors['/' === $pointer ? '' : (string) $pointer] = (string) $message;
        }

        return $errors;
    }

    /**
     * Decoded JSON (associative arrays) back to the JSON value opis
     * validates: string-keyed arrays become objects, lists stay arrays, an
     * empty array is an object at the top level ($asObject) and a list
     * below it.
     */
    private static function toJsonValue(mixed $value, bool $asObject = false): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        if ([] === $value) {
            return $asObject ? new \stdClass() : [];
        }
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::toJsonValue($item), $value);
        }

        $object = new \stdClass();
        foreach ($value as $key => $item) {
            $object->{$key} = self::toJsonValue($item);
        }

        return $object;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function violation(string $message, array $parameters, string $path, mixed $invalidValue, string $code, ValidMcpEndpoint $constraint): void
    {
        $base = $this->context->getPropertyPath();

        $this->context->getViolations()->add(new ConstraintViolation(
            strtr($message, $parameters),
            $message,
            $parameters,
            $this->context->getRoot(),
            '' === $base ? $path : $base.'/'.$path,
            $invalidValue,
            null,
            $code,
            $constraint,
        ));
    }
}
