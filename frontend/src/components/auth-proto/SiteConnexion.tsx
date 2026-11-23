import { AuthCard } from "./AuthCard";

/** Page Connexion / Créer un compte — markup du prototype (`.authpg .w.ag`). */
export function SiteConnexion({ tab }: { tab?: "in" | "up" }) {
  return (
    <section className="authpg">
      <AuthCard initialTab={tab === "up" ? "up" : "in"} />
    </section>
  );
}
