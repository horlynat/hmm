<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil : point d'entrée unique après connexion, avant de choisir
 * entre les deux espaces distincts de l'application — l'administration
 * (/admin/dashboard/, back-office) et l'espace projets (/projects, pour les
 * clients et les collaborateurs/freelances, cf. MemberProjectController).
 *
 * Document HTML autonome (templates/home/index.html.twig) : ne présente
 * NI l'en-tête NI la barre latérale du back-office (contrairement aux
 * pages sous /admin) — cette page précède le choix de l'un des deux
 * espaces, elle n'en fait partie d'aucun.
 *
 * Accessible dès ROLE_USER (cf. access_control ^/$ dans security.yaml,
 * même plancher que ^/projects) : un client qui n'a accès qu'à l'espace
 * projets doit pouvoir atteindre cette page aussi, pas seulement le
 * personnel du back-office.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'home_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
