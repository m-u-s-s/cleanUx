import React, { useMemo, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator } from 'react-native';
import { Button, Icon } from '@/ui';
import { ApiError } from '@/api';
import {
  useOnboardingProgress,
  useCompleteStep,
  type OnboardingStep,
} from '@/onboarding';
import { colors, radius, spacing, typography } from '@/theme';
import { ContractStep, DocumentsStep, KycStep, ProfileStep, SkillsStep, type StepProps } from './steps';

/**
 * Parcours de vérification obligatoire avant l'accès au tableau de bord.
 *
 * Le compte créé par l'inscription est cantonné à son dossier par le middleware
 * provider.approved : sans cet écran, l'utilisateur atterrissait sur un dashboard où chaque appel
 * échouait en 403, sans qu'on lui dise quoi faire.
 *
 * Le serveur reste seul juge : chaque complétion est revalidée côté API, et un 422 signifie que la
 * condition réelle n'est pas remplie — pas que la requête était malformée. L'écran affiche donc
 * l'erreur du serveur telle quelle plutôt que d'inventer un état local.
 */
const STEP_COMPONENTS: Record<string, React.ComponentType<StepProps>> = {
  profile_complete: ProfileStep,
  contract_sign: ContractStep,
  kyc_check: KycStep,
  document_upload: DocumentsStep,
  skill_declare: SkillsStep,
};

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
  const { data, isLoading, isError, refetch } = useOnboardingProgress();
  const complete = useCompleteStep();
  const [failedStepId, setFailedStepId] = useState<number | null>(null);

  const steps = data?.steps ?? [];

  /**
   * L'étape courante est la première non terminée, dans l'ordre du parcours. On ne se fie pas à
   * `current_step_code` seul : une étape validée hors application — l'identité, décidée par un
   * service tiers — doit faire avancer l'écran au rafraîchissement suivant.
   */
  const currentStep: OnboardingStep | undefined = useMemo(
    () =>
      [...steps]
        .sort((a, b) => a.position - b.position)
        .find(step => step.completion_status !== 'completed' && step.completion_status !== 'skipped'),
    [steps],
  );

  const doneCount = steps.filter(
    s => s.completion_status === 'completed' || s.completion_status === 'skipped',
  ).length;

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

  if (!currentStep) {
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

  const StepComponent = STEP_COMPONENTS[currentStep.code];

  return (
    <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
      <View>
        <Text style={styles.title}>Vérification de votre compte</Text>
        <Text style={styles.subtitle}>
          Étape {doneCount + 1} sur {steps.length} — {currentStep.label}
        </Text>
      </View>

      <View style={styles.progressTrack} accessibilityLabel={`${doneCount} étapes sur ${steps.length} terminées`}>
        <View style={[styles.progressFill, { width: `${(doneCount / Math.max(steps.length, 1)) * 100}%` }]} />
      </View>

      <View style={styles.stepList}>
        {[...steps].sort((a, b) => a.position - b.position).map(step => {
          const done = step.completion_status === 'completed' || step.completion_status === 'skipped';
          const active = step.id === currentStep.id;

          return (
            <View key={step.id} style={styles.stepRow} testID={`onboarding-step-${step.code}`}>
              <Icon
                name={done ? 'checkmark-circle' : active ? 'ellipse-outline' : 'ellipse-outline'}
                size={18}
                color={done ? colors.success[600] : active ? colors.brand[600] : colors.surface[300]}
              />
              <Text
                style={[
                  styles.stepRowLabel,
                  done && styles.stepRowDone,
                  active && styles.stepRowActive,
                ]}
              >
                {step.label}
              </Text>
            </View>
          );
        })}
      </View>

      {StepComponent ? (
        <View style={styles.card}>
          <StepComponent
            submitting={complete.isPending}
            error={failedStepId === currentStep.id ? stepErrorMessage(complete.error) : null}
            onDone={(payload) => {
              setFailedStepId(null);
              complete.mutate(
                { stepId: currentStep.id, payload },
                { onError: () => setFailedStepId(currentStep.id) },
              );
            }}
          />
        </View>
      ) : (
        // Une étape ajoutée au parcours sans écran correspondant ne doit pas bloquer le dossier
        // sur une page blanche : on le dit, plutôt que de ne rien afficher.
        <View style={styles.card}>
          <Text style={styles.errorHint}>
            Cette étape ({currentStep.label}) n'est pas encore disponible dans l'application.
            Contactez le support pour la compléter.
          </Text>
        </View>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: spacing.lg, gap: spacing.lg, backgroundColor: '#F7F8FB', flexGrow: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.md, padding: spacing.lg },
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.mode.tool.ink,
  },
  subtitle: { fontSize: typography.fontSize.sm, color: colors.surface[600], marginTop: spacing.xs },
  progressTrack: { height: 6, borderRadius: 999, backgroundColor: colors.surface[200], overflow: 'hidden' },
  progressFill: { height: 6, borderRadius: 999, backgroundColor: colors.brand[500] },
  stepList: { gap: spacing.xs },
  stepRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  stepRowLabel: { fontSize: typography.fontSize.sm, color: colors.surface[500] },
  stepRowDone: { color: colors.success[600] },
  stepRowActive: { color: colors.mode.tool.ink, fontWeight: typography.fontWeight.semibold },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: radius.lg,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.surface[200],
  },
  doneBadge: { alignItems: 'center' },
  errorTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  errorHint: { fontSize: typography.fontSize.sm, color: colors.surface[600], textAlign: 'center' },
});
