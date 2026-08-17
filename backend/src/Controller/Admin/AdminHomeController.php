<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil du back-office : point d'atterrissage unique après
 * connexion pour TOUS les rôles back-office (Éditeur à Super Administrateur),
 * cf. SecurityAuthenticator::onAuthenticationSuccess().
 *
 * Avant cette page, seul ROLE_ADMIN+ était redirigé quelque part d'utile
 * (admin_dashboard_index, réservé à ce même rôle par DashboardVoter) —
 * Éditeur/Modérateur/Manager atterrissaient sur leur propre fiche profil
 * après connexion, sans aucun point d'entrée vers les espaces auxquels ils
 * ont pourtant accès.
 *
 * Volontairement sans requête coûteuse : un hub de navigation, pas un
 * second tableau de bord (celui-ci existe déjà, cf. AdminDashboardController,
 * réservé à ROLE_ADMIN+ et lié depuis cette page pour qui y a droit). Toute
 * la personnalisation (rôle affiché, sections visibles, heure de la
 * journée) est calculée côté Twig à partir de app.user et is_granted(), à
 * l'identique de _admin.nav.html.twig — même source de vérité que le menu,
 * pas de logique dupliquée en PHP qui pourrait diverger de lui.
 *
 * 🔒 Déjà protégée par access_control `^/admin => ROLE_EDITOR`
 * (config/packages/security.yaml) : aucun rôle en dessous n'a de compte
 * back-office, rien à vérifier de plus ici.
 */
#[Route('/admin', name: 'admin_home_')]
class AdminHomeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/home/index.html.twig');
    }
}
