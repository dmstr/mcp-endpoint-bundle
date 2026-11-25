<?php

// file generated with AI assistance: Claude Code - 2026-10-31 20:00:00 UTC

declare(strict_types=1);

namespace Dmstr\McpEndpoint\Validator;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Refuses a permission that no bundle or application registered. Without the
 * parameter `za7_core.permissions.registered` (the host application publishes
 * no list) it accepts every value; the format is checked by the Regex constraint of the entity.
 */
final class RegisteredPermissionValidator extends ConstraintValidator
{
    public const string PARAMETER = 'za7_core.permissions.registered';

    public function __construct(private readonly ParameterBagInterface $parameters)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RegisteredPermission) {
            throw new UnexpectedTypeException($constraint, RegisteredPermission::class);
        }

        if (!\is_string($value) || '' === $value || !$this->parameters->has(self::PARAMETER)) {
            return;
        }

        /** @var list<string> $registered */
        $registered = $this->parameters->get(self::PARAMETER);
        if (!\in_array($value, $registered, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ permission }}', $value)
                ->setCode(RegisteredPermission::NOT_REGISTERED_ERROR)
                ->addViolation();
        }
    }
}
