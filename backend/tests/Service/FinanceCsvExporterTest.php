<?php

namespace App\Tests\Service;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Entity\User;
use App\Enum\ExpenseCategoryEnum;
use App\Enum\ExpenseStatusEnum;
use App\Enum\InvoiceStatusEnum;
use App\Service\FinanceCsvExporter;
use PHPUnit\Framework\TestCase;

final class FinanceCsvExporterTest extends TestCase
{
    private FinanceCsvExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new FinanceCsvExporter();
    }

    private function user(string $email, ?string $fullName = null): User
    {
        return (new User())->setEmail($email)->setFullName($fullName);
    }

    private function project(string $title, ?User $client = null): Project
    {
        $project = (new Project())->setTitle($title);
        if (null !== $client) {
            $project->setClient($client);
        }

        return $project;
    }

    private function invoice(Project $project): Invoice
    {
        return (new Invoice())
            ->setProject($project)
            ->setNumber('FA-2026-001')
            ->setLabel('Facture initiale')
            ->setAmount('1200.00')
            ->setCurrency('EUR')
            ->setStatus(InvoiceStatusEnum::PAID)
            ->setIssuedAt(new \DateTimeImmutable('2026-01-10'));
    }

    public function testInvoiceRowHasOneValuePerHeader(): void
    {
        $row = $this->exporter->invoiceRow($this->invoice($this->project('Refonte site')));

        self::assertCount(count($this->exporter->invoiceHeaders()), $row);
    }

    public function testInvoiceRowUsesRawDecimalAmountNotLocalized(): void
    {
        $row = $this->exporter->invoiceRow($this->invoice($this->project('Refonte site')));

        self::assertSame('1200.00', $row[4], 'Le montant exporté doit rester une valeur machine, pas un affichage localisé.');
        self::assertSame('EUR', $row[5]);
    }

    public function testInvoiceRowResolvesClientDisplayNameWithEmailFallback(): void
    {
        $withFullName = $this->invoice($this->project('A', $this->user('client@example.com', 'Awa Client')));
        $withoutFullName = $this->invoice($this->project('B', $this->user('client2@example.com')));

        self::assertSame('Awa Client', $this->exporter->invoiceRow($withFullName)[2]);
        self::assertSame('client2@example.com', $this->exporter->invoiceRow($withoutFullName)[2]);
    }

    public function testInvoiceRowHandlesMissingClientAndOptionalDatesWithoutError(): void
    {
        $invoice = $this->invoice($this->project('Sans client lié'));

        $row = $this->exporter->invoiceRow($invoice);

        self::assertSame('', $row[2], 'Aucun client lié au projet ne doit jamais lever d\'exception.');
        self::assertSame('', $row[8], 'dueDate absente → chaîne vide.');
        self::assertSame('', $row[9], 'paidAt absente → chaîne vide.');
    }

    public function testInvoiceRowFormatsDatesWhenPresent(): void
    {
        $invoice = $this->invoice($this->project('X'))
            ->setDueDate(new \DateTimeImmutable('2026-02-10'))
            ->setPaidAt(new \DateTimeImmutable('2026-02-01'));

        $row = $this->exporter->invoiceRow($invoice);

        self::assertSame('2026-01-10', $row[7]);
        self::assertSame('2026-02-10', $row[8]);
        self::assertSame('2026-02-01', $row[9]);
    }

    private function expense(Project $project, User $author): ProjectExpense
    {
        return (new ProjectExpense())
            ->setProject($project)
            ->setUser($author)
            ->setCategory(ExpenseCategoryEnum::SOFTWARE)
            ->setDescription('Licence annuelle')
            ->setAmount('89.00')
            ->setStatus(ExpenseStatusEnum::APPROVED)
            ->setSpentAt(new \DateTimeImmutable('2026-01-05'));
    }

    public function testExpenseRowHasOneValuePerHeader(): void
    {
        $author = $this->user('dev@example.com', 'Dev Un');
        $row = $this->exporter->expenseRow($this->expense($this->project('Refonte site'), $author));

        self::assertCount(count($this->exporter->expenseHeaders()), $row);
    }

    public function testExpenseRowUsesRawDecimalAmountAndEffectiveDate(): void
    {
        $author = $this->user('dev@example.com', 'Dev Un');
        $row = $this->exporter->expenseRow($this->expense($this->project('Refonte site'), $author));

        self::assertSame('89.00', $row[3]);
        self::assertSame('2026-01-05', $row[6], 'Doit utiliser getEffectiveDate() (spentAt), pas createdAt.');
    }

    public function testExpenseRowHandlesUnapprovedExpenseWithoutError(): void
    {
        $author = $this->user('dev@example.com');
        $expense = $this->expense($this->project('Refonte site'), $author)->setStatus(ExpenseStatusEnum::PENDING);

        $row = $this->exporter->expenseRow($expense);

        self::assertSame('', $row[7], 'Pas encore approuvée → approvedBy vide, sans exception.');
        self::assertSame('', $row[8]);
        self::assertSame('dev@example.com', $row[5], 'Pas de fullName → repli sur l\'email.');
    }

    public function testExpenseRowResolvesApprover(): void
    {
        $author = $this->user('dev@example.com', 'Dev Un');
        $approver = $this->user('manager@example.com', 'Manager Un');
        $expense = $this->expense($this->project('Refonte site'), $author)
            ->setApprovedBy($approver)
            ->setApprovedAt(new \DateTimeImmutable('2026-01-06'));

        $row = $this->exporter->expenseRow($expense);

        self::assertSame('Manager Un', $row[7]);
        self::assertSame('2026-01-06', $row[8]);
    }
}
