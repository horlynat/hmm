import type { ReactNode } from "react";
import { redirect } from "@/i18n/navigation";
import { AccountShell } from "@/components/sections/AccountShell";
import { TwoFactorSetupGate } from "@/components/sections/TwoFactorSetupGate";
import { getCurrentUser } from "@/lib/auth/session";

/**
 * Garde serveur de l'espace compte : toute page sous /compte exige une session
 * valide. Sans session (cookie absent, token expiré → 401 sur /api/me),
 * redirection vers la page de connexion.
 */
export default async function CompteLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();

  if (!user) {
    redirect({ href: "/connexion", locale });
    return null;
  }

  // La 2FA est obligatoire sur l'espace membre (client/freelance/collaborateur
  // non staff) — même frontière que le backend (App\EventSubscriber\
  // AccountStatusSubscriber côté web, App\Security\Api\TwoFactorAwareJwtSuccessHandler
  // à la connexion) : ROLE_EDITOR (collaborateur vetté par un admin) en est
  // exempté, tout le reste doit l'activer avant d'accéder à quoi que ce soit
  // sous /compte. Pas de redirection vers une route dédiée (qui obligerait à
  // maintenir une liste d'exclusion façon SKIP_PATTERN, avec le même risque
  // de boucle) : on affiche directement l'écran d'activation ICI, quelle que
  // soit la page /compte/* demandée — TwoFactorSetupGate déclenche un
  // router.refresh() une fois l'activation confirmée, qui refait ce rendu
  // serveur et laisse la vraie page s'afficher normalement.
  if (!user.roles.includes("ROLE_EDITOR") && !user.isTwoFactorEnabled) {
    return (
      <main id="main-content" className="flex-1">
        <TwoFactorSetupGate />
      </main>
    );
  }

  const { attributions, isCollaborator } = user;
  // Comptes affichés dans l'aside — dérivés des mêmes attributions que les
  // pages /compte/projets, /compte/devis et /compte/gestion-projet, pour
  // rester cohérents avec ce que ces pages affichent réellement.
  const counts = {
    pendingQuotes: attributions.quoteRequests.filter((q) => q.status === "pending").length,
    myProjects:
      attributions.clientProjects.length +
      (isCollaborator ? attributions.collaboratingProjects.length + attributions.ownedProjects.length : 0),
    managedProjects: attributions.collaboratingProjects.length + attributions.ownedProjects.length,
    unpaidInvoices: attributions.invoices.filter((inv) => inv.status === "pending").length,
    unreadMessages: user.unreadMessagesCount,
    availableProjects: user.availableProjectsCount,
  };

  return (
    <main id="main-content" className="flex-1">
      <AccountShell user={user} isCollaborator={isCollaborator} counts={counts}>
        {children}
      </AccountShell>
    </main>
  );
}
