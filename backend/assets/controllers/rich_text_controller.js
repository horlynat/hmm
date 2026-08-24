import { Controller } from '@hotwired/stimulus';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

/**
 * Éditeur riche (Quill) au-dessus d'un <textarea> Symfony existant — le
 * textarea reste la source de vérité soumise au serveur (progressive
 * enhancement : sans JS, l'admin retombe sur un textarea HTML brut, toujours
 * fonctionnel). Utilisé pour les champs "corps de texte" qui doivent
 * permettre une hiérarchie (titres, listes, citations…) — cf. Article
 * content/contentEn, Project description/descriptionEn — PAS les champs
 * "une ligne par entrée" du wizard projet (techStack, objectives…), qui
 * restent des textareas simples.
 *
 * Barre d'outils volontairement limitée à ce que
 * App\Service\ContentAutoTranslator + frontend/src/lib/sanitize.ts
 * (sanitizeArticleHtml) savent traduire et rendre : h2/h3/h4, gras/italique/
 * souligné/barré, citation, bloc de code, listes, lien. Pas d'upload d'image
 * dans l'éditeur — la couverture a déjà son propre champ dédié.
 */
export default class extends Controller {
    static targets = ['source', 'editor'];
    static values = { placeholder: String, minHeight: String };

    connect() {
        this.quill = new Quill(this.editorTarget, {
            theme: 'snow',
            placeholder: this.hasPlaceholderValue ? this.placeholderValue : '',
            modules: {
                toolbar: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean'],
                ],
            },
        });

        if (this.hasMinHeightValue) {
            this.editorTarget.querySelector('.ql-editor').style.minHeight = this.minHeightValue;
        }

        // Contenu existant (HTML déjà en base, ex. édition d'un article) —
        // converti en Delta interne à Quill pour devenir éditable.
        if ('' !== this.sourceTarget.value.trim()) {
            this.quill.clipboard.dangerouslyPasteHTML(this.sourceTarget.value);
        }

        this.syncToSource = this.syncToSource.bind(this);
        this.quill.on('text-change', this.syncToSource);

        // Filet de sécurité : si le formulaire est soumis sans qu'aucune
        // frappe n'ait eu lieu depuis le chargement (contenu prérempli jamais
        // retouché), le textarea contient déjà la bonne valeur d'origine —
        // mais on resynchronise quand même pour ne dépendre d'aucun ordre
        // d'événements particulier.
        this.form = this.element.closest('form');
        this.form?.addEventListener('submit', this.syncToSource);
    }

    disconnect() {
        this.form?.removeEventListener('submit', this.syncToSource);
        this.quill = null;
    }

    syncToSource() {
        const html = 'function' === typeof this.quill.getSemanticHTML
            ? this.quill.getSemanticHTML()
            : this.quill.root.innerHTML;
        // Quill représente un éditeur vide par "<p><br></p>" — on retombe sur
        // une chaîne vide plutôt que d'enregistrer ce balisage vide en base.
        this.sourceTarget.value = this.quill.getLength() > 1 ? html : '';
        this.sourceTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
