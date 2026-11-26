<?php

namespace App\Controller\Admin;

use App\Entity\SkillDomain;
use App\Form\SkillDomainType;
use App\Repository\SkillDomainRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Domaines de compétence (onglets « Compétences » de la page À propos).
 *
 * 🔒 Sécurité : ROLE_ADMIN, comme le reste des textes de la page À propos ;
 * suppression protégée par jeton CSRF.
 */
#[Route('/admin/content/skill-domains', name: 'admin_skill_domain_')]
final class AdminSkillDomainController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SkillDomainRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/skill_domain/index.html.twig', ['domains' => $repository->findOrdered()]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $domain = new SkillDomain();
        $form = $this->createForm(SkillDomainType::class, $domain);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($domain);
            $em->flush();
            $auditLogger->log(SkillDomain::class, (int) $domain->getId(), $domain->getTitle(), 'created');
            $em->flush();
            $this->addFlash('success', 'Le domaine a été créé.');

            return $this->redirectToRoute('admin_skill_domain_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/skill_domain/form.html.twig', ['form' => $form, 'domain' => $domain, 'title' => 'Nouveau domaine de compétence']);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, SkillDomain $domain, EntityManagerInterface $em, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(SkillDomainType::class, $domain);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $auditLogger->log(SkillDomain::class, (int) $domain->getId(), $domain->getTitle(), 'updated');
            $em->flush();
            $this->addFlash('success', 'Le domaine a été mis à jour.');

            return $this->redirectToRoute('admin_skill_domain_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/skill_domain/form.html.twig', ['form' => $form, 'domain' => $domain, 'title' => 'Modifier : '.$domain->getTitle()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, SkillDomain $domain, EntityManagerInterface $em, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('admin_skill_domain_delete_'.$domain->getId(), (string) $request->request->get('_token'))) {
            $auditLogger->log(SkillDomain::class, (int) $domain->getId(), $domain->getTitle(), 'deleted');
            $em->remove($domain);
            $em->flush();
            $this->addFlash('success', 'Le domaine a été supprimé.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide : suppression annulée.');
        }

        return $this->redirectToRoute('admin_skill_domain_index', [], Response::HTTP_SEE_OTHER);
    }
}
