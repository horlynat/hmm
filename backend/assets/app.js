import "@hotwired/turbo";
import "./stimulus_bootstrap.js";
import "./styles/app.css";
import "./js/dashboard.js";
import "./js/project.js";
import "./js/idle-timeout.js";

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


