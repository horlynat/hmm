import "../styles/login.css";
// `authenticate` fait partie de framework.csrf_protection.stateless_token_ids
// (config/packages/csrf.yaml) : le champ _csrf_token n'est rendu qu'avec une
// valeur placeholder côté serveur (SameOriginCsrfTokenManager), ce script
// remplace cette valeur par un vrai jeton double-submit à la soumission. Sans
// cet import, la page /login (bundle Vite autonome, hors app.js/Stimulus)
// soumettait le placeholder tel quel → "jeton CSRF invalide" au login.
import "../controllers/csrf_protection_controller.js";

// Révèle la page une fois que ce module (et son import CSS ci-dessus) a fini
// de s'exécuter — voir templates/_partials/_vite_fouc_guard.html.twig pour
// le détail du flash sans styles que ça évite en dev.
document.documentElement.classList.add("vite-ready");

/**
 * login.js
 * Bundle Vite partagé par toutes les pages d'authentification "simples"
 * (login, mot de passe oublié, réinitialisation, 2FA — cf. le
 * vite_entry_script_tags('login') de chacun de ces templates). register.html.twig
 * a son propre bundle (register.js), plus riche.
 * - Prévention du double submit sur TOUS les formulaires de la page
 * - Indice visuel (non bloquant) sur le format de l'email pendant la saisie (login)
 * - Confort de saisie du code 2FA (chiffres uniquement, auto-soumission à 6 chiffres)
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
    // Anti double-submit générique : chaque formulaire de la page (il n'y en
    // a jamais qu'un en pratique) désactive son bouton et affiche un libellé
    // de chargement à la soumission. Le libellé vient de l'attribut
    // data-loading-label du bouton (posé côté template) pour rester propre à
    // chaque page ; à défaut, un libellé générique est utilisé.
    // -------------------------
    document.querySelectorAll("form").forEach((form) => {
        form.addEventListener("submit", () => {
            const submitBtn = form.querySelector("button[type='submit']");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add("opacity-60", "cursor-not-allowed");
                submitBtn.innerHTML =
                    submitBtn.dataset.loadingLabel || "⏳ Veuillez patienter...";
            }
        });
    });

    // -------------------------
    // Page de connexion : indice visuel (non bloquant) sur le format de l'email
    // -------------------------
    const emailInput = document.getElementById("inputEmail");
    const emailHelper = document.getElementById("emailHelper");

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
    // Page 2FA : le code n'a de sens qu'à 6 chiffres. On nettoie la saisie
    // (colle/clavier) et on soumet automatiquement une fois les 6 chiffres
    // atteints, pour éviter un clic superflu sur un code à usage unique et
    // court dans le temps.
    // -------------------------
    const authCodeInput = document.getElementById("_auth_code");
    const twoFactorForm = document.getElementById("twoFactorForm");

    if (authCodeInput) {
        authCodeInput.addEventListener("input", () => {
            const digitsOnly = authCodeInput.value.replace(/\D/g, "").slice(0, 6);
            if (digitsOnly !== authCodeInput.value) {
                authCodeInput.value = digitsOnly;
            }

            if (digitsOnly.length === 6 && twoFactorForm) {
                twoFactorForm.requestSubmit();
            }
        });
    }

    // -------------------------
    // Bandeau de marque : champ de particules ambiant (cf. _auth_hero.html.twig
    // + .auth-hero-canvas dans login.css). C'est la principale source d'énergie
    // du panneau — les dégradés/le grain en CSS donnent la profondeur, ce
    // canvas donne le mouvement réel. Purement décoratif (aria-hidden côté
    // template), jamais interactif. Désactivé si prefers-reduced-motion.
    // -------------------------
    initHeroParticles();
});

function initHeroParticles() {
    const canvas = document.querySelector(".auth-hero-canvas");
    if (!canvas) return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    const ctx = canvas.getContext("2d");
    const hero = canvas.parentElement;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);

    // Deux teintes tirées des vrais tokens de marque (--color-brand-accent /
    // --color-brand-primary), + une touche chaude corail/ambre volontairement
    // hors palette froide existante — l'étincelle au milieu du bleu plutôt
    // qu'un champ uniformément froid (cf. commentaire dans login.css).
    const rootStyles = getComputedStyle(document.documentElement);
    const hexToRgb = (hex, fallback) => {
        const clean = (hex || "").trim().replace("#", "");
        const full = clean.length === 3 ? clean.split("").map((c) => c + c).join("") : clean;
        const value = parseInt(full, 16);
        return Number.isNaN(value) ? fallback : `${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}`;
    };
    const COLORS = [
        hexToRgb(rootStyles.getPropertyValue("--color-brand-accent"), "0, 180, 216"),
        hexToRgb(rootStyles.getPropertyValue("--color-brand-primary"), "0, 119, 182"),
        "255, 148, 102",
    ];
    const COLOR_WEIGHTS = [0.55, 0.3, 0.15];
    const pickColor = () => {
        const roll = Math.random();
        let acc = 0;
        for (let i = 0; i < COLOR_WEIGHTS.length; i++) {
            acc += COLOR_WEIGHTS[i];
            if (roll <= acc) return COLORS[i];
        }
        return COLORS[0];
    };

    let width = 0;
    let height = 0;
    let particles = [];

    function resize() {
        width = hero.clientWidth;
        height = hero.clientHeight;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        // Densité proportionnelle à la surface du panneau, plafonnée pour
        // rester léger même sur un très grand écran.
        const count = Math.min(70, Math.round((width * height) / 13000));
        particles = Array.from({ length: count }, () => ({
            x: Math.random() * width,
            y: Math.random() * height,
            vx: (Math.random() - 0.5) * 0.22,
            vy: (Math.random() - 0.5) * 0.22,
            r: Math.random() * 1.6 + 0.6,
            color: pickColor(),
        }));
    }

    const LINK_DIST = 130;

    function step() {
        ctx.clearRect(0, 0, width, height);

        for (const p of particles) {
            p.x += p.vx;
            p.y += p.vy;
            if (p.x < 0) p.x = width;
            else if (p.x > width) p.x = 0;
            if (p.y < 0) p.y = height;
            else if (p.y > height) p.y = 0;
        }

        // Fils de constellation entre particules proches — visuellement
        // léger (faible opacité), mais c'est ce qui donne l'impression d'un
        // réseau vivant plutôt que de simples points qui flottent.
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const a = particles[i];
                const b = particles[j];
                const dx = a.x - b.x;
                const dy = a.y - b.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < LINK_DIST) {
                    ctx.strokeStyle = `rgba(${a.color}, ${0.14 * (1 - dist / LINK_DIST)})`;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.stroke();
                }
            }
        }

        for (const p of particles) {
            ctx.beginPath();
            ctx.fillStyle = `rgba(${p.color}, 0.85)`;
            ctx.shadowColor = `rgba(${p.color}, 0.9)`;
            ctx.shadowBlur = 6;
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
        }

        requestAnimationFrame(step);
    }

    resize();
    window.addEventListener("resize", resize);
    requestAnimationFrame(step);
}
