import "../styles/login.css";

/**
 * register.js
 * Validation en temps réel pour le formulaire d'inscription.
 * - Utilise des sélecteurs robustes pour éviter les problèmes d'IDs dynamiques
 * - Validation email, mot de passe, RGPD
 * - Barre de progression du mot de passe mise à jour en direct
 * - Prévention du double submit
 */

document.addEventListener("DOMContentLoaded", () => {
    // Sélection du formulaire
    const form = document.getElementById("registerForm");
    if (!form) {
        console.error("register.js: formulaire introuvable (id=registerForm).");
        return;
    }

    // Sélection des éléments avec des sélecteurs plus robustes
    const emailInput = form.querySelector("[name*='email']"); // Sélecteur par attribut name
    const passwordInput = form.querySelector("[name*='plainPassword']"); // Sélecteur par attribut name
    const rgpdInput = form.querySelector("[name*='agreeTerms']"); // Sélecteur par attribut name
    const submitBtn = form.querySelector("button[type='submit']"); // Sélecteur par type
    const passwordStrength = document.getElementById("passwordStrength");
    const passwordStrengthText = document.getElementById(
        "passwordStrengthText",
    );

    // Helpers (optionnels)
    const emailHelper = document.getElementById("emailHelper");
    const passwordHelper = document.getElementById("passwordHelper");
    const rgpdHelper = document.getElementById("rgpdHelper");

    // États de validation
    let emailValid = false;
    let passwordValid = false;
    let rgpdValid = false;

    // Fonction pour mettre à jour l'état de validation
    const setValidationState = (
        input,
        helper,
        isValid,
        successMsg = "",
        errorMsg = "",
    ) => {
        if (!input) return;

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

    // Validation de l'email en temps réel
    if (emailInput) {
        emailInput.addEventListener("input", () => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            emailValid = emailRegex.test(emailInput.value.trim());
            setValidationState(
                emailInput,
                emailHelper,
                emailValid,
                "✅",
                "⚠️ Email invalide",
            );
            checkAllValidations();
        });
    }

    // Validation du mot de passe + barre de progression
    if (passwordInput) {
        passwordInput.addEventListener("input", () => {
            const password = passwordInput.value;
            const strength = evaluatePasswordStrength(password);

            const configs = {
                "Très faible": {
                    w: 20,
                    c: "bg-red-500",
                    t: "⚠️ Très faible",
                    v: false,
                    txt: "text-red-600",
                },
                Faible: {
                    w: 40,
                    c: "bg-orange-500",
                    t: "⚠️ Faible",
                    v: false,
                    txt: "text-orange-600",
                },
                Moyen: {
                    w: 60,
                    c: "bg-yellow-500",
                    t: "🔒 Moyen",
                    v: false,
                    txt: "text-yellow-600",
                },
                Fort: {
                    w: 80,
                    c: "bg-green-500",
                    t: "✅ Fort",
                    v: true,
                    txt: "text-green-600",
                },
                "Très fort": {
                    w: 100,
                    c: "bg-green-600",
                    t: "✅ Très fort",
                    v: true,
                    txt: "text-green-600",
                },
            };

            const config = configs[strength] || configs["Très faible"];
            passwordValid = config.v;

            // Mise à jour de la barre de progression
            if (passwordStrength) {
                passwordStrength.style.width = `${config.w}%`;
                passwordStrength.className = `h-2 rounded transition-all duration-300 ease-in-out ${config.c}`;
            }
            if (passwordStrengthText) {
                passwordStrengthText.textContent = config.t;
                passwordStrengthText.className = `text-xs mt-1 ${config.txt}`;
            }

            setValidationState(
                passwordInput,
                passwordHelper,
                passwordValid,
                "✅",
                "⚠️ Min. 8 caractères, 1 majuscule et 1 chiffre",
            );
            checkAllValidations();
        });
    }

    // Validation du RGPD
    if (rgpdInput) {
        rgpdInput.addEventListener("change", () => {
            rgpdValid = rgpdInput.checked;
            if (rgpdHelper) {
                rgpdHelper.textContent = rgpdValid
                    ? "✅"
                    : "⚠️ Vous devez accepter les conditions";
                rgpdHelper.className = rgpdValid
                    ? "helper-success"
                    : "helper-danger";
            }
            checkAllValidations();
        });
    }

    // Vérification de tous les champs
    function checkAllValidations() {
        const allValid = emailValid && passwordValid && rgpdValid;
        if (submitBtn) {
            submitBtn.disabled = !allValid;
            submitBtn.classList.toggle("opacity-50", !allValid);
            submitBtn.classList.toggle("cursor-not-allowed", !allValid);
        }
    }

    // Soumission du formulaire
    form.addEventListener("submit", (e) => {
        const emailInvalid =
            emailInput && emailInput.getAttribute("aria-invalid") === "true";
        const passwordInvalid =
            passwordInput &&
            passwordInput.getAttribute("aria-invalid") === "true";
        const rgpdInvalid = rgpdInput && !rgpdInput.checked;

        if (emailInvalid || passwordInvalid || rgpdInvalid) {
            e.preventDefault();
            alert("Veuillez corriger les erreurs avant de continuer.");
        } else if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = "⏳ Inscription en cours...";
        }
    });
});

function evaluatePasswordStrength(password) {
    if (!password) return "Très faible";
    let poolSize = 0;
    if (/[a-z]/.test(password)) poolSize += 26;
    if (/[A-Z]/.test(password)) poolSize += 26;
    if (/\d/.test(password)) poolSize += 10;
    if (/[^A-Za-z0-9]/.test(password)) poolSize += 33;

    const entropy = poolSize > 0 ? password.length * Math.log2(poolSize) : 0;
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (entropy >= 100 && score >= 5) return "Très fort";
    if (entropy >= 80 && score >= 4) return "Fort";
    if (entropy >= 60 && score >= 3) return "Moyen";
    if (entropy >= 40 && score >= 2) return "Faible";
    return "Très faible";
}
