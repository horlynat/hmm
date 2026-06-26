const Encore = require("@symfony/webpack-encore");
const CompressionPlugin = require("compression-webpack-plugin");
const { BundleAnalyzerPlugin } = require("webpack-bundle-analyzer");
const Dotenv = require("dotenv-webpack");

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || "dev");
}

Encore
    /*
     * 📂 Sortie des fichiers compilés
     */
    .setOutputPath("public/build/")
    .setPublicPath("/build")
    .setManifestKeyPrefix("build/")

    /*
     * 🎯 Entrées principales
     */
    .addEntry("app", "./assets/app.js")

    /*
     * ⚡ Optimisations
     */
    .splitEntryChunks()
    .enableSingleRuntimeChunk()

    /*
     * 🛠️ Symfony UX
     */
    .enableStimulusBridge("./assets/controllers.json")

    /*
     * 🧹 Nettoyage et notifications
     */
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    /*
     * 📦 Babel et polyfills
     */
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = "usage";
        config.corejs = "3.38";
    })

    /*
     * 🎨 CSS / PostCSS / Sass
     */
    .enablePostCssLoader()
    .enableSassLoader()

    /*
     * 🔒 Sécurité et intégrité
     */
    .enableIntegrityHashes(Encore.isProduction())

    /*
     * 🖼️ Gestion des assets
     */
    // .enableImageLoader()
    // .enableFontLoader()
    .copyFiles({
        from: "./assets/images",
        to: "images/[path][name].[hash:8].[ext]",
    })
    .copyFiles({
        from: "./assets/fonts",
        to: "fonts/[path][name].[hash:8].[ext]",
    });

const config = Encore.getWebpackConfig();

/*
 * 🔧 Plugins supplémentaires
 */
if (Encore.isProduction()) {
    config.plugins.push(
        new CompressionPlugin({
            algorithm: "brotliCompress",
            test: /\.(js|css|html|svg)$/,
        }),
    );
    config.plugins.push(new Dotenv());
}

// Active l’analyse du bundle si besoin
if (process.env.ANALYZE) {
    config.plugins.push(new BundleAnalyzerPlugin());
}

module.exports = config;
