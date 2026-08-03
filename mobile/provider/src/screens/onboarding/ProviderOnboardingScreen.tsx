import React, { useMemo, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator, TouchableOpacity } from 'react-native';
import { Button, Icon } from '@/ui';
import { ApiError } from '@/api';
import {
  useOnboardingProgress,
  useOnboardingDocuments,
  useCompleteStep,
  type OnboardingStep,
} from '@/onboarding';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { ContractStep, DocumentsStep, KycStep, ProfileStep, SkillsStep, type StepProps } from './steps';

/**
 * Cockpit de vérification : le dossier du prestataire, carte par carte.
 *
 * L'écran présentait auparavant UNE étape à la fois, la première non terminée, dans un ordre
 * imposé. Un prestataire bloqué sur une pièce manquante ne pouvait donc rien faire avancer
 * d'autre, et ne voyait pas ce qui l'attendait ensuite.
 *
 * Chaque vérification devient une carte, ouvrable dans l'ordre que l'on veut — à l'exception des
 * dépendances réelles du moteur (`depends_on`), qui verrouillent en disant pourquoi. L'état de
 * chaque carte est celui du serveur, y compris ce que le parcours seul ne sait pas dire : une
 * pièce refusée s'affiche « À corriger » avec son motif, sans attendre un email.
 *
 * Le serveur reste seul juge : chaque complétion est revalidée côté API, et un 422 signifie que la
 * condition réelle n'est pas remplie — pas que la requête était malformée.
 */
const STEP_COMPONENTS: Record<string, React.ComponentType<StepProps>> = {
  profile_complete: ProfileStep,
  contract_sign: ContractStep,
  kyc_check: KycStep,
  document_upload: DocumentsStep,
  skill_declare: SkillsStep,
};

/**
 * Habillage de chaque carte. Le temps annoncé est une estimation volontairement basse mais
 * tenable : promettre « 2 min » puis en demander dix est le meilleur moyen de faire abandonner.
 */
const STEP_PRESENTATION: Record<string, { icon: string; duration: string }> = {
  profile_complete: { icon: 'person-circle-outline', duration: '~1 min' },
  contract_sign: { icon: 'document-text-outline', duration: '~2 min' },
  kyc_check: { icon: 'finger-print-outline', duration: '~3 min' },
  document_upload: { icon: 'folder-open-outline', duration: '~3 min' },
  skill_declare: { icon: 'construct-outline', duration: '~1 min' },
};

type CardState = 'todo' | 'locked' | 'in_review' | 'needs_fix' | 'done';

/**
 * Chaque teinte est choisie sur son contraste mesuré contre son propre lavis, ce libellé étant
 * du texte courant : surface[600] 6,99:1, warning[700] 4,92:1, danger[600] 4,59:1,
 * success[700] 5,26:1. Les nuances immédiatement plus claires — surface[500] à 4,27:1 et
 * success[600] à 3,61:1 — échouent au seuil de 4,5:1 et ne sont donc pas employées ici.
 */
const statePresentation = (t: ThemeTokens): Record<CardState, { label: string; color: string; background: string }> => ({
  // Les fonds neutres passent aux jetons ; les couleurs de TEXTE restent sémantiques, leurs
  // ratios de contraste ayant été mesurés et leur sens ne dépendant pas du fond.
  todo: { label: 'À faire', color: t.textSecondary, background: t.inputBg },
  locked: { label: 'Verrouillé', color: t.textSecondary, background: t.inputBg },
  in_review: { label: 'En cours de vérification', color: colors.warning[700], background: colors.warning[50] },
  needs_fix: { label: 'À corriger', color: colors.danger[600], background: colors.danger[50] },
  done: { label: 'Vérifié', color: colors.success[700], background: colors.success[50] },
});

function stepErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    // Le moteur renvoie ses refus sous `errors`, chacun expliquant la condition manquante.
    // Laravel normalise en tableaux, mais on tolère la chaîne nue : un indexage aveugle sur une
    // chaîne rendrait sa première LETTRE, message d'erreur pire que pas de message du tout.
    const first = error.errors ? Object.values(error.errors)[0] : undefined;
    const message = Array.isArray(first) ? first[0] : first;
    if (typeof message === 'string' && message) return message;
    if (error.status === 0) return 'Connexion perdue. Vérifiez votre réseau, puis réessayez.';
  }

  return "Cette étape n'a pas pu être validée. Réessayez.";
}

export function ProviderOnboardingScreen({ onFinished }: { onFinished?: () => void }) {
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const presentations = statePresentation(theme);
  const { data, isLoading, isError, refetch } = useOnboardingProgress();
  const { data: documents } = useOnboardingDocuments();
  const complete = useCompleteStep();
  const [failedStepId, setFailedStepId] = useState<number | null>(null);
  const [openStepId, setOpenStepId] = useState<number | null>(null);

  const steps = useMemo(
    () => [...(data?.steps ?? [])].sort((a, b) => a.position - b.position),
    [data?.steps],
  );

  const doneCodes = useMemo(
    () =>
      new Set(
        steps
          .filter(s => s.completion_status === 'completed' || s.completion_status === 'skipped')
          .map(s => s.code),
      ),
    [steps],
  );

  /**
   * Une pièce refusée doit se lire sur la carte « justificatifs » elle-même : le parcours, lui,
   * ne connaît que « déposé » et laisserait croire l'étape en règle.
   */
  const rejectedDocument = useMemo(
    () => documents?.requirements.find(r => r.document?.status === 'rejected') ?? null,
    [documents],
  );

  const unmetDependencies = (step: OnboardingStep): string[] =>
    (step.depends_on ?? []).filter(code => !doneCodes.has(code));

  const stateOf = (step: OnboardingStep): CardState => {
    if (doneCodes.has(step.code)) return 'done';

    if (step.code === 'document_upload') {
      if (rejectedDocument) return 'needs_fix';
      if (documents?.requirements.some(r => r.document?.status === 'pending_review')) return 'in_review';
    }

    return unmetDependencies(step).length > 0 ? 'locked' : 'todo';
  };

  const blockingLabel = (step: OnboardingStep): string | null => {
    const unmet = unmetDependencies(step);
    if (unmet.length === 0) return null;

    const names = unmet.map(code => steps.find(s => s.code === code)?.label ?? code);

    return `Terminez d'abord : ${names.join(', ')}.`;
  };

  const doneCount = doneCodes.size;
  const percent = steps.length > 0 ? Math.round((doneCount / steps.length) * 100) : 0;

  if (isLoading) {
    return (
      <View style={styles.centered} testID="onboarding-loading">
        <ActivityIndicator color={colors.brand[500]} />
      </View>
    );
  }

  // Une erreur de chargement ne doit pas enfermer l'utilisateur dans un écran vide sans issue.
  if (isError) {
    return (
      <View style={styles.centered} testID="onboarding-error">
        <Text style={styles.errorTitle}>Impossible de charger votre dossier</Text>
        <Text style={styles.errorHint}>Vérifiez votre connexion, puis réessayez.</Text>
        <Button label="Réessayer" onPress={() => refetch()} variant="secondary" />
      </View>
    );
  }

  if (steps.length > 0 && doneCount === steps.length) {
    return (
      <ScrollView contentContainerStyle={styles.container} testID="onboarding-complete">
        <View style={styles.doneBadge}>
          <Icon name="checkmark-circle-outline" size={40} color={colors.success[600]} />
        </View>
        <Text style={styles.title}>Dossier complet</Text>
        <Text style={styles.subtitle}>
          Votre dossier est envoyé. Nous le validons sous peu — vous recevrez une notification dès
          que votre compte sera actif.
        </Text>
        {onFinished ? <Button label="Continuer" onPress={onFinished} fullWidth size="lg" /> : null}
      </ScrollView>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
      <View>
        <Text style={styles.title}>Votre dossier de vérification</Text>
        <Text style={styles.subtitle}>
          {doneCount} sur {steps.length} — à compléter dans l'ordre que vous voulez.
        </Text>
      </View>

      <View
        style={styles.progressTrack}
        accessibilityLabel={`Dossier complété à ${percent} pour cent`}
        testID="onboarding-progress"
      >
        <View style={[styles.progressFill, { width: `${percent}%` }]} />
      </View>

      <View style={styles.cardList}>
        {steps.map(step => {
          const state = stateOf(step);
          const presentation = presentations[state];
          const look = STEP_PRESENTATION[step.code] ?? { icon: 'ellipse-outline', duration: '' };
          const StepComponent = STEP_COMPONENTS[step.code];
          const open = openStepId === step.id;
          const blocking = blockingLabel(step);

          return (
            <View key={step.id} style={styles.card} testID={`onboarding-step-${step.code}`}>
              <TouchableOpacity
                style={styles.cardHead}
                onPress={() => setOpenStepId(open ? null : step.id)}
                // Une carte verrouillée ou déjà vérifiée n'a rien à ouvrir : la laisser cliquable
                // mènerait à un formulaire voué à un 422, ou à refaire ce qui est fait.
                disabled={state === 'locked' || state === 'done'}
                accessibilityRole="button"
                accessibilityState={{ expanded: open, disabled: state === 'locked' || state === 'done' }}
                accessibilityLabel={`${step.label} — ${presentation.label}`}
                testID={`onboarding-card-${step.code}`}
              >
                <View style={[styles.cardIcon, { backgroundColor: presentation.background }]}>
                  <Icon name={look.icon as never} size={20} color={presentation.color} />
                </View>

                <View style={styles.cardBody}>
                  <Text style={styles.cardTitle}>{step.label}</Text>
                  <View style={styles.cardMetaRow}>
                    <Text style={[styles.cardState, { color: presentation.color }]}>
                      {presentation.label}
                    </Text>
                    {state === 'todo' && look.duration ? (
                      <Text style={styles.cardDuration}>· {look.duration}</Text>
                    ) : null}
                  </View>
                  {blocking ? <Text style={styles.cardBlocking}>{blocking}</Text> : null}
                  {state === 'needs_fix' && rejectedDocument?.document?.rejection_reason ? (
                    <Text style={styles.cardBlocking} testID="onboarding-rejection-reason">
                      {rejectedDocument.document.rejection_reason}
                    </Text>
                  ) : null}
                </View>

                <Icon
                  name={state === 'done' ? 'checkmark-circle' : open ? 'chevron-up' : 'chevron-forward'}
                  size={20}
                  color={state === 'done' ? colors.success[600] : colors.surface[400]}
                />
              </TouchableOpacity>

              {open ? (
                <View style={styles.cardContent}>
                  {StepComponent ? (
                    <StepComponent
                      submitting={complete.isPending}
                      error={failedStepId === step.id ? stepErrorMessage(complete.error) : null}
                      onDone={(payload) => {
                        setFailedStepId(null);
                        complete.mutate(
                          { stepId: step.id, payload },
                          {
                            onError: () => setFailedStepId(step.id),
                            onSuccess: () => setOpenStepId(null),
                          },
                        );
                      }}
                    />
                  ) : (
                    // Une étape ajoutée au parcours sans écran correspondant ne doit pas bloquer
                    // le dossier sur une carte vide : on le dit, plutôt que de ne rien afficher.
                    <Text style={styles.errorHint}>
                      Cette étape ({step.label}) n'est pas encore disponible dans l'application.
                      Contactez le support pour la compléter.
                    </Text>
                  )}
                </View>
              ) : null}
            </View>
          );
        })}
      </View>
    </ScrollView>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { padding: spacing.lg, gap: spacing.lg, backgroundColor: t.bg, flexGrow: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.md, padding: spacing.lg },
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.mode.tool.ink,
  },
  subtitle: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: spacing.xs },
  progressTrack: { height: 6, borderRadius: 999, backgroundColor: t.border, overflow: 'hidden' },
  progressFill: { height: 6, borderRadius: 999, backgroundColor: colors.brand[500] },
  cardList: { gap: spacing.sm },
  card: {
    backgroundColor: t.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: t.border,
    overflow: 'hidden',
  },
  // La carte entière est la cible tactile, bien au-delà des 44 pt recommandés.
  cardHead: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, padding: spacing.md, minHeight: 72 },
  cardIcon: { width: 40, height: 40, borderRadius: radius.md, alignItems: 'center', justifyContent: 'center' },
  cardBody: { flex: 1, gap: 2 },
  cardTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  cardMetaRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
  cardState: { fontSize: typography.fontSize.xs, fontWeight: typography.fontWeight.medium },
  cardDuration: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  cardBlocking: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: 2 },
  cardContent: {
    padding: spacing.lg,
    borderTopWidth: 1,
    borderTopColor: t.border,
    backgroundColor: t.card,
  },
  doneBadge: { alignItems: 'center' },
  errorTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  errorHint: { fontSize: typography.fontSize.sm, color: t.textSecondary, textAlign: 'center' },
});
