<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * FAQ interne du back-office (statique, écrite en dur dans le template —
 * pas de CRUD, pas d'entité).
 */
#[Route('/admin/support/faq', name: 'admin_support_faq_')]
final class AdminSupportFaqController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->render('admin/support/faq.html.twig');
    }
}
