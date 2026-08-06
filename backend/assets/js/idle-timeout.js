// Déconnexion automatique après 10 min sans clic. Le minuteur et l'écouteur
// sont posés une seule fois au chargement du module (pas dans un callback
// "turbo:load", pour ne pas en empiler un nouveau à chaque navigation Turbo).
// Le cookie posé avant la redirection vers /logout permet à
// SecurityAuthenticator de ramener l'utilisateur sur cette page une fois
// reconnecté (voir src/Security/SecurityAuthenticator.php).

const IDLE_DELAY_MS = 10 * 60 * 1000;
const RETURN_TO_COOKIE = "idle_return_to";

let idleTimer = null;

function onIdle() {
    const returnTo = encodeURIComponent(location.pathname + location.search);
    const secure = location.protocol === "https:" ? "; secure" : "";
    document.cookie = `${RETURN_TO_COOKIE}=${returnTo}; path=/; max-age=300; samesite=strict${secure}`;
    window.location.assign("/logout");
}

function resetIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(onIdle, IDLE_DELAY_MS);
}

document.addEventListener("click", resetIdleTimer);
resetIdleTimer();
