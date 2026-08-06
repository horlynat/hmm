<?php

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Form\InvoiceType;
use App\Security\Voter\ProjectVoter;
use App\Service\ProjectNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion des factures client d'un projet — CRUD simple (formulaires
 * traditionnels, pas de Live Component) : créer, modifier, marquer
 * payée/impayée, supprimer. Distinct de ProjectExpense (dépense interne).
 */
#[Route('/admin/project/{id}/invoices', name: 'admin_invoice_', requirements: ['id' => '\d+'])]
final class AdminInvoiceController extends AbstractController
{
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Project $project, Request $request, EntityManagerInterface $entityManager, ProjectNotifier $projectNotifier): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_INVOICE, $project);

        $invoice = new Invoice();
        $invoice->setProject($project);
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->addInvoice($invoice);
            $entityManager->persist($invoice);
            $entityManager->flush();
            $projectNotifier->invoiceCreated($invoice);
            $this->addFlash('success', 'Facture créée. Le client a été notifié par email.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        return $this->render('admin/project/invoice/new.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{invoiceId}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['invoiceId' => '\d+'])]
    public function edit(
        Project $project,
        #[MapEntity(id: 'invoiceId')] Invoice $invoice,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_INVOICE, $project);

        if ($invoice->getProject() !== $project) {
            $this->addFlash('error', 'Facture introuvable ou non associée à ce projet.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Facture modifiée.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        return $this->render('admin/project/invoice/edit.html.twig', [
            'project' => $project,
            'invoice' => $invoice,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{invoiceId}/mark-paid', name: 'mark_paid', methods: ['POST'], requirements: ['invoiceId' => '\d+'])]
    public function markPaid(
        Project $project,
        #[MapEntity(id: 'invoiceId')] Invoice $invoice,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectNotifier $projectNotifier,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_INVOICE, $project);

        if ($invoice->getProject() !== $project) {
            $this->addFlash('error', 'Facture introuvable ou non associée à ce projet.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if ($this->isCsrfTokenValid('invoice_'.$invoice->getId(), $request->request->get('_token'))) {
            $invoice->markPaid();
            $entityManager->flush();
            $projectNotifier->invoicePaid($invoice);
            $this->addFlash('success', 'Facture marquée comme payée. Le client a été notifié par email.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    #[Route('/{invoiceId}/mark-unpaid', name: 'mark_unpaid', methods: ['POST'], requirements: ['invoiceId' => '\d+'])]
    public function markUnpaid(
        Project $project,
        #[MapEntity(id: 'invoiceId')] Invoice $invoice,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_INVOICE, $project);

        if ($invoice->getProject() !== $project) {
            $this->addFlash('error', 'Facture introuvable ou non associée à ce projet.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if ($this->isCsrfTokenValid('invoice_'.$invoice->getId(), $request->request->get('_token'))) {
            $invoice->markUnpaid();
            $entityManager->flush();
            $this->addFlash('success', 'Facture remise en attente de paiement.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    #[Route('/{invoiceId}/delete', name: 'delete', methods: ['POST'], requirements: ['invoiceId' => '\d+'])]
    public function delete(
        Project $project,
        #[MapEntity(id: 'invoiceId')] Invoice $invoice,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_INVOICE, $project);

        if ($invoice->getProject() !== $project) {
            $this->addFlash('error', 'Facture introuvable ou non associée à ce projet.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if ($this->isCsrfTokenValid('delete_invoice_'.$invoice->getId(), $request->request->get('_token'))) {
            $project->removeInvoice($invoice);
            $entityManager->remove($invoice);
            $entityManager->flush();
            $this->addFlash('success', 'Facture supprimée.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }
}
