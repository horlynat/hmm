/**
 * @file dashboard.js
 * @description Gestion moderne et optimisée des interactions du tableau de bord admin, compatible Symfony UX + Turbo + Vite.
 * @author Horlynat MAMPASSI MBAMA
 * @version 1.1.0
 * @license MIT
 */

// =========================================================================
// 📌 VARIABLES GLOBALES
// =========================================================================

/**
 * @type {number|null}
 * @description Stocke l'ID du projet à supprimer (utilisé pour la confirmation de suppression).
 */
let currentDeleteId = null;

// =========================================================================
// 📌 FONCTIONS D'INITIALISATION
// =========================================================================

/**
 * Initialise les inputs cachés pour le tri et la pagination.
 * @function initHiddenInputs
 * @returns {void}
 */
function initHiddenInputs() {
    const form = document.getElementById("filter-form");
    if (!form) return;

    // Éviter de dupliquer les inputs si Turbo recharge l'élément
    if (!form.querySelector('input[name="sort"]')) {
        form.insertAdjacentHTML(
            "beforeend",
            `
            <input type="hidden" name="sort" value="${form.dataset.sort || "createdAt"}">
            <input type="hidden" name="direction" value="${form.dataset.direction || "desc"}">
            <input type="hidden" name="page" value="1">
        `,
        );
    }

    // Gérer la soumission du formulaire pour s'assurer que les valeurs par défaut sont présentes
    form.removeEventListener("submit", handleFormSubmit);
    form.addEventListener("submit", handleFormSubmit);
}

/**
 * Gestionnaire de soumission du formulaire de filtrage.
 * @function handleFormSubmit
 * @returns {void}
 */
function handleFormSubmit() {
    const form = document.getElementById("filter-form");
    if (!form) return;

    const sortInput = form.querySelector('input[name="sort"]');
    const directionInput = form.querySelector('input[name="direction"]');
    const pageInput = form.querySelector('input[name="page"]');

    if (sortInput && !sortInput.value) sortInput.value = "createdAt";
    if (directionInput && !directionInput.value) directionInput.value = "desc";
    if (pageInput && !pageInput.value) pageInput.value = 1;
}

/**
 * Masque le skeleton et affiche le contenu principal une fois la page chargée.
 * @function hideSkeleton
 * @returns {void}
 */
function hideSkeleton() {
    const loadingSkeleton = document.getElementById("loading-skeleton");
    const mainContent = document.getElementById("main-content");

    if (loadingSkeleton && mainContent) {
        loadingSkeleton.classList.add("hidden");
        mainContent.classList.remove("hidden");
    }
}

// =========================================================================
// 📌 FONCTIONS DE TOGGLE (AFFICHAGE/MASQUAGE)
// =========================================================================

/**
 * Affiche ou masque la sidebar de filtres (pour mobile).
 * @function toggleSidebar
 * @returns {void}
 */
function toggleSidebar() {
    const sidebar = document.getElementById("filter-sidebar");
    if (sidebar) {
        sidebar.classList.toggle("hidden");
    }
}

/**
 * Affiche ou masque les filtres avancés.
 * @function toggleAdvancedFilters
 * @returns {void}
 */
function toggleAdvancedFilters() {
    const advancedFilters = document.getElementById("advanced-filters");
    const showButton = document.getElementById("show-advanced-filters");

    if (advancedFilters && showButton) {
        if (advancedFilters.classList.contains("hidden")) {
            advancedFilters.classList.remove("hidden");
            showButton.classList.add("hidden");
        } else {
            advancedFilters.classList.add("hidden");
            showButton.classList.remove("hidden");
        }
    }
}

// =========================================================================
// 📌 FONCTIONS DE TRI ET FILTRAGE
// =========================================================================

/**
 * Trie le tableau par colonne.
 * @function sortTable
 * @param {string} column - La colonne à trier (ex: 'title', 'budget', 'createdAt').
 * @returns {void}
 */
function sortTable(column) {
    const form = document.getElementById("filter-form");
    if (!form) return;

    const sortInput = form.querySelector('input[name="sort"]');
    const directionInput = form.querySelector('input[name="direction"]');
    const pageInput = form.querySelector('input[name="page"]');

    if (!sortInput || !directionInput || !pageInput) {
        console.warn("Les champs de tri sont introuvables dans le formulaire.");
        return;
    }

    const currentSort = sortInput.value || "createdAt";
    const currentDirection = directionInput.value || "desc";

    // Mettre à jour les valeurs de tri
    sortInput.value = column;
    directionInput.value =
        currentSort === column && currentDirection === "asc" ? "desc" : "asc";

    // Réinitialiser la page à 1 lors d'un nouveau tri
    pageInput.value = 1;

    // Soumettre le formulaire
    form.submit();
}

// =========================================================================
// 📌 FONCTIONS DE GESTION DES MODALS
// =========================================================================

/**
 * Affiche le modal de confirmation de suppression.
 * @function confirmDelete
 * @param {number} projectId - L'ID du projet à supprimer.
 * @returns {void}
 */
function confirmDelete(projectId) {
    currentDeleteId = projectId;
    const deleteModal = document.getElementById("delete-modal");
    if (deleteModal) {
        deleteModal.classList.remove("hidden");
    }
}

/**
 * Ferme le modal de confirmation de suppression.
 * @function closeDeleteModal
 * @returns {void}
 */
function closeDeleteModal() {
    currentDeleteId = null;
    const deleteModal = document.getElementById("delete-modal");
    if (deleteModal) {
        deleteModal.classList.add("hidden");
    }
}

/**
 * Soumet le formulaire de suppression après confirmation.
 * @function submitDeleteForm
 * @returns {void}
 */
function submitDeleteForm() {
    if (currentDeleteId) {
        const deleteForm = document.getElementById(
            `delete-form-${currentDeleteId}`,
        );
        if (deleteForm) {
            deleteForm.submit();
            closeDeleteModal();
        }
    }
}

// =========================================================================
// 📌 AUTOMATISATION & ÉCOUTEURS D'ÉVÉNEMENTS DYNAMIQUES
// =========================================================================

/**
 * Initialise les écouteurs d'événements dynamiques liés aux éléments Twig.
 * @function initDynamicListeners
 * @returns {void}
 */
function initDynamicListeners() {
    // 1. Bouton mobile pour afficher/masquer la sidebar de filtres [data-toggle-sidebar]
    const toggleSidebarBtn = document.querySelector("[data-toggle-sidebar]");
    if (toggleSidebarBtn) {
        toggleSidebarBtn.removeEventListener("click", toggleSidebar);
        toggleSidebarBtn.addEventListener("click", toggleSidebar);
    }

    // 2. Clics sur les en-têtes de colonnes triables [data-sort-col]
    document.querySelectorAll("[data-sort-col]").forEach((header) => {
        header.removeEventListener("click", handleSortClick);
        header.addEventListener("click", handleSortClick);
    });

    // 3. Clics sur les cartes de filtres de statut rapides [data-status-filter]
    document.querySelectorAll("[data-status-filter]").forEach((card) => {
        card.removeEventListener("click", handleStatusFilterClick);
        card.addEventListener("click", handleStatusFilterClick);
    });

    // 4. Modale de suppression (Boutons Annuler et Fermer)
    const closeBtns = document.querySelectorAll(".close-modal-btn");
    closeBtns.forEach((btn) => {
        btn.removeEventListener("click", closeDeleteModal);
        btn.addEventListener("click", closeDeleteModal);
    });

    const confirmDeleteBtn = document.getElementById("confirm-delete");
    if (confirmDeleteBtn) {
        confirmDeleteBtn.removeEventListener("click", submitDeleteForm);
        confirmDeleteBtn.addEventListener("click", submitDeleteForm);
    }

    const deleteModal = document.getElementById("delete-modal");
    if (deleteModal) {
        deleteModal.removeEventListener("click", handleModalOutsideClick);
        deleteModal.addEventListener("click", handleModalOutsideClick);
    }
}

/**
 * Handler pour le clic sur les en-têtes de colonne.
 * @this HTMLElement
 */
function handleSortClick() {
    const column = this.getAttribute("data-sort-col");
    if (column) sortTable(column);
}

/**
 * Handler pour le filtre de statut rapide depuis les cartes.
 * @this HTMLElement
 */
function handleStatusFilterClick() {
    const statusValue = this.getAttribute("data-status-filter");
    const statusSelect = document.getElementById("status");
    const filterForm = document.getElementById("filter-form");

    if (statusSelect && filterForm && statusValue) {
        statusSelect.value = statusValue;
        filterForm.submit();
    }
}

/**
 * Handler pour fermer la modale si on clique sur l'arrière-plan.
 * @param {MouseEvent} e
 */
function handleModalOutsideClick(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
}

// =========================================================================
// 📌 EXPOSITION GLOBALE POUR CONVENANCE VITE / INLINE TWIG
// =========================================================================
// En raison de l'isolation d'ES Modules de Vite, les fonctions utilisées
// dans les anciens templates Twig (ex: onclick="confirmDelete(...)")
// doivent être attachées à l'objet global window.
window.sortTable = sortTable;
window.confirmDelete = confirmDelete;
window.closeDeleteModal = closeDeleteModal;
window.submitDeleteForm = submitDeleteForm;
window.toggleSidebar = toggleSidebar;
window.toggleAdvancedFilters = toggleAdvancedFilters;

// =========================================================================
// 📌 INITIALISATION DE LA PAGE (TURBO / CLASSIQUE)
// =========================================================================

/**
 * Initialise la page une fois le DOM prêt ou actualisé par Turbo.
 * @function initPage
 * @returns {void}
 */
function initPage() {
    hideSkeleton();
    initHiddenInputs();
    initDynamicListeners();
}

// 1. Écouteur standard pour le premier chargement classique de la page
document.addEventListener("DOMContentLoaded", initPage);

document.addEventListener("DOMContentLoaded", function () {
    // Soumission automatique lors des changements de tris rapides
    const autoSubmitSelectors = document.querySelectorAll(
        ".auto-submit-trigger",
    );
    autoSubmitSelectors.forEach((select) => {
        select.addEventListener("change", () => {
            document.getElementById("filter-sidebar-form").submit();
        });
    });
});

// 2. Écouteur crucial pour les rechargements AJAX instantanés de Symfony Turbo
document.addEventListener("turbo:load", initPage);

// 3. Fermer les modales ouvertes avant la mise en cache de Turbo (évite les bugs d'historique)
document.addEventListener("turbo:before-cache", () => {
    closeDeleteModal();
});
