import React, { useMemo, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute, type RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, ErrorState, GlassSurface, Screen, Skeleton, useReducedMotion } from '@/ui';
import { radius, spacing, typography } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';
import { DEVISE_PAR_DEFAUT } from '@/format/money';
import { HAUTEUR_DE_REPERE, cheminDeCommande, construireLaSonde, useCatalogue } from '@/catalogue';
import type { RepereDeSonde } from '@/catalogue';
import type { RootStackParamList } from '@/navigation/types';
import { FeuilleDuMetier } from './FeuilleDuMetier';
import { LigneDeSonde } from './LigneDeSonde';
import { libelleDuPrix } from './libelleDuPrix';

/**
 * LE CATALOGUE NATIF — la ligne de sonde sur l'iceberg.
 *
 * `Screen toile` : aucune plaque de fond, l'iceberg reste visible ; chaque texte est porté par du
 * verre (cases, étiquettes, feuille). La commande elle-même reste celle du web : le bouton ouvre le
 * moteur sur le métier choisi, qui pose ses questions et calcule le devis.
 */
export function CatalogueScreen() {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { params } = useRoute<RouteProp<RootStackParamList, 'Catalogue'>>();
  const mode = params.mode;
  const mouvementReduit = useReducedMotion();

  const { data, isLoading, isError, refetch } = useCatalogue(mode);
  const reperes = useMemo(() => construireLaSonde(data?.sectors ?? []), [data]);
  const [index, setIndex] = useState(0);

  if (isLoading) {
    return (
      <Screen toile testID="catalogue-chargement">
        <View style={styles.squelettes}>
          {[0, 1, 2, 3].map(k => (
            <Skeleton key={k} width="100%" height={HAUTEUR_DE_REPERE - spacing.md} borderRadius={radius.md} />
          ))}
        </View>
      </Screen>
    );
  }

  // Un rafraîchissement en échec ne remplace pas un catalogue déjà affiché : `useCatalogue` a un
  // `staleTime` de 5 min, et react-query pose `isError` même quand `data` tient encore la dernière
  // réponse connue. L'écran d'erreur ne se montre que quand il n'y a vraiment rien à montrer.
  if (isError && !data) {
    return (
      <Screen toile>
        <GlassSurface strong radius={radius.lg} style={styles.vide}>
          <ErrorState compact message={tr('catalogue.chargement_impossible')} onRetry={() => void refetch()} />
        </GlassSurface>
      </Screen>
    );
  }

  // Un seul index borné, pour la ligne ET pour la feuille : sans quoi, après un rafraîchissement
  // qui raccourcit la liste, la feuille montrerait le dernier métier pendant qu'aucun repère
  // n'est mis en évidence.
  const indexBorne = Math.min(index, reperes.length - 1);
  const choisi = reperes[indexBorne];

  if (!choisi) {
    return (
      <Screen toile testID="catalogue-vide">
        <GlassSurface strong radius={radius.lg} style={styles.vide}>
          <Text style={styles.videTexte}>
            {mode === 'asap' ? tr('catalogue.aucun_metier_immediat') : tr('catalogue.aucun_metier')}
          </Text>
          {mode === 'asap' ? (
            <Button
              label={tr('catalogue.prendre_rendez_vous_plutot')}
              onPress={() => navigation.replace('Catalogue', { mode: 'scheduled' })}
              variant="secondary"
              testID="catalogue-vers-rendez-vous"
            />
          ) : null}
        </GlassSurface>
      </Screen>
    );
  }

  const devise = data?.currency ?? DEVISE_PAR_DEFAUT;
  const libelleDuRepere = (repere: RepereDeSonde) => libelleDuPrix(repere.metier, devise, tr);

  return (
    <Screen toile testID="catalogue-screen" edges={['left', 'right']} style={styles.contenu}>
      <View style={styles.colonne}>
        <LigneDeSonde
          reperes={reperes}
          index={indexBorne}
          onIndex={setIndex}
          libelleDuRepere={libelleDuRepere}
          mouvementReduit={mouvementReduit}
        />
      </View>

      <FeuilleDuMetier
        repere={choisi}
        libellePrix={libelleDuRepere(choisi)}
        onCommander={() =>
          navigation.navigate('EmbeddedModule', { path: cheminDeCommande(choisi, mode), title: choisi.metier.name })
        }
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  // La feuille va bord à bord : on retire la marge latérale que `Screen` pose par défaut.
  contenu: { paddingHorizontal: 0 },
  colonne: { flex: 1 },
  squelettes: { paddingTop: spacing.md, gap: spacing.sm },
  vide: { marginTop: spacing.lg, padding: spacing.lg, gap: spacing.md, alignItems: 'center' },
  videTexte: { fontSize: typography.fontSize.sm, color: t.textOnGlass, textAlign: 'center' },
});
