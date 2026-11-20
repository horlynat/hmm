import { Controller } from '@hotwired/stimulus';

/**
 * Ouverture/fermeture des modales natives <dialog> — remplace les
 * `onclick="document.getElementById('delete-modal-{{ id }}').showModal()"`
 * inline : l'identifiant de la modale reste une donnée (paramètre d'action
 * Stimulus), jamais interpolé dans du code JS.
 *
 * Usage :
 *   <button type="button" data-controller="dialog" data-action="dialog#open"
 *           data-dialog-id-param="delete-modal-{{ id }}">…</button>
 *   <button type="button" data-controller="dialog" data-action="dialog#close"
 *           data-dialog-id-param="delete-modal-{{ id }}">Annuler</button>
 * Sans paramètre, `close` ferme le <dialog> qui contient le bouton.
 */
export default class extends Controller {
    open({ params }) {
        const dialog = params.id ? document.getElementById(params.id) : null;
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }
    }

    close({ params }) {
        const dialog = params.id ? document.getElementById(params.id) : this.element.closest('dialog');
        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
        }
    }
}
