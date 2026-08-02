import type { ReactNode } from "react";
import { redirect } from "@/i18n/navigation";
import { AccountShell } from "@/components/sections/AccountShell";
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
  };

  return (
    <main id="main-content" className="flex-1">
      <AccountShell user={user} isCollaborator={isCollaborator} counts={counts}>
        {children}
      </AccountShell>
    </main>
  );
}
