import "@hotwired/turbo";
import "./stimulus_bootstrap.js";
import "./styles/app.css";
import "./js/dashboard.js";
import "./js/project.js";
import "./js/idle-timeout.js";

// 🔁 Alpine.js (build CSP, cf. base.html.twig) + Turbo Drive : à chaque navigation
// interceptée (clic sur un lien du menu, etc.), Turbo REMPLACE <body> par un
// nouveau nœud plutôt que de muter l'existant. Le MutationObserver interne
// d'Alpine, attaché une seule fois au tout premier chargement, ne voit donc plus
// rien après la 1ère navigation : les x-data (accordéons du nav admin, menu
// mobile, dropdowns du header/profil) cessent de réagir aux clics, alors que
// tout refonctionne après un F5 — c'est exactement le bug rapporté sur les
// accordéons du aside admin. Alpine ne gère pas Turbo nativement (aucun plugin
// officiel), d'où ce pont manuel : on détruit l'arbre Alpine juste avant que
// Turbo ne retire l'ancien body (turbo:before-render), puis on le réinitialise
// sur le nouveau body une fois le remplacement effectué (turbo:render). Ces deux
// évènements ne se déclenchent QUE lors d'une navigation Turbo, jamais au tout
// premier chargement de page — donc pas de double init avec l'auto-démarrage
// d'Alpine (Alpine.start(), déclenché par son propre script CDN).
document.addEventListener("turbo:before-render", () => {
    if (window.Alpine) {
        window.Alpine.destroy(document.body);
    }
});

document.addEventListener("turbo:render", () => {
    if (window.Alpine) {
        window.Alpine.initTree(document.body);
    }
});

// ✅ CORRECTION : document.addEventListener("turbo:load") au lieu de DOMContentLoaded
// Turbo ne redéclenche pas DOMContentLoaded à chaque navigation
// turbo:load se déclenche à chaque chargement de page Turbo

document.addEventListener("turbo:load", () => {
    // Dark Mode Toggle
    const toggle = document.getElementById("darkModeToggle");
    if (toggle) {
        toggle.addEventListener("click", () => {
            document.documentElement.classList.toggle("dark");
            // Persister le choix
            localStorage.setItem(
                "darkMode",
                document.documentElement.classList.contains("dark"),
            );
        });
    }

    // Restaurer le dark mode depuis localStorage
    if (localStorage.getItem("darkMode") === "true") {
        document.documentElement.classList.add("dark");
    }
});


