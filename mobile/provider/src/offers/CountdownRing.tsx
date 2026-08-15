import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import Svg, { Circle } from 'react-native-svg';
import { typography, colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { formatDelai } from '@brio/shared/format';

const TAILLE = 56;
const EPAISSEUR = 5;
const RAYON = (TAILLE - EPAISSEUR) / 2;
const PERIMETRE = 2 * Math.PI * RAYON;

/**
 * L'ANNEAU DE DÉCOMPTE — vingt secondes qui se lisent sans être lues.
 *
 * Une barre qui rétrécit dit « il reste de la place » ; un anneau qui se vide dit « le temps
 * passe ». C'est le patron des plateformes VTC, et la différence n'est pas décorative : le
 * prestataire regarde cet écran une demi-seconde, souvent en conduisant, et doit savoir s'il a
 * quinze secondes ou trois sans lire un chiffre.
 *
 * LE CHIFFRE RESTE AU CENTRE. L'anneau seul demande une conversion mentale que personne ne fait
 * sous pression, et une forme qui se vide n'est pas lisible par un lecteur d'écran — d'où
 * l'étiquette d'accessibilité, qui porte la même information en toutes lettres.
 *
 * LA COULEUR BASCULE SOUS CINQ SECONDES. Passé ce seuil, hésiter revient à laisser expirer : ce
 * n'est plus une information, c'est une alerte.
 */
export function AnneauDeDecompte({ ratio, secondes }: { ratio: number; secondes: number }) {
  const theme = useThemeColors();
  const borne = Math.max(0, Math.min(1, ratio));
  const urgence = secondes <= 5;
  const teinte = urgence ? colors.danger[500] : colors.warning[500];

  return (
    <View
      style={styles.conteneur}
      accessibilityRole="progressbar"
      accessibilityLabel={`${formatDelai(secondes)} pour répondre`}
      testID="offer-countdown-ring"
    >
      <Svg width={TAILLE} height={TAILLE}>
        <Circle
          cx={TAILLE / 2}
          cy={TAILLE / 2}
          r={RAYON}
          stroke={theme.border}
          strokeWidth={EPAISSEUR}
          fill="none"
        />
        <Circle
          cx={TAILLE / 2}
          cy={TAILLE / 2}
          r={RAYON}
          stroke={teinte}
          strokeWidth={EPAISSEUR}
          fill="none"
          strokeLinecap="round"
          strokeDasharray={`${PERIMETRE} ${PERIMETRE}`}
          strokeDashoffset={PERIMETRE * (1 - borne)}
          // Le décompte part du haut et tourne dans le sens des aiguilles : sans cette rotation il
          // partirait de trois heures, ce qu'aucune horloge ne fait.
          transform={`rotate(-90 ${TAILLE / 2} ${TAILLE / 2})`}
          testID="offer-countdown-arc"
        />
      </Svg>
      <Text style={[styles.chiffre, { color: urgence ? colors.danger[500] : theme.text }]}>
        {secondes}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: { width: TAILLE, height: TAILLE, alignItems: 'center', justifyContent: 'center' },
  chiffre: {
    position: 'absolute',
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
  },
});
