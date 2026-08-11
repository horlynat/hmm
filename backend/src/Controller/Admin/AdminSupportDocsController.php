<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Documentation interne du back-office (statique, écrite en dur dans le
 * template — pas de CRUD, pas d'entité) : une seule page scrollable avec une
 * table des matières ancrée, pas neuf routes séparées.
 */
#[Route('/admin/support/docs', name: 'admin_support_docs_')]
final class AdminSupportDocsController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // Pas d'attribut dédié : lecture ouverte à qui a déjà accès au back-office.
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->render('admin/support/docs.html.twig');
    }
}
