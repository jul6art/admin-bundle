/**
 * Le préréglage Tailwind de la coquille.
 *
 *   // tailwind.config.js
 *   module.exports = {
 *       presets: [require('./vendor/jul6art/admin-bundle/assets/tailwind/preset.js')],
 *       content: ['./templates/**\/*.html.twig', './assets/**\/*.js', ...bundleContentPaths()],
 *   };
 *
 * Il ne déclare que ce dont les feuilles du bundle ont besoin. Le `content`, lui, reste à
 * l'application : c'est elle qui sait où vivent ses gabarits, et un préréglage qui le fixerait
 * casserait tout projet rangé autrement.
 *
 * ⚠️ `darkMode: 'class'` et non `'media'`. Le mode est une préférence de COMPTE, résolue côté
 * serveur et écrite sur `<html>` ; `'media'` la rendrait au système d'exploitation et le réglage
 * n'aurait plus d'effet.
 */
module.exports = {
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                panel: '0 1px 4px rgba(0, 0, 0, 0.06), 0 4px 16px rgba(0, 0, 0, 0.06)',
            },
            // L'accent par compte. Les triplets RGB réels sont portés par les variables
            // `--accent-*` de `styles/tokens.css`, échangées par l'attribut `data-accent` sur
            // `<html>`. `<alpha-value>` est ce qui garde `bg-accent-500/10` fonctionnel.
            colors: {
                accent: {
                    50: 'rgb(var(--accent-50) / <alpha-value>)',
                    100: 'rgb(var(--accent-100) / <alpha-value>)',
                    200: 'rgb(var(--accent-200) / <alpha-value>)',
                    300: 'rgb(var(--accent-300) / <alpha-value>)',
                    400: 'rgb(var(--accent-400) / <alpha-value>)',
                    500: 'rgb(var(--accent-500) / <alpha-value>)',
                    600: 'rgb(var(--accent-600) / <alpha-value>)',
                    700: 'rgb(var(--accent-700) / <alpha-value>)',
                    800: 'rgb(var(--accent-800) / <alpha-value>)',
                    900: 'rgb(var(--accent-900) / <alpha-value>)',
                },
            },
        },
    },
};
