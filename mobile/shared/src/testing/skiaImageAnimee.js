/**
 * `useAnimatedImageValue` MANQUE AU FAUX DE SKIA.
 *
 * Le fichier de setup fourni par `@shopify/react-native-skia` remplace tout le module ; les
 * exports qui dependent de Reanimated — dont celui-ci — n'y figurent pas, et le fond de
 * l'application leve alors « is not a function » avant meme de rendre.
 *
 * On COMPLETE le faux plutot que de le remplacer : le reste de Skia reste celui du paquet, et une
 * version future qui ajouterait la fonction reprendrait la main d'elle-meme.
 */
const Skia = require('@shopify/react-native-skia');

if (typeof Skia.useAnimatedImageValue !== 'function') {
  Skia.useAnimatedImageValue = () => null;
}
