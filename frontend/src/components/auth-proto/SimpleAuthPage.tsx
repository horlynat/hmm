import type { ReactNode } from "react";

/**
 * Page d'authentification secondaire à carte unique (vérification d'e-mail,
 * réinitialisation de mot de passe par jeton) : le prototype ne modélise que
 * /connexion (`.authpg .ag .aside2 .acard`) — ce gabarit reprend les mêmes
 * classes sans la colonne de présentation, pour rester cohérent avec elle.
 */
export function SimpleAuthPage({ children }: { children: ReactNode }) {
  return (
    <section className="authpg">
      <div className="w" style={{ maxWidth: "480px" }}>
        <div className="acard">
          <div id="abox">{children}</div>
        </div>
      </div>
    </section>
  );
}
