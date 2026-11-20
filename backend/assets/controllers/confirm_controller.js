import { Controller } from '@hotwired/stimulus';

/**
 * Confirmation native avant soumission d'un formulaire — remplace les
 * `onsubmit="return confirm('… {{ donnée }} …')"` inline (XSS stocké : le
 * navigateur décode les entités HTML de l'attribut AVANT d'exécuter le JS,
 * donc `'` échappé en `&#039;` par Twig redevenait une vraie apostrophe et
 * un nom d'utilisateur `x');alert(1);//` s'exécutait dans la session admin).
 *
 * Le message est lu depuis un attribut de données (`data-confirm-message-value`)
 * et passé tel quel à `window.confirm()` : il ne quitte jamais le statut de
 * DONNÉE, aucune concaténation dans du code n'a lieu.
 *
 * Usage :
 *   <form … data-controller="confirm" data-confirm-message-value="Supprimer {{ user.email }} ?">
 *
 * L'écouteur est posé sur le formulaire lui-même (phase de bouillonnement,
 * avant celui de Turbo sur `document`) : un refus appelle preventDefault(),
 * que Turbo respecte (FormSubmitObserver ignore les évènements déjà annulés).
 */
export default class extends Controller {
    static values = { message: String };

    connect() {
        this.ask = this.ask.bind(this);
        this.element.addEventListener('submit', this.ask);
    }

    disconnect() {
        this.element.removeEventListener('submit', this.ask);
    }

    ask(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }
}
