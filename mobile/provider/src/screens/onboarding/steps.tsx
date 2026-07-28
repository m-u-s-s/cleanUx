import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { Button, TextInput, Icon } from '@/ui';
import { apiClient } from '@/api';
import { useTrades } from '@/trades';
import { colors, radius, spacing, typography } from '@/theme';

/**
 * Les cinq étapes du parcours de vérification prestataire.
 *
 * Chacune ne fait qu'UNE chose : amener l'utilisateur à satisfaire son validateur côté serveur.
 * Le serveur revalide systématiquement à la complétion — une étape ne passe donc jamais parce que
 * l'app l'a décidé, et un 422 signifie que la condition réelle n'est pas remplie.
 *
 * Chaque étape reçoit `onDone(payload)` : le parent appelle l'API de complétion et gère l'erreur,
 * pour que ces composants restent concentrés sur leur formulaire.
 */
export interface StepProps {
  onDone: (payload?: Record<string, unknown>) => void;
  submitting: boolean;
  error: string | null;
}

/** Version du contrat attendue par le validateur (metadata.required_version du seeder). */
export const CONTRACT_VERSION = '1.0';

function StepShell({ title, hint, children }: { title: string; hint?: string; children: React.ReactNode }) {
  return (
    <View style={styles.shell}>
      <Text style={styles.stepTitle}>{title}</Text>
      {hint ? <Text style={styles.stepHint}>{hint}</Text> : null}
      {children}
    </View>
  );
}

function StepError({ error }: { error: string | null }) {
  if (!error) return null;

  return (
    <View style={styles.error} accessibilityLiveRegion="polite">
      <Icon name="alert-circle-outline" size={18} color={colors.danger[600]} />
      <Text style={styles.errorText}>{error}</Text>
    </View>
  );
}

/**
 * Étape 1 — Profil. Le validateur exige `phone` en plus du nom et de l'email, seul champ que
 * l'inscription ne remplit pas.
 */
export function ProfileStep({ onDone, submitting, error }: StepProps) {
  const [phone, setPhone] = useState('');
  const [localError, setLocalError] = useState<string | null>(null);

  const save = useMutation({
    mutationFn: () => apiClient.put('/provider/profile', { phone }),
    onSuccess: () => onDone(),
    onError: () => setLocalError("Impossible d'enregistrer votre numéro. Réessayez."),
  });

  return (
    <StepShell
      title="Vos coordonnées"
      hint="Votre numéro permet aux clients et au support de vous joindre pendant une mission."
    >
      <TextInput
        label="Téléphone"
        value={phone}
        onChangeText={(t) => { setPhone(t); setLocalError(null); }}
        keyboardType="phone-pad"
        placeholder="+32 470 12 34 56"
      />
      <StepError error={localError ?? error} />
      <Button
        label="Continuer"
        onPress={() => (phone.trim() ? save.mutate() : setLocalError('Numéro requis'))}
        fullWidth
        size="lg"
        loading={save.isPending || submitting}
      />
    </StepShell>
  );
}

/**
 * Étape 2 — Contrat. `contract_templates` étant vide, le validateur emploie son repli par
 * version : on transmet la version acceptée, que le serveur compare à celle qu'il exige.
 */
export function ContractStep({ onDone, submitting, error }: StepProps) {
  const [accepted, setAccepted] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  return (
    <StepShell title="Contrat prestataire" hint={`Version ${CONTRACT_VERSION}`}>
      <ScrollView style={styles.contractBox} nestedScrollEnabled>
        <Text style={styles.contractText}>
          En tant que prestataire, je m'engage à intervenir aux horaires convenus, à traiter les
          clients avec respect, et à signaler sans délai tout incident survenu pendant une mission.
          {'\n\n'}
          Je reconnais intervenir en qualité d'indépendant ou pour le compte de ma société, et non
          comme salarié de la plateforme. Je reste responsable de mes obligations sociales et
          fiscales, ainsi que de la validité des assurances couvrant mon activité.
          {'\n\n'}
          Je m'engage à ne sous-traiter aucune mission sans accord préalable, et à respecter la
          confidentialité des informations auxquelles j'accède au domicile ou sur le site du client.
        </Text>
      </ScrollView>

      <TouchableOpacity
        style={styles.acceptRow}
        onPress={() => { setAccepted(v => !v); setLocalError(null); }}
        accessibilityRole="checkbox"
        accessibilityState={{ checked: accepted }}
        accessibilityLabel="J'accepte le contrat prestataire"
        testID="onboarding-accept-contract"
      >
        <View style={[styles.checkbox, accepted && styles.checkboxChecked]} />
        <Text style={styles.acceptText}>J'ai lu et j'accepte ce contrat</Text>
      </TouchableOpacity>

      <StepError error={localError ?? error} />
      <Button
        label="Signer et continuer"
        onPress={() =>
          accepted
            ? onDone({ terms_accepted_version: CONTRACT_VERSION })
            : setLocalError('Vous devez accepter le contrat pour continuer.')
        }
        fullWidth
        size="lg"
        loading={submitting}
      />
    </StepShell>
  );
}

/**
 * Étape 3 — Identité. La vérification est asynchrone : le prestataire la démarre, un service
 * tiers décide, et l'étape ne passera qu'une fois la décision `approved`. On ne prétend donc pas
 * la valider ici — on lance et on rafraîchit.
 */
export function KycStep({ onDone, submitting, error }: StepProps) {
  const [started, setStarted] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  const start = useMutation({
    mutationFn: () => apiClient.post('/provider/kyc/start'),
    onSuccess: () => { setStarted(true); setLocalError(null); },
    onError: () => setLocalError("Impossible de démarrer la vérification. Réessayez."),
  });

  return (
    <StepShell
      title="Vérification d'identité"
      hint="Contrôlée automatiquement. Elle peut prendre quelques minutes."
    >
      {started ? (
        <View style={styles.notice}>
          <Text style={styles.noticeText}>
            Vérification lancée. Une fois votre identité confirmée, cette étape se validera toute
            seule — vous pouvez revenir plus tard.
          </Text>
        </View>
      ) : null}

      <StepError error={localError ?? error} />

      {started ? (
        <Button label="J'ai terminé, vérifier" onPress={() => onDone()} fullWidth size="lg" loading={submitting} />
      ) : (
        <Button
          label="Démarrer la vérification"
          onPress={() => start.mutate()}
          fullWidth
          size="lg"
          loading={start.isPending}
        />
      )}
    </StepShell>
  );
}

/**
 * Étape 4 — Justificatifs. Le validateur exige une pièce d'identité déposée.
 *
 * L'envoi de fichier depuis React Native demande un sélecteur de documents natif ; tant qu'il
 * n'est pas branché, cette étape indique clairement ce qui est attendu plutôt que de laisser
 * croire à une action possible. Voir la réserve consignée dans l'écran parent.
 */
export function DocumentsStep({ onDone, submitting, error }: StepProps) {
  return (
    <StepShell
      title="Pièce d'identité"
      hint="Carte d'identité ou passeport, lisible et en cours de validité."
    >
      <View style={styles.notice}>
        <Text style={styles.noticeText}>
          Le dépôt depuis l'application arrive prochainement. En attendant, envoyez votre pièce
          d'identité à support@cleanux.com : elle sera rattachée à votre dossier.
        </Text>
      </View>

      <StepError error={error} />
      <Button label="J'ai envoyé ma pièce, vérifier" onPress={() => onDone()} fullWidth size="lg" loading={submitting} />
    </StepShell>
  );
}

/**
 * Étape 5 — Métiers. Satisfaite dès l'inscription, où le métier est déclaré : cet écran sert donc
 * surtout à confirmer, et à en ajouter. Le payload `trade_codes` est le troisième chemin accepté
 * par le validateur.
 */
export function SkillsStep({ onDone, submitting, error }: StepProps) {
  const { data: trades } = useTrades();
  const [selected, setSelected] = useState<string[]>([]);

  const toggle = (slug: string) =>
    setSelected(prev => (prev.includes(slug) ? prev.filter(s => s !== slug) : [...prev, slug]));

  return (
    <StepShell
      title="Vos métiers"
      hint="Vous pouvez en ajouter d'autres. Ils déterminent les missions qui vous seront proposées."
    >
      <View style={styles.tradeGrid}>
        {(trades ?? []).map(trade => {
          const active = selected.includes(trade.slug);

          return (
            <TouchableOpacity
              key={trade.id}
              style={[styles.tradeChip, active && styles.tradeChipSelected]}
              onPress={() => toggle(trade.slug)}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: active }}
              accessibilityLabel={trade.name}
              testID={`onboarding-trade-${trade.id}`}
            >
              <Text style={[styles.tradeChipText, active && styles.tradeChipTextSelected]}>{trade.name}</Text>
            </TouchableOpacity>
          );
        })}
      </View>

      <StepError error={error} />
      <Button
        label="Confirmer mes métiers"
        onPress={() => onDone(selected.length ? { trade_codes: selected } : undefined)}
        fullWidth
        size="lg"
        loading={submitting}
      />
    </StepShell>
  );
}

const styles = StyleSheet.create({
  shell: { gap: spacing.md },
  stepTitle: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.mode.tool.ink,
  },
  stepHint: { fontSize: typography.fontSize.sm, color: colors.surface[600], marginTop: -spacing.xs },
  contractBox: {
    maxHeight: 240,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.surface[200],
    backgroundColor: '#ffffff',
  },
  contractText: { fontSize: typography.fontSize.sm, lineHeight: 22, color: colors.surface[700] },
  acceptRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  acceptText: { flex: 1, fontSize: typography.fontSize.sm, color: colors.surface[700] },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: colors.surface[400],
    flexShrink: 0,
  },
  checkboxChecked: { backgroundColor: colors.brand[500], borderColor: colors.brand[500] },
  notice: {
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: colors.brand[50],
    borderWidth: 1,
    borderColor: colors.brand[500],
  },
  noticeText: { fontSize: typography.fontSize.sm, color: colors.brand[600], lineHeight: 20 },
  error: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.danger[500],
    backgroundColor: colors.danger[50],
  },
  errorText: { flex: 1, fontSize: typography.fontSize.sm, color: colors.danger[700] },
  tradeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  tradeChip: {
    paddingVertical: spacing.xs + 2,
    paddingHorizontal: spacing.sm + 2,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: colors.surface[200],
    backgroundColor: '#ffffff',
  },
  tradeChipSelected: { borderColor: colors.brand[600], backgroundColor: colors.brand[50] },
  tradeChipText: { fontSize: typography.fontSize.sm, color: colors.surface[600] },
  tradeChipTextSelected: { color: colors.brand[600], fontWeight: typography.fontWeight.semibold },
});
