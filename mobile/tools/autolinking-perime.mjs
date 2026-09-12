/**
 * LE CACHE D'AUTOLINKING SURVIT AU DÉPLACEMENT DES PAQUETS, ET LA COMPILATION ÉCHOUE AILLEURS.
 *
 * Gradle mémorise le résultat de l'autolinking dans `android/build/generated/autolinking/`, avec
 * des chemins ABSOLUS. Dans un espace de travail npm, c'est npm qui décide si un paquet atterrit
 * dans `client/node_modules` ou à la racine `mobile/node_modules` — et il change d'avis d'une
 * installation à l'autre. Le cache pointe alors sur un dossier disparu.
 *
 * Ce que ça donne : « Configuring project ':react-native-safe-area-context' without an existing
 * directory is not allowed ». L'erreur nomme Gradle et un paquet, jamais npm ni le cache — on
 * cherche donc du côté de la dépendance, qui est parfaitement installée.
 *
 * Ce garde ne nettoie QUE si un chemin mémorisé n'existe plus. Un cache valide n'est jamais
 * touché : la compilation native coûte plusieurs minutes, la jeter à chaque lancement serait un
 * remède pire que le mal.
 */
import { existsSync, readFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';

const RACINE = process.cwd();
const CACHE = join(RACINE, 'android', 'build', 'generated', 'autolinking', 'autolinking.json');

/* Les caches qui portent des chemins absolus. `.cxx` en contient aussi, dans ses fichiers CMake. */
const A_JETER = [
  join(RACINE, 'android', 'build', 'generated', 'autolinking'),
  join(RACINE, 'android', 'app', 'build', 'generated', 'autolinking'),
  join(RACINE, 'android', 'app', '.cxx'),
];

if (!existsSync(CACHE)) {
  process.exit(0);
}

let dependances;

try {
  dependances = JSON.parse(readFileSync(CACHE, 'utf8')).dependencies ?? {};
} catch {
  // Un cache illisible est un cache à refaire, pas une raison d'interrompre la compilation.
  dependances = null;
}

const disparus = dependances
  ? Object.entries(dependances)
      .map(([nom, paquet]) => [nom, paquet?.platforms?.android?.sourceDir])
      .filter(([, chemin]) => chemin && !existsSync(chemin))
  : [['<cache illisible>', CACHE]];

if (!disparus.length) {
  process.exit(0);
}

console.log('\nLe cache d’autolinking pointe sur des dossiers qui n’existent plus :');
for (const [nom, chemin] of disparus) {
  console.log(`  ${nom}\n    ${chemin}`);
}
console.log('\nnpm a déplacé ces paquets depuis la dernière compilation. On refait le cache.');
console.log('La compilation native repart de zéro : comptez quelques minutes de plus.\n');

for (const dossier of A_JETER) {
  rmSync(dossier, { recursive: true, force: true });
}
