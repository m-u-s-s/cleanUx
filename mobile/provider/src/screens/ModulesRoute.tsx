import React from 'react';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { ModulesScreen } from '@/modules';
import type { RootStackParamList } from '@/navigation/types';

/**
 * Les routes natives visées ici ne prennent AUCUN paramètre : c'est ce qui rend la redirection
 * possible depuis un simple chemin web.
 */
type RouteSansParametre =
  | 'CompanyPlanning'
  | 'CompanyDispatch'
  | 'CompanyTasks'
  | 'CompanyInventory'
  | 'CompanyTimesheets'
  | 'CompanyQuotes'
  | 'CompanySites'
  | 'CompanyRolePermissions'
  | 'CompanyFieldTeams'
  | 'CompanyAgencies'
  | 'CompanyMembers'
  | 'CompanyRecruitment'
  | 'CompanyChannels'
  | 'CompanyQualityFleet'
  | 'ProviderNotifications'
  | 'ProviderOnboarding';

/**
 * LE MODULE OUVRE SON ÉCRAN NATIF QUAND IL EN A UN.
 *
 * `EmbeddedModuleRoute` porte cette phrase depuis sa création : « tant qu'aucune correspondance
 * chemin → écran natif n'existe, on ré-embarque la cible ». Cette correspondance n'a jamais été
 * écrite. Résultat mesuré : HUIT écrans natifs — planning, heures, devis, consommables,
 * recrutement, qualité et matériel, rôles et permissions, implantations, soit 1 684 lignes —
 * étaient déclarés dans `RootNavigator` et n'étaient la cible d'AUCUN appel de navigation dans
 * toute l'application. Ils existaient, ils passaient les tests, et personne ne pouvait les
 * atteindre : le seul menu qui nomme ces fonctions ouvrait la page web à la place.
 *
 * C'est la famille dominante de ce dépôt — un module complet et injoignable. Le contrôle n'est pas
 * un test unitaire mais un `grep` des appelants, et `tsc` ne le fera jamais : une route déclarée
 * est valide même si rien ne l'appelle.
 *
 * Ce qui n'a pas d'écran natif reste embarqué : la migration se poursuit chemin par chemin, comme
 * l'approche hybride le prévoit.
 */
const NATIF_PAR_CHEMIN: Record<string, RouteSansParametre> = {
  '/dashboard/entreprise-prestataire/planning': 'CompanyPlanning',
  '/dashboard/entreprise-prestataire/dispatch': 'CompanyDispatch',
  '/dashboard/entreprise-prestataire/taches': 'CompanyTasks',
  '/dashboard/entreprise-prestataire/consommables': 'CompanyInventory',
  '/dashboard/entreprise-prestataire/heures': 'CompanyTimesheets',
  '/dashboard/entreprise-prestataire/devis': 'CompanyQuotes',
  '/dashboard/entreprise-prestataire/sites': 'CompanySites',
  '/dashboard/entreprise-prestataire/roles-permissions': 'CompanyRolePermissions',
  '/dashboard/entreprise-prestataire/equipes-terrain': 'CompanyFieldTeams',
  '/dashboard/entreprise-prestataire/implantations': 'CompanyAgencies',
  '/dashboard/entreprise-prestataire/equipe': 'CompanyMembers',
  '/dashboard/entreprise-prestataire/recrutement': 'CompanyRecruitment',
  '/dashboard/entreprise-prestataire/canaux': 'CompanyChannels',
  '/dashboard/entreprise-prestataire/qualite-materiel': 'CompanyQualityFleet',
  '/notifications': 'ProviderNotifications',
  '/provider/onboarding': 'ProviderOnboarding',
  /*
   * `/user/profile` N'EST PAS ICI, ET C'EST VOULU. L'écran `Profile` appartient à `TabParamList`,
   * pas à `RootStackParamList` : le viser depuis la pile racine ne mènerait nulle part. Ce module
   * reste donc embarqué — c'est TypeScript qui a refusé l'entrée, pas une relecture.
   */
};

/** Le catalogue vient du serveur : un chemin peut arriver avec une requête ou une barre finale. */
export function ecranNatifPour(chemin: string): RouteSansParametre | undefined {
  const propre = (chemin.split('?')[0] ?? chemin).replace(/\/+$/, '');

  return NATIF_PAR_CHEMIN[propre];
}

/**
 * Le répertoire des modules, branché sur l'hôte WebView de cette application.
 *
 * L'écran lui-même est PARTAGÉ entre les deux applications : ce qui change ici est la façon
 * d'ouvrir un module, chaque application ayant son propre hôte embarqué. Le catalogue, lui, vient
 * du serveur, qui déduit le contexte du jeton.
 */
export function ModulesRoute() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  return (
    <ModulesScreen
      onOuvrir={(module) => {
        /*
         * NE VISER QUE CE QUE LA PILE COURANTE DÉCLARE.
         *
         * `RootNavigator` monte QUATRE piles différentes selon l'espace choisi, et elles ne
         * déclarent pas les mêmes routes : l'espace société en a treize sur dix-sept, l'espace
         * prestataire seize, et les espaces admin et super-admin AUCUNE. Rediriger sans vérifier
         * transformerait donc chaque entrée du menu en lien mort pour un administrateur.
         *
         * Et un lien mort est ici particulièrement discret : `RootNavigator` le dit lui-même —
         * « une route absente ne lève rien, elle ne fait simplement rien ». Rien ne plante, rien
         * ne s'ouvre, et l'on croit avoir mal appuyé.
         *
         * `routeNames` est la liste que le navigateur courant déclare vraiment : c'est la seule
         * mesure qui ne se périme pas quand on ajoute ou retire un écran d'une pile.
         */
        const declarees = navigation.getState()?.routeNames ?? [];
        const natifBrut = ecranNatifPour(module.path);
        const natif = natifBrut && declarees.includes(natifBrut) ? natifBrut : undefined;

        if (natif) {
          /*
           * Toutes les routes de la table prennent `undefined` en paramètre — c'est précisément
           * ce que garantit `RouteSansParametre`. TypeScript ne sait pas réduire une union de
           * noms à une seule surcharge de `navigate` — d'où `as never`, l'idiome habituel pour
           * un nom de route dynamique. La conversion est sûre par construction : c'est le type
           * de la table, et non ce cast, qui garantit qu'aucune route à paramètres n'y entre.
           */
          navigation.navigate(natif as never);

          return;
        }

        navigation.navigate('EmbeddedModule', { path: module.path, title: module.label });
      }}
    />
  );
}
