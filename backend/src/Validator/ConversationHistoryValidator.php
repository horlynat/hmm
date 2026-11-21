<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ConversationHistoryValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ConversationHistory) {
            throw new UnexpectedTypeException($constraint, ConversationHistory::class);
        }

        if (!\is_array($value) || [] === $value) {
            return;
        }

        $previousRole = null;
        $lastRole = null;
        foreach ($value as $turn) {
            $role = \is_array($turn) ? ($turn['role'] ?? null) : null;
            if (!\in_array($role, ['user', 'assistant'], true)) {
                // Forme invalide : déjà signalée par la contrainte Collection.
                return;
            }

            if ($role === $previousRole) {
                $this->context->buildViolation($constraint->alternationMessage)->addViolation();

                return;
            }

            $previousRole = $lastRole = $role;
        }

        if ('assistant' !== $lastRole) {
            $this->context->buildViolation($constraint->lastTurnMessage)->addViolation();
        }
    }
}
