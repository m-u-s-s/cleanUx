/**
 * Tâches lancées sur les fichiers indexés avant chaque commit : husky → .husky/pre-commit → lint-staged.
 *
 * Ce fichier remplace le bloc « lint-staged » de package.json, dont la tâche mobile ne pouvait
 * qu'échouer :
 * - `npx tsc` partait de la racine, où TypeScript n'est pas installé : npx y résout le paquet npm
 *   « tsc », un leurre qui répond « This is not the tsc command you are looking for » ;
 * - lint-staged ajoute les fichiers indexés à la fin de la commande, et tsc les refuse à côté de
 *   `--project` (error TS5042).
 *
 * Une fonction choisit la commande sans lui passer les fichiers : chaque application touchée
 * vérifie ses types avec son propre tsconfig et ses propres alias.
 */

import path from 'node:path';

const MOBILE_APPS = ['client', 'provider'];

/**
 * Applications dont les types dépendent des fichiers indexés. Un fichier propre à une application
 * ne vérifie qu'elle ; shared/ et le reste de mobile/ servent aux deux.
 *
 * lint-staged transmet des chemins absolus : on les rapporte à la racine du dépôt (le hook s'y
 * exécute) avant de les comparer. Sinon un dépôt cloné sous un dossier parent nommé
 * « …/mobile/client/… » ferait passer un fichier du prestataire pour un fichier du client, et le
 * typecheck du prestataire sauterait sans rien dire.
 */
function mobileAppsTouched(files) {
    const apps = new Set();

    for (const file of files) {
        const fromRoot = path.relative(process.cwd(), file).replaceAll('\\', '/');
        const own = MOBILE_APPS.find((app) => fromRoot.startsWith(`mobile/${app}/`));

        if (own) {
            apps.add(own);
        } else {
            MOBILE_APPS.forEach((app) => apps.add(app));
        }
    }

    return [...apps];
}

export default {
    '*.php': 'vendor/bin/pint',
    'mobile/**/*.{ts,tsx}': (files) =>
        mobileAppsTouched(files).map((app) => `npm --prefix mobile/${app} run typecheck`),
    'resources/js/**/*.js': ['eslint --fix', 'prettier --write'],
    'resources/css/**/*.css': 'prettier --write',
};
