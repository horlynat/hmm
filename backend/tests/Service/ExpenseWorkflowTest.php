<?php

namespace App\Tests\Service;

use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Entity\User;
use App\Enum\ExpenseStatusEnum;
use App\Service\ExpenseWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ExpenseWorkflowTest extends TestCase
{
    private function workflow(): ExpenseWorkflow
    {
        // L'EntityManager n'est utilisé que pour persist/flush/remove : un stub suffit.
        return new ExpenseWorkflow($this->createStub(EntityManagerInterface::class));
    }

    private function user(string $email = 'admin@example.com'): User
    {
        return (new User())->setEmail($email);
    }

    private function project(string $budget): Project
    {
        return (new Project())->setBudget($budget);
    }

    private function expense(string $amount): ProjectExpense
    {
        return (new ProjectExpense())->setAmount($amount);
    }

    public function testSubmitIsRejectedWhenProjectHasNoBudget(): void
    {
        $this->expectException(\DomainException::class);
        $this->workflow()->submit($this->project('0.00'), $this->expense('50.00'), $this->user());
    }

    public function testSubmitCreatesPendingExpenseWithoutAffectingSpent(): void
    {
        $project = $this->project('100.00');
        $expense = $this->expense('60.00');

        $this->workflow()->submit($project, $expense, $this->user());

        self::assertSame(ExpenseStatusEnum::PENDING, $expense->getStatus());
        self::assertTrue($project->getExpenses()->contains($expense));
        self::assertSame('0.00', $project->getSpent(), 'Une dépense en attente ne doit pas impacter le budget dépensé.');
        self::assertSame('100.00', $project->getAvailableBudget());
    }

    public function testApproveIsBlockedWhenAmountExceedsAvailableBudget(): void
    {
        $wf = $this->workflow();
        $project = $this->project('100.00');
        $expense = $this->expense('150.00');
        $wf->submit($project, $expense, $this->user());

        try {
            $wf->approve($expense, $this->user());
            self::fail('Une dépense supérieure au disponible aurait dû être bloquée.');
        } catch (\DomainException) {
            // attendu
        }

        self::assertSame(ExpenseStatusEnum::PENDING, $expense->getStatus());
        self::assertSame('0.00', $project->getSpent());
    }

    public function testApproveWithinBudgetUpdatesSpent(): void
    {
        $wf = $this->workflow();
        $project = $this->project('100.00');
        $expense = $this->expense('60.00');
        $wf->submit($project, $expense, $this->user());

        $approver = $this->user('manager@example.com');
        $wf->approve($expense, $approver);

        self::assertSame(ExpenseStatusEnum::APPROVED, $expense->getStatus());
        self::assertSame($approver, $expense->getApprovedBy());
        self::assertSame('60.00', $project->getSpent());
        self::assertSame('40.00', $project->getAvailableBudget());
    }

    public function testSecondApprovalBeyondRemainingIsBlocked(): void
    {
        $wf = $this->workflow();
        $project = $this->project('100.00');

        $first = $this->expense('60.00');
        $wf->submit($project, $first, $this->user());
        $wf->approve($first, $this->user());
        self::assertSame('60.00', $project->getSpent());

        // Disponible restant = 40 ; une 2e dépense de 50 doit être bloquée.
        $second = $this->expense('50.00');
        $wf->submit($project, $second, $this->user());
        $this->expectException(\DomainException::class);
        $wf->approve($second, $this->user());
    }

    public function testRejectingAnApprovedExpenseFreesTheBudget(): void
    {
        $wf = $this->workflow();
        $project = $this->project('100.00');
        $expense = $this->expense('60.00');
        $wf->submit($project, $expense, $this->user());
        $wf->approve($expense, $this->user());
        self::assertSame('60.00', $project->getSpent());

        $wf->reject($expense, $this->user(), 'hors périmètre');

        self::assertSame(ExpenseStatusEnum::REJECTED, $expense->getStatus());
        self::assertSame('0.00', $project->getSpent(), 'Refuser une dépense approuvée doit libérer le budget.');
    }

    public function testRemovingAnApprovedExpenseFreesTheBudget(): void
    {
        $wf = $this->workflow();
        $project = $this->project('100.00');
        $expense = $this->expense('60.00');
        $wf->submit($project, $expense, $this->user());
        $wf->approve($expense, $this->user());

        $wf->remove($expense, $this->user());

        self::assertFalse($project->getExpenses()->contains($expense));
        self::assertSame('0.00', $project->getSpent());
    }
}
