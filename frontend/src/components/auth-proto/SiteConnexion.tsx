import { AuthCard } from "./AuthCard";

/** Page Connexion / Créer un compte — markup du prototype (`.authpg .w.ag`). */
export function SiteConnexion({ tab, forgot }: { tab?: "in" | "up"; forgot?: boolean }) {
  return (
    <section className="authpg">
      <AuthCard initialTab={tab === "up" ? "up" : "in"} initialForgot={forgot} />
    </section>
  );
}
