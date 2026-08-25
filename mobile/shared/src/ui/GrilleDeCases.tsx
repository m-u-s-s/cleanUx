import React from 'react';
import { StyleSheet, Text, View, type ViewStyle } from 'react-native';
import { radius, spacing, typography, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * LA GRILLE DE CASES — « tout visible d'un coup ».
 *
 * L'equivalent natif de `.brio-terrain` du web. Une case porte UNE information : un
 * libelle court, une valeur, parfois une note. L'oeil balaye la grille au lieu de
 * derouler une liste — c'est la difference entre lire un tableau de bord d'un coup
 * d'oeil et le parcourir ligne a ligne, le telephone dans une main sur un chantier.
 *
 * POURQUOI PAS `FlatList` : ces grilles portent trois a huit cases. Un rendu virtualise
 * y coute plus qu'il ne rapporte, et il empeche la grille de se replier naturellement
 * quand elle est imbriquee dans un `ScrollView` — ce qui est le cas partout ici.
 *
 * LA LARGEUR EST EN POURCENTAGE, pas en pixels : une valeur fixe deborde sur un petit
 * ecran et laisse un vide sur une tablette.
 */
export type TonDeCase = 'neutre' | 'accent' | 'bon' | 'attention' | 'alerte';

export interface Case {
  /** Le libelle court, en capitales — deux mots au plus. */
  libelle: string;
  valeur: string | number;
  /** L'unite collee a la valeur, en plus petit : « m² », « km », « min ». */
  unite?: string;
  /** Une precision sous la valeur — une date, un ecart, un rappel. */
  note?: string;
  ton?: TonDeCase;
  icone?: React.ReactNode;
}

interface GrilleDeCasesProps {
  cases: Case[];
  /** Le nombre de colonnes. Deux par defaut : trois serrent trop sous 360px. */
  colonnes?: 2 | 3 | 4;
  /** Pose la grille sur une surface DENSE (un heros sombre) plutot que sur la page. */
  surSombre?: boolean;
  style?: ViewStyle;
}

export function GrilleDeCases({ cases, colonnes = 2, surSombre = false, style }: GrilleDeCasesProps) {
  const theme = useThemeColors();
  const s = feuille(theme, surSombre);

  // Le pourcentage laisse la place aux gouttieres : sans la soustraction, la derniere
  // case de chaque rangee passe a la ligne suivante et la grille se disloque.
  const largeur = `${100 / colonnes}%` as const;

  return (
    <View style={[s.grille, style]}>
      {cases.map((c, i) => (
        <View key={`${c.libelle}-${i}`} style={[s.enveloppe, { width: largeur }]}>
          <View style={[s.case_, tonDuBord(s, c.ton)]} accessible accessibilityRole="text"
                accessibilityLabel={`${c.libelle} : ${c.valeur}${c.unite ? ' ' + c.unite : ''}`}>
            <View style={s.tete}>
              {c.icone}
              <Text numberOfLines={1} style={s.libelle}>{c.libelle}</Text>
            </View>

            <Text numberOfLines={2} style={[s.valeur, tonDeLaValeur(s, c.ton)]}>
              {c.valeur}
              {c.unite ? <Text style={s.unite}>{` ${c.unite}`}</Text> : null}
            </Text>

            {c.note ? <Text numberOfLines={1} style={s.note}>{c.note}</Text> : null}
          </View>
        </View>
      ))}
    </View>
  );
}

const tonDuBord = (s: ReturnType<typeof feuille>, ton?: TonDeCase) =>
  ton === 'accent' ? s.bordAccent
    : ton === 'bon' ? s.bordBon
    : ton === 'attention' ? s.bordAttention
    : ton === 'alerte' ? s.bordAlerte
    : null;

const tonDeLaValeur = (s: ReturnType<typeof feuille>, ton?: TonDeCase) =>
  ton === 'accent' ? s.valeurAccent
    : ton === 'bon' ? s.valeurBon
    : ton === 'attention' ? s.valeurAttention
    : ton === 'alerte' ? s.valeurAlerte
    : null;

const feuille = (theme: ThemeTokens, surSombre: boolean) => {
  const encre = surSombre ? '#ffffff' : theme.textOnGlass;
  const attenue = surSombre ? 'rgba(255,255,255,0.62)' : theme.mutedOnGlass;
  const fond = surSombre ? 'rgba(255,255,255,0.07)' : theme.glass;
  const bord = surSombre ? 'rgba(255,255,255,0.12)' : theme.glassBorder;

  return StyleSheet.create({
    grille: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      marginHorizontal: -spacing.xs,
    },
    enveloppe: {
      paddingHorizontal: spacing.xs,
      paddingBottom: spacing.sm,
    },
    case_: {
      minHeight: 78,
      padding: spacing.sm,
      borderRadius: radius.md,
      backgroundColor: fond,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: bord,
      /* Le liseré de statut colore la TRANCHE, pas le fond : un aplat teinte sur du
         verre redevient un rectangle opaque et casse la matiere. */
      borderLeftWidth: 3,
      borderLeftColor: 'transparent',
    },
    bordAccent:    { borderLeftColor: theme.accent },
    bordBon:       { borderLeftColor: theme.success },
    bordAttention: { borderLeftColor: theme.warning },
    bordAlerte:    { borderLeftColor: theme.danger },

    tete: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 4,
      marginBottom: 4,
    },
    libelle: {
      flexShrink: 1,
      fontFamily: typography.fontFamily.bodySemiBold,
      fontSize: 10,
      letterSpacing: 0.6,
      textTransform: 'uppercase',
      color: attenue,
    },
    valeur: {
      fontFamily: typography.fontFamily.bodyExtraBold,
      fontSize: 19,
      lineHeight: 23,
      color: encre,
    },
    valeurAccent:    { color: surSombre ? theme.accent : theme.accentDeep },
    valeurBon:       { color: theme.success },
    valeurAttention: { color: theme.warning },
    valeurAlerte:    { color: theme.danger },

    unite: {
      fontFamily: typography.fontFamily.bodySemiBold,
      fontSize: 12,
      color: attenue,
    },
    note: {
      marginTop: 2,
      fontFamily: typography.fontFamily.body,
      fontSize: 11,
      color: attenue,
    },
  });
};
