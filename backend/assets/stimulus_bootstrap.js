import { startStimulusApp, registerControllers } from "vite-plugin-symfony/stimulus/helpers";

// Démarre Stimulus ET enregistre les contrôleurs tiers déclarés dans
// controllers.json (dont @symfony/ux-live-component → identifiant « live »,
// indispensable aux actions data-action="live#action" : approbation, refus,
// suppression de dépenses, etc.). Sans cet appel, aucune action Live ne
// fonctionne — seuls les liens/formulaires HTML classiques répondent.
const app = startStimulusApp();

// Contrôleurs Stimulus locaux du projet (assets/controllers/*_controller.js).
registerControllers(
    app,
    import.meta.glob("./controllers/*_controller.js", {
        query: "?stimulus",
        eager: true,
    }),
);

export { app };
