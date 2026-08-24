import { Controller } from '@hotwired/stimulus';

/**
 * Traduction en direct (Gemini, débouncée) entre les deux champs FR/EN d'une
 * paire de formulaire bilingue (cf. templates/components/FieldPair.html.twig).
 *
 * Le sens de traduction se déduit du champ qui a réellement reçu la frappe —
 * pas besoin de connaître "la langue active" pour ça : un champ <disabled>
 * n'émet jamais d'événement `input` utilisateur, donc seul le champ
 * réellement modifiable à cet instant peut déclencher une traduction.
 * L'activation/désactivation elle-même est pilotée globalement par
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
        this.frTarget.disabled = !frActive;
        this.enTarget.disabled = frActive;
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
                // (validation native, etc.) sans redéclencher notre propre
                // écouteur `input` (le champ cible est désactivé — un
                // `disabled` n'émet de toute façon jamais d'`input`).
                targetEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
        } catch {
            // Silencieux : la saisie manuelle reste toujours possible, et le
            // filet de sécurité côté serveur (ContentAutoTranslator, à
            // l'enregistrement du formulaire) rattrape une traduction ratée.
        } finally {
            targetEl.classList.remove('opacity-50');
        }
    }
}
