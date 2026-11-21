<?php

namespace App\Validator;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class MailerAddressValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MailerAddress) {
            throw new UnexpectedTypeException($constraint, MailerAddress::class);
        }
        // Vide et type : laissés à NotBlank / Assert\Email / au typage.
        if (null === $value || '' === $value || !\is_string($value)) {
            return;
        }

        // Toute exception Mime, pas seulement RfcComplianceException :
        // `new Address()` lève aussi InvalidArgumentException sur un caractère
        // de contrôle — oubli qui donnait une 500 (contre-audit de dc6b849).
        try {
            new Address($value);
        } catch (MimeException) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
