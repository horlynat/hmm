<?php

namespace App\Tests\Entity;

use App\Entity\Invoice;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    public function testWasCreatedAndMarkedPaidBySamePersonIsFalseWhenEitherIsMissing(): void
    {
        $invoice = new Invoice();
        self::assertFalse($invoice->wasCreatedAndMarkedPaidBySamePerson());

        $invoice->setCreatedBy(new User());
        self::assertFalse($invoice->wasCreatedAndMarkedPaidBySamePerson());
    }

    public function testWasCreatedAndMarkedPaidBySamePersonIsFalseForTwoDistinctUnpersistedUsers(): void
    {
        // Piège : deux User fraîchement instanciés ont tous les deux un ID
        // null — une comparaison par ID donnerait ici un faux "même personne".
        $invoice = new Invoice();
        $invoice->setCreatedBy(new User());
        $invoice->setMarkedPaidBy(new User());

        self::assertFalse($invoice->wasCreatedAndMarkedPaidBySamePerson());
    }

    public function testWasCreatedAndMarkedPaidBySamePersonIsTrueForTheSameUserInstance(): void
    {
        $user = new User();
        $invoice = new Invoice();
        $invoice->setCreatedBy($user);
        $invoice->setMarkedPaidBy($user);

        self::assertTrue($invoice->wasCreatedAndMarkedPaidBySamePerson());
    }
}
