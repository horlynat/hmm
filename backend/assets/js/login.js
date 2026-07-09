import "../styles/login.css";

/**
 * login.js
 * Validation en temps réel pour la page de connexion.
 * - Validation email et mot de passe côté client
 * - Mise à jour des helpers visuels
 * - Prévention du double submit
 * - Protections pour éviter les erreurs si éléments manquants
 *
 * Intégration
 * - Inclure via encore_entry_script_tags('login') placé juste avant </body> ou avec defer
 */

document.addEventListener("DOMContentLoaded", () => {
    // -------------------------
    // Sélecteurs et guards
    // -------------------------
    const emailInput = document.getElementById("inputEmail");
    const passwordInput = document.getElementById("inputPassword");
    const loginForm = document.getElementById("loginForm");
    const emailHelper = document.getElementById("emailHelper");
    const passwordHelper = document.getElementById("passwordHelper");

    // Vérification initiale pour éviter null.addEventListener
    if (!loginForm) {
        console.error(
            "login.js: formulaire introuvable (id=loginForm). Le script est arrêté.",
        );
        return;
    }

    // Si un champ manque, on logge mais on continue en mode dégradé
    if (!emailInput || !passwordInput) {
        console.error("login.js: input email ou password introuvable", {
            emailInput: !!emailInput,
            passwordInput: !!passwordInput,
        });
    }

    // -------------------------
    // Utilitaire d'état visuel
    // -------------------------
    const setValidationState = (
        input,
        helper,
        isValid,
        successMsg = "",
        errorMsg = "",
    ) => {
        if (!input) return;

        // Ne pas écraser d'autres classes utilitaires : utiliser classList
        input.classList.remove("input-success", "input-danger");
        if (isValid) {
            input.classList.add("input-success");
            input.removeAttribute("aria-invalid");
            if (helper) {
                helper.textContent = successMsg;
                helper.className = "helper-success";
            }
        } else {
            input.classList.add("input-danger");
            input.setAttribute("aria-invalid", "true");
            if (helper) {
                helper.textContent = errorMsg;
                helper.className = "helper-danger";
            }
        }
    };

    // -------------------------
    // Validation email en temps réel
    // -------------------------
    if (emailInput) {
        emailInput.addEventListener("input", () => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const valid = emailRegex.test(emailInput.value.trim());
            setValidationState(
                emailInput,
                emailHelper,
                valid,
                "",
                "⚠️ Veuillez entrer une adresse email valide",
            );
        });

        // ❌ SUPPRIMÉ : emailInput.dispatchEvent(new Event("input", { bubbles: true }));
    }

    // -------------------------
    // Validation mot de passe en temps réel
    // -------------------------
    if (passwordInput) {
        passwordInput.addEventListener("input", () => {
            const value = passwordInput.value;
            // règle simple : min 8, au moins 1 majuscule et 1 chiffre
            const isValid =
                value.length >= 8 && /[A-Z]/.test(value) && /\d/.test(value);
            setValidationState(
                passwordInput,
                passwordHelper,
                isValid,
                "",
                "⚠️ Min. 8 caractères, 1 majuscule et 1 chiffre",
            );
        });

        // ❌ SUPPRIMÉ : passwordInput.dispatchEvent(new Event("input", { bubbles: true }));
    }

    // -------------------------
    // Soumission du formulaire
    // -------------------------
    loginForm.addEventListener("submit", (e) => {
        // Si les inputs existent, on vérifie aria-invalid
        const emailInvalid =
            emailInput && emailInput.getAttribute("aria-invalid") === "true";
        const passwordInvalid =
            passwordInput &&
            passwordInput.getAttribute("aria-invalid") === "true";

        if (emailInvalid || passwordInvalid) {
            e.preventDefault();
            // UX : zone de feedback non bloquante est préférable ; on garde alert pour compatibilité
            alert("Veuillez corriger les erreurs avant de continuer.");
            return;
        }

        // Désactivation du bouton pour éviter le double clic
        const submitBtn = loginForm.querySelector("button[type='submit']");
        if (submitBtn) {
            submitBtn.disabled = true;
            // Conserver le contenu accessible si icônes ou children existent
            submitBtn.innerHTML = "⏳ Vérification...";
        }
    });

    // -------------------------
    // Fin DOMContentLoaded
    // -------------------------
});
