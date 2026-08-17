<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil du back-office : point d'entrée à part du tableau de bord
 * (admin_dashboard_index, réservé ROLE_ADMIN+ et inchangé — cette page ne
 * remplace ni ne modifie la redirection existante de SecurityAuthenticator),
 * accessible à toute l'équipe back-office (ROLE_EDITOR et plus, cf.
 * access_control ^/$ dans security.yaml) une fois connectée.
 *
 * Volontairement sans requête coûteuse : un hub de navigation, pas un
 * second tableau de bord. Toute la personnalisation (rôle affiché, sections
 * visibles, heure de la journée) est calculée côté Twig à partir de
 * app.user et is_granted(), à l'identique de _admin.nav.html.twig — même
 * source de vérité que le menu, pas de logique dupliquée en PHP qui
 * pourrait diverger de lui.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'home_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
