/**
 * dashboard.js
 * Initialise les graphiques du tableau de bord admin (Chart.js) à partir
 * des données injectées côté Twig dans `window.dashboardData`.
 *
 * Prérequis :
 *   npm install chart.js
 *
 * Ce fichier est conçu pour être un entrypoint Vite dédié, chargé uniquement
 * sur la page qui contient les canvas #projectsByStatusChart et
 * #expensesByMonthChart (voir index.html.twig). Toutes les fonctions sont
 * défensives : si un canvas ou une donnée est absent, elles ne font rien
 * plutôt que de lever une erreur.
 */

import Chart from "chart.js/auto";

/** Rotation de couleurs basée sur les tokens définis dans app.css (@theme) */
const STATUS_COLOR_TOKENS = [
    "--color-brand-primary",
    "--color-success",
    "--color-warning",
    "--color-danger",
    "--color-info",
    "--color-brand-accent",
];

/**
 * Lit une variable CSS custom déjà résolue par le navigateur
 * (donc automatiquement correcte en dark mode grâce à la classe `.dark`).
 */
function cssVar(name, fallback = "#9CA3AF") {
    const value = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();
    return value || fallback;
}

/**
 * Normalise `projectsByStatus` (objet clé => { label, count, badgeClass })
 * en tableau exploitable par Chart.js.
 */
function normalizeProjectsByStatus(raw) {
    if (!raw) return [];
    return Object.values(raw).map((entry) => ({
        label: entry.label ?? "—",
        count: Number(entry.count ?? 0),
    }));
}

/**
 * Normalise `expensesByMonth`, qu'il soit fourni sous forme d'objet
 * ({ "2026-01": 1234.56 }) ou de tableau ([{ month, total }]).
 */
function normalizeExpensesByMonth(raw) {
    if (!raw) return [];

    if (Array.isArray(raw)) {
        return raw.map((entry) => ({
            label: entry.month ?? entry.label ?? "",
            total: Number(entry.total ?? entry.amount ?? 0),
        }));
    }

    return Object.entries(raw).map(([month, total]) => ({
        label: month,
        total: Number(total ?? 0),
    }));
}

/** Instances Chart.js actives, conservées pour pouvoir les détruire proprement
 *  entre deux navigations Turbo (sinon Chart.js lève une erreur "Canvas is
 *  already in use" quand Turbo réutilise le même <canvas>). */
let projectsByStatusChart = null;
let expensesByMonthChart = null;

function destroyDashboardCharts() {
    if (projectsByStatusChart) {
        projectsByStatusChart.destroy();
        projectsByStatusChart = null;
    }
    if (expensesByMonthChart) {
        expensesByMonthChart.destroy();
        expensesByMonthChart = null;
    }
}

function buildProjectsByStatusChart(canvas, rawData) {
    const entries = normalizeProjectsByStatus(rawData);
    if (!canvas || entries.length === 0) return;

    projectsByStatusChart = new Chart(canvas, {
        type: "doughnut",
        data: {
            labels: entries.map((entry) => entry.label),
            datasets: [
                {
                    data: entries.map((entry) => entry.count),
                    backgroundColor: entries.map((_, index) =>
                        cssVar(
                            STATUS_COLOR_TOKENS[
                                index % STATUS_COLOR_TOKENS.length
                            ],
                        ),
                    ),
                    borderWidth: 0,
                    hoverOffset: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "bottom",
                    labels: { boxWidth: 12, padding: 16 },
                },
            },
        },
    });
}

function buildExpensesByMonthChart(canvas, rawData) {
    const entries = normalizeExpensesByMonth(rawData);
    if (!canvas || entries.length === 0) return;

    expensesByMonthChart = new Chart(canvas, {
        type: "bar",
        data: {
            labels: entries.map((entry) => entry.label),
            datasets: [
                {
                    label: "Dépenses",
                    data: entries.map((entry) => entry.total),
                    backgroundColor: cssVar("--color-brand-primary"),
                    borderRadius: 6,
                    maxBarThickness: 40,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) =>
                            new Intl.NumberFormat("fr-FR", {
                                style: "currency",
                                currency: "EUR",
                            }).format(context.parsed.y ?? 0),
                    },
                },
            },
            scales: {
                y: { beginAtZero: true },
            },
        },
    });
}

function initDashboard() {
    // Toujours repartir d'un état propre : évite les doublons/erreurs de
    // canvas quand Turbo revisite la page (retour arrière, navigation, etc.)
    destroyDashboardCharts();

    const data = window.dashboardData;
    if (!data) return;

    buildProjectsByStatusChart(
        document.getElementById("projectsByStatusChart"),
        data.projectsByStatus,
    );

    buildExpensesByMonthChart(
        document.getElementById("expensesByMonthChart"),
        data.expensesByMonth,
    );
}

// Turbo ne redéclenche pas DOMContentLoaded lors des navigations SPA-like :
// on s'aligne sur turbo:load, comme le reste de app.js.
document.addEventListener("turbo:load", initDashboard);

// Avant que Turbo ne mette la page en cache (retour arrière), on détruit les
// charts pour ne pas restaurer un <canvas> lié à une instance Chart.js morte.
document.addEventListener("turbo:before-cache", destroyDashboardCharts);
