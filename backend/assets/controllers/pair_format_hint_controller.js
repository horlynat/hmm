import { Controller } from '@hotwired/stimulus';

/**
 * Retour visuel immédiat (pas d'aller-retour serveur) sur le format
 * "Défi | Solution" par ligne attendu par ce champ — même règle que
 * AdminProjectController::findLinesWithoutSeparator(), qui la fait
 * respecter côté serveur au moment de l'enregistrement (dernier filet,
 * pas le seul). Ajouté après un cas réel constaté deux fois en prod :
 * le champ acceptait silencieusement une ligne sans "|", laissant la
 * partie "Solution" vide sans que rien ne le signale avant la
 * publication.
 *
 * Ne bloque rien ici — seulement un avertissement pendant la frappe,
 * pour qu'il soit vu avant de cliquer sur "Enregistrer", pas après.
 */
export default class extends Controller {
    static targets = ['input', 'hint'];

    connect() {
        this.check();
    }

    check() {
        const lines = this.inputTarget.value
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter((line) => '' !== line);
        const missing = lines.filter((line) => !line.includes('|')).length;

        if (missing > 0) {
            const plural = missing > 1 ? 's' : '';
            this.hintTarget.textContent =
                `⚠ ${missing} ligne${plural} sans séparateur « | » — la partie après le « | » restera vide sans lui.`;
            this.hintTarget.classList.remove('hidden');
            this.inputTarget.classList.add('border-amber-400', 'dark:border-amber-600');
        } else {
            this.hintTarget.classList.add('hidden');
            this.inputTarget.classList.remove('border-amber-400', 'dark:border-amber-600');
        }
    }
}
