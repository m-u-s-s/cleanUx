import React from 'react';
import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { GlassSurface } from '@/ui/GlassSurface';
import type { LiveMissionClock, MissionClockPhase } from './types';
import { formatChronometre, formatDureeCourte } from './useMissionClock';

export type ClockAudience = 'client' | 'provider';

interface MissionClockBarProps {
  clock: LiveMissionClock;
  audience: ClockAudience;
  /** L'action de prolongation, côté client. Absente, le bandeau reste purement informatif. */
  onExtend?: () => void;
  extendLabel?: string;
  style?: StyleProp<ViewStyle>;
  testID?: string;
}

/**
 * LE COMPTEUR DE LA MISSION — la même pièce dans les deux applications, deux discours.
 *
 * POURQUOI UN SEUL COMPOSANT POUR DEUX PUBLICS. Le client et le prestataire regardent le même
 * chronomètre et en tirent des conséquences opposées : l'un peut prolonger, l'autre subit une
 * majoration s'il ne le fait pas. Ce sont deux TEXTES, pas deux logiques. Écrire deux composants
 * ferait diverger le calcul de phase, l'arrondi et le seuil d'alerte — et le jour où ils
 * divergeraient, l'un des deux annoncerait un dépassement que l'autre ne verrait pas. Ce dépôt a
 * déjà payé cette leçon sur les aides de formatage, dupliquées puis recassées à l'identique.
 *
 * IL N'Y A PAS DE ROUGE AVANT QU'IL Y AIT DE L'ARGENT. Les quinze dernières minutes s'annoncent en
 * ambre, la franchise reste en ambre — c'est encore gratuit — et seul le dépassement réellement
 * facturé passe au rouge. Alarmer plus tôt apprendrait à ignorer l'alarme.
 */
export function MissionClockBar({
  clock,
  audience,
  onExtend,
  extendLabel = 'Prolonger',
  style,
  testID = 'mission-clock-bar',
}: MissionClockBarProps) {
  const theme = useThemeColors();
  const styles = stylesFor(theme);

  if (!clock.applies) return null;

  const accent = accentDe(clock.phase, theme);
  const { titre, detail } = discours(clock, audience);
  const facturees = clock.server.billable_overtime_minutes ?? 0;
  const montant = clock.server.overtime_amount_cents ?? 0;

  return (
    <GlassSurface style={[styles.plaque, style]} radius={radius.lg} testID={testID}>
      <View style={[styles.liseret, { backgroundColor: accent }]} />

      <View style={styles.corps}>
        <View style={styles.ligneHaute}>
          <View style={styles.bloc}>
            <Text style={styles.libelle}>{titre}</Text>
            {/*
             * LE CHRONOMÈTRE EST LE PLUS GROS CHIFFRE DE LA CARTE, en chiffres tabulaires : sans
             * cette variante, chaque seconde change la largeur des glyphes et le nombre entier
             * tressaute à chaque battement.
             */}
            <Text
              testID={`${testID}-value`}
              style={[styles.compteur, { color: accent }]}
              accessibilityLabel={titre + ' ' + detail}
            >
              {formatChronometre(valeurAffichee(clock))}
            </Text>
          </View>

          {onExtend ? (
            <Text
              testID={`${testID}-extend`}
              onPress={onExtend}
              accessibilityRole="button"
              style={[styles.action, { color: accent, borderColor: accent }]}
            >
              {extendLabel}
            </Text>
          ) : null}
        </View>

        <View style={styles.rail}>
          <View
            testID={`${testID}-progress`}
            style={[styles.remplissage, { width: `${Math.round(clock.progress * 100)}%`, backgroundColor: accent }]}
          />
        </View>

        <Text style={styles.detail}>{detail}</Text>

        {clock.phase === 'overtime' && facturees > 0 ? (
          <Text testID={`${testID}-amount`} style={[styles.montant, { color: accent }]}>
            {formatDureeCourte(facturees * 60)} facturé{facturees > 60 ? 'es' : 'e'} · {formatEuros(montant)}
            {clock.server.capped ? ' · plafond atteint' : ''}
          </Text>
        ) : null}
      </View>
    </GlassSurface>
  );
}

/**
 * CE QUE LE GRAND CHIFFRE COMPTE change de sens avec la phase, et c'est délibéré.
 *
 * Tant qu'il reste du temps, il DÉCOMPTE — c'est la question que se posent les deux parties. Une
 * fois l'échéance passée, il compte le dépassement À LA HAUSSE : voir un décompte s'arrêter à zéro
 * ne dit pas depuis combien de temps on déborde, et c'est précisément le chiffre qui coûte.
 */
function valeurAffichee(clock: LiveMissionClock): number {
  if (clock.remainingSeconds > 0) return clock.remainingSeconds;
  if (clock.phase === 'grace') return clock.graceSeconds;

  return -clock.remainingSeconds;
}

function accentDe(phase: MissionClockPhase, theme: ThemeTokens): string {
  switch (phase) {
    case 'overtime':
      return colors.danger[500];
    case 'grace':
    case 'ending':
      return colors.warning[500];
    default:
      return theme.isDark ? colors.brand[300] : colors.brand[600];
  }
}

/**
 * LES DEUX DISCOURS.
 *
 * Le client entend ce qu'il peut décider ; le prestataire entend ce qu'il déclenche. Aucun des
 * deux n'entend « ×1,30 » avant que ce multiplicateur ne s'applique réellement — annoncer une
 * majoration pendant qu'elle ne s'applique pas est le meilleur moyen qu'on ne la croie plus quand
 * elle s'applique.
 */
function discours(clock: LiveMissionClock, audience: ClockAudience): { titre: string; detail: string } {
  const achetees = clock.server.purchased_minutes ?? 0;
  const total = formatDureeCourte(achetees * 60);
  const franchise = clock.server.grace_minutes ?? 0;
  const client = audience === 'client';

  switch (clock.phase) {
    case 'overtime':
      return {
        titre: 'Temps dépassé',
        detail: client
          ? `Vous aviez réservé ${total}. Le temps supplémentaire est facturé au tarif majoré.`
          : `${total} étaient prévues. Le temps supplémentaire est facturé au client au tarif majoré.`,
      };

    case 'grace':
      return {
        titre: 'Fin de la tolérance',
        detail: client
          ? `Vos ${total} sont écoulées. ${franchise} min offertes, puis le temps supplémentaire devient payant.`
          : `Les ${total} prévues sont écoulées. ${franchise} min de tolérance, ensuite le client est facturé en plus.`,
      };

    case 'ending':
      return {
        titre: 'Temps restant',
        detail: client
          ? `Bientôt la fin de vos ${total}. Vous pouvez prolonger dès maintenant, au tarif normal.`
          : `Bientôt la fin des ${total} prévues. Le client peut encore prolonger au tarif normal.`,
      };

    default:
      return {
        titre: 'Temps restant',
        detail: client ? `Sur les ${total} réservées.` : `Sur les ${total} prévues.`,
      };
  }
}

/** Le format belge, comme la console d'administration : « 57,04 € ». */
function formatEuros(centimes: number): string {
  return new Intl.NumberFormat('fr-BE', { style: 'currency', currency: 'EUR' }).format(centimes / 100);
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    plaque: { overflow: 'hidden' },
    /** Une arête de couleur à gauche : elle dit la gravité sans repeindre la carte. */
    liseret: { position: 'absolute', left: 0, top: 0, bottom: 0, width: 3 },
    corps: { padding: spacing.md, paddingLeft: spacing.md + 3, gap: spacing.sm },
    ligneHaute: { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', gap: spacing.sm },
    bloc: { flexShrink: 1 },
    libelle: {
      fontSize: typography.fontSize.xs,
      letterSpacing: 1.2,
      textTransform: 'uppercase',
      color: t.textSecondary,
      marginBottom: 2,
    },
    compteur: {
      fontSize: 34,
      lineHeight: 38,
      fontWeight: '300',
      fontVariant: ['tabular-nums'],
      letterSpacing: -0.5,
    },
    action: {
      fontSize: typography.fontSize.sm,
      fontWeight: '600',
      borderWidth: StyleSheet.hairlineWidth,
      borderRadius: radius.pill,
      paddingHorizontal: spacing.md,
      paddingVertical: spacing.xs,
      overflow: 'hidden',
    },
    rail: { height: 3, borderRadius: radius.pill, backgroundColor: t.border, overflow: 'hidden' },
    remplissage: { height: '100%', borderRadius: radius.pill },
    detail: { fontSize: typography.fontSize.sm, lineHeight: 19, color: t.textSecondary },
    montant: { fontSize: typography.fontSize.sm, fontWeight: '600' },
  });
