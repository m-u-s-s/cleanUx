import React, { forwardRef, useMemo } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { BottomSheet, Button, Icon, Badge } from '@/ui';
import { useOnSiteTimeline, useOnSiteExtras, useTodoList, useRevisionDeDevis } from '@/booking/onsite';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * « MA MISSION » — l'aperçu, pas le contenu.
 *
 * ── POURQUOI UNE FEUILLE ET NON UN ÉCRAN ─────────────────────────────────────────────────────
 *
 * Le client la déclenche depuis sa carte, pouce sur le bas de l'écran, pendant qu'il attend. Une
 * navigation complète lui ferait perdre le suivi qu'il regardait ; une feuille le laisse revenir
 * d'un geste. C'est le motif exact de l'accueil, et le même composant.
 *
 * ── ELLE PORTE CE QUI ATTEND, ET DEUX RACCOURCIS ─────────────────────────────────────────────
 *
 * Y mettre le contenu entier obligerait à faire défiler un panneau à demi ouvert, main levée. Elle
 * annonce donc ce qui attend une réponse — un devis révisé, un supplément — et ouvre la page qui,
 * elle, se lit posément.
 *
 * ── LA NAVIGATION ARRIVE PAR RAPPELS, ELLE NE SE PREND PAS AU CONTEXTE ───────────────────────
 *
 * `useNavigation()` exige un `NavigationContainer` monté. L'écran qui porte cette feuille reçoit
 * déjà son objet de navigation en propriété, et ses tests le fournissent tel quel : appeler le
 * crochet ici ferait tomber l'écran entier en test, pour un service que l'appelant rend déjà.
 */
interface MissionSheetProps {
  bookingId: number;
  onGerer: () => void;
  onMessage: () => void;
  onLitige: () => void;
}

export const MissionSheet = forwardRef<GorhomBottomSheet, MissionSheetProps>(
  ({ bookingId, onGerer, onMessage, onLitige }, ref) => {
    const t = useThemeColors();
    const styles = stylesFor(t);

    const { data: fil } = useOnSiteTimeline(bookingId);
    const { data: supplements } = useOnSiteExtras(bookingId);
    const { data: liste } = useTodoList(bookingId);
    const { data: revision } = useRevisionDeDevis(bookingId);

    /*
     * CE QUI ATTEND UNE RÉPONSE — compté ici et annoncé en une phrase.
     *
     * Le client doit savoir, avant d'ouvrir, s'il y a quelque chose à faire. Une feuille qui ne dit
     * rien se referme sans être lue, et le prestataire attend devant lui pour rien.
     */
    const enAttente = useMemo(() => {
      const suppléments = (supplements ?? []).filter((s) => s.awaiting_client).length;
      const devis = revision?.awaiting_client ? 1 : 0;

      return suppléments + devis;
    }, [supplements, revision]);

    const tachesOuvertes = (liste?.items ?? []).filter((i) => !i.done).length;

    return (
      <BottomSheet ref={ref} snapPoints={['45%']}>
        <View style={styles.body} testID="mission-sheet">
          <Text style={styles.titre} accessibilityRole="header">Ma mission</Text>

          {enAttente > 0 ? (
            <View style={styles.attente} testID="mission-sheet-attente">
              <Icon name="alert-circle-outline" size={18} color={t.text} />
              <Text style={styles.attenteTexte}>
                {enAttente === 1
                  ? '1 chose attend votre réponse'
                  : `${enAttente} choses attendent votre réponse`}
              </Text>
            </View>
          ) : null}

          <View style={styles.resume}>
            {fil?.progress && fil.progress.total > 0 ? (
              <Badge
                label={`${fil.progress.done}/${fil.progress.total} tâches`}
                variant={fil.progress.percent === 100 ? 'success' : 'brand'}
              />
            ) : null}
            {tachesOuvertes > 0 ? (
              <Text style={styles.resumeTexte}>
                {tachesOuvertes === 1
                  ? '1 tâche de votre liste reste à faire'
                  : `${tachesOuvertes} tâches de votre liste restent à faire`}
              </Text>
            ) : (
              <Text style={styles.resumeTexte}>Votre liste est à jour.</Text>
            )}
          </View>

          <Button
            label="Gérer ma mission"
            onPress={onGerer}
            fullWidth
            size="lg"
            testID="ouvrir-gerer-ma-mission"
          />

          {/*
            JOINDRE ET SIGNALER, à portée immédiate.

            Ces deux gestes sont ceux qu'on cherche quand quelque chose cloche, et c'est le pire
            moment pour naviguer dans un menu. Le litige s'ouvre pré-rempli avec la mission.
          */}
          <View style={styles.raccourcis}>
            <TouchableOpacity
              style={[styles.raccourci, { backgroundColor: t.card }]}
              onPress={onMessage}
              accessibilityRole="button"
              accessibilityLabel="Envoyer un message au prestataire"
              testID="mission-sheet-message"
            >
              <Icon name="chatbubble-outline" size={20} color={t.text} />
              <Text style={styles.raccourciTexte}>Message</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.raccourci, { backgroundColor: t.card }]}
              onPress={onLitige}
              accessibilityRole="button"
              accessibilityLabel="Signaler un litige sur cette mission"
              testID="mission-sheet-litige"
            >
              <Icon name="flag-outline" size={20} color={t.text} />
              <Text style={styles.raccourciTexte}>Signaler un litige</Text>
            </TouchableOpacity>
          </View>
        </View>
      </BottomSheet>
    );
  },
);

MissionSheet.displayName = 'MissionSheet';

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  body: { gap: spacing.md, paddingHorizontal: spacing.md, paddingBottom: spacing.lg },
  titre: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  attente: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    backgroundColor: t.tint.warning,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  attenteTexte: {
    flex: 1,
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  resume: { gap: spacing.xs },
  resumeTexte: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  raccourcis: { flexDirection: 'row', gap: spacing.sm },
  // La carte entière est la cible tactile, bien au-delà des 44 pt recommandés.
  raccourci: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.xs,
    borderRadius: radius.md,
    paddingVertical: spacing.md,
    minHeight: 52,
  },
  raccourciTexte: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
});
