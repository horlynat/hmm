<?php

namespace App\Tests\Functional\Security;

use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Enum\ExpenseCategoryEnum;
use App\Enum\ProjectStatusEnum;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * P1 #6 — IDOR via la LiveProp `project` d'ExpenseList : un visiteur légitime
 * sur le projet A renvoyait `"updated":{"project":<B>}` et obtenait les
 * dépenses (et chemins de justificatifs) du projet B, sans y être autorisé.
 *
 * Attaque reproduite avec le protocole réel du composant (checksum légitime
 * du montage initial sur A, cf. InteractsWithLiveComponents::set()).
 */
final class ExpenseListIdorTest extends FunctionalTestCase
{
    use InteractsWithLiveComponents;

    private function makeProject(string $title, ?\App\Entity\User $owner = null): Project
    {
        $project = new Project();
        $project->setTitle($title);
        $project->setDescription('Description de '.$title);
        $project->setLink('https://example.com/'.$title);
        $project->setStatus(ProjectStatusEnum::IN_PROGRESS);
        $project->generateSlug($title.'-'.bin2hex(random_bytes(4)));
        if (null !== $owner) {
            $project->setOwner($owner);
        }
        $this->em()->persist($project);

        return $project;
    }

    public function testCollaboratorCannotSwapLiveComponentToAnUnauthorizedProject(): void
    {
        $collaboratorA = $this->createUser(['ROLE_EDITOR']);
        $outsider = $this->createUser(['ROLE_EDITOR']);

        $projectA = $this->makeProject('Projet A', $collaboratorA);
        $projectB = $this->makeProject('Projet B secret', $outsider);
        $this->em()->flush();

        $secretExpense = new ProjectExpense();
        $secretExpense->setAmount('999.00');
        $secretExpense->setDescription('Note de frais confidentielle');
        $secretExpense->setCategory(ExpenseCategoryEnum::OTHER);
        $secretExpense->setUser($outsider);
        $secretExpense->setReceiptPath('/uploads/receipts/secret-invoice.pdf');
        $projectB->addExpense($secretExpense);
        $this->em()->persist($secretExpense);
        $this->em()->flush();

        $component = $this->createLiveComponent('project:expense_list', ['project' => $projectA])
            ->actingAs($collaboratorA);

        // Montage initial légitime sur A : doit réussir (checkAccess() passe).
        $initialHtml = $component->render()->toString();
        self::assertStringNotContainsString('secret-invoice', $initialHtml);

        // Attaque : échange de l'identité via "updated" (LiveProp writable).
        try {
            $component->set('project', (string) $projectB->getId());
            self::fail('Le swap d\'identité vers un projet non autorisé doit être rejeté, pas silencieusement accepté.');
        } catch (\Throwable $e) {
            self::assertInstanceOf(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class, $e);
        }
    }

    public function testCollaboratorCanStillRenderTheirOwnProject(): void
    {
        $collaborator = $this->createUser(['ROLE_EDITOR']);
        $project = $this->makeProject('Mon projet', $collaborator);
        $this->em()->flush();

        $html = $this->createLiveComponent('project:expense_list', ['project' => $project])
            ->actingAs($collaborator)
            ->render()
            ->toString();

        self::assertStringNotContainsString('Access Denied', $html);
    }

    public function testUnrelatedUserCannotEvenMountTheComponentOnAForeignProject(): void
    {
        $owner = $this->createUser(['ROLE_EDITOR']);
        $stranger = $this->createUser(['ROLE_EDITOR']);
        $project = $this->makeProject('Projet protégé', $owner);
        $this->em()->flush();

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->createLiveComponent('project:expense_list', ['project' => $project])
            ->actingAs($stranger)
            ->render();
    }

    public function testBudgetSummaryAlsoRejectsAnUnauthorizedMount(): void
    {
        $owner = $this->createUser(['ROLE_EDITOR']);
        $stranger = $this->createUser(['ROLE_EDITOR']);
        $project = $this->makeProject('Projet protégé budget', $owner);
        $project->setBudget('10000.00');
        $this->em()->flush();

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->createLiveComponent('project:budget_summary', ['project' => $project])
            ->actingAs($stranger)
            ->render();
    }

    public function testPlainUserCannotReachTheAdminProjectTableComponentDirectly(): void
    {
        $plainClient = $this->createUser(['ROLE_USER']);

        // Double rempart : la règle access_control (ROLE_ADMIN) bloque
        // d'abord au niveau HTTP (403, avant même que la requête n'atteigne
        // le composant) ; ProjectTable::checkAccess() (PostMount) referait le
        // même refus si jamais ce composant était un jour embarqué ailleurs
        // sous un chemin non couvert par cette règle.
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->createLiveComponent('admin_project_table')
            ->actingAs($plainClient)
            ->render();
    }

    public function testAdminCanReachTheAdminProjectTableComponent(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        $html = $this->createLiveComponent('admin_project_table')
            ->actingAs($admin)
            ->render()
            ->toString();

        self::assertStringNotContainsString('Access Denied', $html);
    }
}
