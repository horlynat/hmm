import "../styles/login.css";

// Révèle la page une fois que ce module (et son import CSS ci-dessus) a fini
// de s'exécuter — voir templates/_partials/_vite_fouc_guard.html.twig pour
// le détail du flash sans styles que ça évite en dev.
document.documentElement.classList.add("vite-ready");

/**
 * login.js
 * Améliorations non bloquantes pour la page de connexion.
 * - Indice visuel (non bloquant) sur le format de l'email pendant la saisie
 * - Prévention du double submit
 * - Protections pour éviter les erreurs si éléments manquants
 *
 * Important : contrairement à l'inscription, on ne valide PAS la force du
 * mot de passe ici. Un compte existant peut avoir été créé avant un
 * durcissement de la politique de mot de passe, ou simplement avoir un mot
 * de passe qui ne correspond pas à ce pattern arbitraire — bloquer la
 * soumission dans ce cas empêcherait un utilisateur légitime de se
 * connecter alors que le serveur, seul juge de la validité des
 * identifiants, les aurait acceptés. Le formulaire ne doit jamais décider
 * à la place du serveur si un mot de passe est "correct".
 *
 * Intégration
 * - Inclure via encore_entry_script_tags('login') placé juste avant </body> ou avec defer
 */

document.addEventListener("DOMContentLoaded", () => {
    // -------------------------
    // Sélecteurs et guards
    // -------------------------
    const emailInput = document.getElementById("inputEmail");
    const loginForm = document.getElementById("loginForm");
    const emailHelper = document.getElementById("emailHelper");

    // Vérification initiale pour éviter null.addEventListener
    if (!loginForm) {
        console.error(
            "login.js: formulaire introuvable (id=loginForm). Le script est arrêté.",
        );
        return;
    }

    if (!emailInput) {
        console.error("login.js: input email introuvable");
    }

    // -------------------------
    // Indice visuel (non bloquant) sur le format de l'email
    // -------------------------
    if (emailInput) {
        emailInput.addEventListener("input", () => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const valid = emailRegex.test(emailInput.value.trim());

            emailInput.classList.remove("input-success", "input-danger");
            emailInput.classList.add(valid ? "input-success" : "input-danger");

            if (emailHelper) {
                emailHelper.textContent = valid
                    ? ""
                    : "⚠️ Veuillez entrer une adresse email valide";
                emailHelper.className = valid ? "helper-success" : "helper-danger";
            }
        });
    }

    // -------------------------
    // Soumission du formulaire : la validation d'identifiants revient
    // entièrement au serveur, on se contente d'éviter le double clic.
    // -------------------------
    loginForm.addEventListener("submit", () => {
        const submitBtn = loginForm.querySelector("button[type='submit']");
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = "⏳ Vérification...";
        }
    });
});
