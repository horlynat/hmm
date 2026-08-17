import { defineConfig } from "vite";
import symfonyPlugin from "vite-plugin-symfony";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        tailwindcss(),
        symfonyPlugin({
            stimulus: true, // Active Stimulus
        }),
    ],
    build: {
        rollupOptions: {
            input: {
                app: "./assets/app.js",
                login: "./assets/js/login.js",
                register: "./assets/js/register.js",
                dashboard: "./assets/js/dashboard.js",
                project: "./assets/js/project.js",
                profile: "./assets/js/profile.js",
            },
        },
        manifest: true,
        outDir: "public/build",
        emptyOutDir: true,
    },
    server: {
        watch: {
            // Surveille les fichiers dans le dossier assets/
            usePolling: true,
        },
    },
    optimizeDeps: {
        exclude: [
            "@hotwired/stimulus", // Désactive le pré-bundling pour Stimulus
        ],
    },
});
