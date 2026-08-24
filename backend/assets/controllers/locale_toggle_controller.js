import { Controller } from '@hotwired/stimulus';

/**
 * Bascule globale FR/EN pour un formulaire bilingue (page complète, un seul
 * choix de langue à la fois — cf. templates/components/LocaleToggle.html.twig).
 * Diffuse `admin:locale-changed`, écouté par chaque bilingual_field_controller
 * de la page pour activer/désactiver la colonne non choisie.
 */
export default class extends Controller {
    static targets = ['frBtn', 'enBtn'];

    connect() {
        this.set(document.documentElement.dataset.adminLocale || 'fr');
    }

    chooseFr() {
        this.set('fr');
    }

    chooseEn() {
        this.set('en');
    }

    set(locale) {
        document.documentElement.dataset.adminLocale = locale;
        this.frBtnTarget.classList.toggle('btn-primary', 'fr' === locale);
        this.frBtnTarget.classList.toggle('btn-secondary', 'fr' !== locale);
        this.enBtnTarget.classList.toggle('btn-primary', 'en' === locale);
        this.enBtnTarget.classList.toggle('btn-secondary', 'en' !== locale);
        document.dispatchEvent(new CustomEvent('admin:locale-changed', { detail: { locale } }));
    }
}
