import { Controller } from '@hotwired/stimulus';

/**
 * Formulaire multi-étapes générique : une seule soumission finale, mais les
 * champs sont répartis en `fieldset[data-wizard-target="step"]` masqués via
 * l'attribut `hidden` (et non une classe CSS) — un champ `required` dans une
 * étape cachée est alors exclu de la validation native du navigateur, donc
 * "Suivant" ne peut valider/bloquer que les champs réellement visibles.
 */
export default class extends Controller {
    static targets = ['step', 'dot', 'prevBtn', 'nextBtn', 'submitBtn'];

    connect() {
        this.index = 0;
        this.render();
    }

    next() {
        const current = this.stepTargets[this.index];
        const invalid = current.querySelector(':invalid');
        if (invalid) {
            invalid.reportValidity();
            invalid.focus();
            return;
        }
        if (this.index < this.stepTargets.length - 1) {
            this.index += 1;
            this.render();
            this.element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    prev() {
        if (this.index > 0) {
            this.index -= 1;
            this.render();
            this.element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    render() {
        this.stepTargets.forEach((step, i) => {
            step.hidden = i !== this.index;
        });

        this.dotTargets.forEach((dot, i) => {
            dot.classList.toggle('bg-brand-primary', i <= this.index);
            dot.classList.toggle('text-white', i <= this.index);
            dot.classList.toggle('bg-gray-100', i > this.index);
            dot.classList.toggle('text-gray-400', i > this.index);
        });

        const isFirst = this.index === 0;
        const isLast = this.index === this.stepTargets.length - 1;
        this.prevBtnTarget.classList.toggle('invisible', isFirst);
        this.nextBtnTarget.classList.toggle('hidden', isLast);
        this.submitBtnTarget.classList.toggle('hidden', !isLast);
    }
}
