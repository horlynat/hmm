<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil PUBLIQUE du portail back-office — volontairement hors de
 * Controller/Admin/ (par convention dans ce projet, tout ce qui y vit
 * suppose un accès déjà authentifié) et hors de tout access_control
 * protégé : accessible à quiconque, avant connexion, quel que soit son
 * futur rôle une fois connecté.
 *
 * Ne remplace ni ne modifie le flux existant : SecurityAuthenticator et la
 * redirection post-connexion (admin_dashboard_index pour ROLE_ADMIN+,
 * profile_read sinon) restent inchangés. Cette page est un point d'entrée
 * additionnel, pas un nouveau maillon de la chaîne d'authentification.
 *
 * Document HTML autonome (templates/home/index.html.twig), ne pas confondre
 * avec les pages admin qui étendent base.html.twig : ce dernier inclut
 * _header.html.twig, qui accède à app.user.* sans garde de nullité (sûr
 * uniquement pour un utilisateur déjà authentifié) — voir security.yaml
 * pour l'access_control PUBLIC_ACCESS associé à cette route.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'home_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
