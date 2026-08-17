import "../styles/home.css";

// Révèle la page une fois que ce module (et son import CSS ci-dessus) a fini
// de s'exécuter — voir templates/_partials/_vite_fouc_guard.html.twig pour
// le détail du flash sans styles que ça évite en dev. Page purement
// statique (aucun formulaire) : pas de contrôleur applicatif à charger ici,
// contrairement à login.js/register.js.
document.documentElement.classList.add("vite-ready");
