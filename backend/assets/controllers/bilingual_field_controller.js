import { Controller } from '@hotwired/stimulus';

/**
 * Traduction en direct (Gemini, débouncée) entre les deux champs FR/EN d'une
 * paire de formulaire bilingue (cf. templates/components/FieldPair.html.twig).
 *
 * Le sens de traduction se déduit du champ qui a réellement reçu la frappe —
 * pas besoin de connaître "la langue active" pour ça : un champ `readonly`
 * n'émet jamais d'événement `input` utilisateur, donc seul le champ
 * réellement modifiable à cet instant peut déclencher une traduction.
 *
 * La colonne non choisie est verrouillée en lecture seule (`readonly`), pas
 * `disabled` : un champ `disabled` n'est jamais envoyé à la soumission du
 * formulaire — Symfony traite alors ce champ comme "vidé" et écrase la valeur
 * existante en base à chaque enregistrement fait dans l'autre langue. `readonly`
 * bloque la saisie manuelle tout aussi bien, sans ce risque de perte de
 * contenu. L'activation/désactivation est pilotée globalement par
 * locale_toggle_controller.js via l'événement `admin:locale-changed`.
 */
export default class extends Controller {
    static targets = ['fr', 'en'];
    static values = { debounce: { type: Number, default: 900 } };

    connect() {
        this.timer = null;
        this.onLocaleChanged = this.onLocaleChanged.bind(this);
        document.addEventListener('admin:locale-changed', this.onLocaleChanged);
        // Réplique l'état déjà choisi par la bascule globale (si elle a déjà
        // émis avant que ce champ ne soit connecté, ex. dans un panneau
        // affiché plus bas sur la page).
        this.applyLocale(document.documentElement.dataset.adminLocale || 'fr');
    }

    disconnect() {
        document.removeEventListener('admin:locale-changed', this.onLocaleChanged);
        window.clearTimeout(this.timer);
    }

    onLocaleChanged(event) {
        this.applyLocale(event.detail.locale);
    }

    applyLocale(locale) {
        const frActive = 'fr' === locale;
        this.frTarget.readOnly = !frActive;
        this.enTarget.readOnly = frActive;
        this.frTarget.classList.toggle('input-disabled', !frActive);
        this.enTarget.classList.toggle('input-disabled', frActive);
    }

    scheduleFromFr() {
        this.schedule(this.frTarget, this.enTarget, 'en');
    }

    scheduleFromEn() {
        this.schedule(this.enTarget, this.frTarget, 'fr');
    }

    schedule(sourceEl, targetEl, targetLocale) {
        window.clearTimeout(this.timer);
        const text = sourceEl.value.trim();
        if ('' === text) {
            return;
        }
        this.timer = window.setTimeout(() => this.translate(text, targetLocale, targetEl), this.debounceValue);
    }

    async translate(text, targetLocale, targetEl) {
        targetEl.classList.add('opacity-50');
        try {
            const response = await fetch('/admin/translate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text, targetLocale }),
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            if ('string' === typeof data.translated && '' !== data.translated) {
                targetEl.value = data.translated;
                // Signale la mise à jour aux autres écouteurs éventuels
                // (rich_text_controller.js notamment, cf. reloadFromSource) via
                // `change`, pas `input` — on ne veut surtout pas redéclencher
                // notre propre écouteur `input->bilingual-field#schedule...` ici.
                targetEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
        } catch {
            // Silencieux : la saisie manuelle reste toujours possible — aucun
            // filet de traduction automatique côté serveur à l'enregistrement,
            // le champ reste simplement à compléter à la main si l'appel échoue.
        } finally {
            targetEl.classList.remove('opacity-50');
        }
    }
}
