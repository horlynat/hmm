import "../styles/home.css";
// Webfont Tabler Icons vendorée (assets/vendor/tabler) — remplace le <link>
// jsdelivr de templates/home/index.html.twig ; servie en 'self' par Vite.
import "../vendor/tabler/tabler-icons.min.css";

// Révèle la page une fois que ce module (et son import CSS ci-dessus) a fini
// de s'exécuter — voir templates/_partials/_vite_fouc_guard.html.twig pour
// le détail du flash sans styles que ça évite en dev. Page purement
// statique (aucun formulaire) : pas de contrôleur applicatif à charger ici,
// contrairement à login.js/register.js.
document.documentElement.classList.add("vite-ready");
