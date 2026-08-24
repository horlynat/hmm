import { Controller } from '@hotwired/stimulus';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

// Force l'alignement en classes (ql-align-center...) plutôt qu'en style
// inline (text-align: ...) — frontend/src/lib/sanitize.ts n'autorise aucun
// attribut `style` libre, seulement `class` (déjà passant par la liste
// blanche générique "*": ["class"]). Sans ce réenregistrement, Quill utilise
// par défaut l'attributor "style" pour ce format, et l'alignement choisi
// disparaîtrait silencieusement au rendu public.
Quill.register(Quill.import('attributors/class/align'), true);

/**
 * Éditeur riche (Quill) au-dessus d'un <textarea> Symfony existant — le
 * textarea reste la source de vérité soumise au serveur (progressive
 * enhancement : sans JS, l'admin retombe sur un textarea HTML brut, toujours
 * fonctionnel). Utilisé pour les champs "corps de texte" qui doivent
 * permettre une hiérarchie (titres, listes, citations…) — cf. Article
 * content/contentEn, Project description/descriptionEn (formulaire de
 * modification ET assistant de création — même dispositif partout, cf.
 * data-rich-text-min-height-value pour la seule variation, la hauteur).
 *
 * Barre d'outils alignée avec ce que frontend/src/lib/sanitize.ts
 * (sanitizeArticleHtml) sait préserver au rendu : h1-h4, gras/italique/
 * souligné/barré, citation, bloc de code, listes, alignement, lien, image.
 * Volontairement absents : couleurs/surlignage libres (écriraient un style
 * inline que la liste blanche retire intégralement — l'admin verrait sa
 * mise en forme disparaître silencieusement à la publication) et tableaux
 * (aucun module de table stable dans Quill 2 open-source sans dépendance
 * tierce peu maintenue) — à ajouter explicitement si le besoin se confirme,
 * pas par défaut.
 */
export default class extends Controller {
    static targets = ['source', 'editor'];
    static values = { placeholder: String, minHeight: String };

    connect() {
        this.quill = new Quill(this.editorTarget, {
            theme: 'snow',
            placeholder: this.hasPlaceholderValue ? this.placeholderValue : '',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [1, 2, 3, 4, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote', 'code-block'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ align: [] }],
                        ['link', 'image'],
                        ['clean'],
                    ],
                    handlers: { image: () => this.pickImage() },
                },
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

    /** Ouvre un sélecteur de fichier, uploade vers le serveur, insère l'URL renvoyée au curseur. */
    pickImage() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/webp,image/gif';
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (file) {
                this.uploadImage(file);
            }
        });
        input.click();
    }

    async uploadImage(file) {
        const range = this.quill.getSelection(true);
        const body = new FormData();
        body.append('image', file);

        try {
            const response = await fetch('/admin/rich-text/upload-image', { method: 'POST', body });
            const data = await response.json();
            if (!response.ok || 'string' !== typeof data.url) {
                window.alert(data.error ?? 'Échec de l\'envoi de l\'image.');
                return;
            }
            this.quill.insertEmbed(range.index, 'image', data.url, 'user');
            this.quill.setSelection(range.index + 1, 0, 'user');
        } catch {
            window.alert('Échec de l\'envoi de l\'image — connexion indisponible.');
        }
    }
}
