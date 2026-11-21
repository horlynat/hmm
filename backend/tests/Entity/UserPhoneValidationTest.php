<?php

namespace App\Tests\Entity;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contre-audit B3 : `\s` et `$` sans `D` laissaient passer un saut de ligne
 * final et des tabulations dans le téléphone (stockés tels quels) — champ
 * renseigné par les inscriptions publiques client et freelance.
 */
final class UserPhoneValidationTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function phones(): iterable
    {
        yield 'international avec espaces' => ['+33 6 12 34 56 78', true];
        yield 'parenthèses et tirets' => ['(01) 23-45-67-89', true];
        yield 'saut de ligne final' => ["0612345678\n", false];
        yield 'tabulation' => ["0612\t345678", false];
        yield '21 caractères' => ['+'.str_repeat('1', 20), false];
        yield 'lettres' => ['06 12 AB 56 78', false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('phones')]
    public function testPhoneValidation(string $phone, bool $valid): void
    {
        /** @var ValidatorInterface $validator */
        $validator = static::getContainer()->get(ValidatorInterface::class);
        $violations = $validator->validateProperty((new User())->setPhone($phone), 'phone');

        self::assertSame($valid, 0 === \count($violations), sprintf('« %s » : %s attendu.', addcslashes($phone, "\n\t"), $valid ? 'valide' : 'refusé'));
    }
}
