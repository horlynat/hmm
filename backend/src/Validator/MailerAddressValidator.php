<?php

namespace App\Validator;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;
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

        try {
            new Address($value);
        } catch (RfcComplianceException) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
