// assets/controllers/tabs_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["button", "content"];

    connect() {
        // Masquer tous les contenus sauf le premier
        this.contentTargets.forEach((content, index) => {
            if (index > 0) content.classList.add("hidden");
        });
    }

    switch(event) {
        const tabName = event.currentTarget.dataset.tabName;

        // Désactiver tous les boutons
        this.buttonTargets.forEach((button) => {
            button.classList.remove(
                "border-indigo-500",
                "text-indigo-600",
                "dark:text-indigo-400",
            );
            button.classList.add(
                "border-transparent",
                "text-gray-500",
                "dark:text-gray-400",
            );
        });

        // Activer le bouton cliqué
        event.currentTarget.classList.add(
            "border-indigo-500",
            "text-indigo-600",
            "dark:text-indigo-400",
        );
        event.currentTarget.classList.remove(
            "border-transparent",
            "text-gray-500",
            "dark:text-gray-400",
        );

        // Masquer tous les contenus
        this.contentTargets.forEach((content) => {
            content.classList.add("hidden");
        });

        // Afficher le contenu correspondant
        const content = this.contentTargets.find(
            (c) => c.dataset.tabName === tabName,
        );
        if (content) content.classList.remove("hidden");
    }
}
