import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Linking } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { Button, TextInput, Icon } from '@/ui';
import { apiClient } from '@/api';
import { useTrades } from '@/trades';
import {
  useOnboardingDocuments,
  useKycStatus,
  useRenderContract,
  useServiceZones,
  useSignContract,
  isKycPending,
  isKycVerified,
  isKycRefused,
  isKycUnderReview,
  useSyncKycStatus,
  useVehicleDossier,
  type DocumentRequirement,
} from '@/onboarding';
import { useAuth } from '@/auth';
import { pickDocument, pickImage, rejectionReason, type PickedFile } from './documentPicker';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

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
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.shell}>
      <Text style={styles.stepTitle}>{title}</Text>
      {hint ? <Text style={styles.stepHint}>{hint}</Text> : null}
      {children}
    </View>
  );
}

function StepError({ error }: { error: string | null }) {
  const styles = stylesFor(useThemeColors());

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
  const styles = stylesFor(useThemeColors());

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

/** Modèle de contrat prestataire, seedé par ContractTemplatesSeeder. */
const CONTRACT_TEMPLATE_CODE = 'provider_agreement';

/**
 * Retire le balisage du contrat rendu.
 *
 * Le service rend du HTML ; React Native n'en affiche pas. Plutôt qu'un moteur de rendu ou une
 * WebView pour un texte juridique linéaire, on en extrait le texte — et on le fait ici plutôt
 * qu'au fil du JSX pour que le résultat reste lisible et testable.
 */
function contractPlainText(html: string): string {
  return html
    .replace(/<\s*br\s*\/?>/gi, '\n')
    .replace(/<\/\s*(p|div|li|h[1-6])\s*>/gi, '\n\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

/**
 * Étape 2 — Contrat.
 *
 * L'écran affichait un texte codé en dur et « signait » en transmettant un numéro de version :
 * aucune signature n'existait en base, donc aucune piste d'audit — alors que Contracts v2
 * (modèles versionnés, rendu personnalisé, signature horodatée avec empreinte, PDF) était
 * entièrement construit et n'était appelé de nulle part.
 *
 * Le contrat est maintenant rendu par le serveur, aux données du prestataire, et signé pour de
 * bon. Le texte de repli n'est employé que là où aucun modèle n'est publié — le validateur
 * bascule alors lui aussi sur la version, plutôt que de rendre l'étape infranchissable.
 */
export function ContractStep({ onDone, submitting, error }: StepProps) {
  const styles = stylesFor(useThemeColors());

  const [accepted, setAccepted] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);
  const render = useRenderContract();
  const sign = useSignContract();
  const { user } = useAuth();

  // Un seul rendu par ouverture de l'étape : chaque appel crée un document côté serveur.
  React.useEffect(() => {
    render.mutate({ templateCode: CONTRACT_TEMPLATE_CODE });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const document = render.data ?? null;

  const submit = () => {
    if (!accepted) {
      setLocalError('Vous devez accepter le contrat pour continuer.');

      return;
    }

    setLocalError(null);

    // Sans document, aucun modèle n'est publié dans cet environnement : on emprunte le repli par
    // version, que le validateur accepte dans ce cas précis.
    if (!document) {
      onDone({ terms_accepted_version: CONTRACT_VERSION });

      return;
    }

    sign.mutate(
      { documentId: document.id, signerName: user?.name ?? 'Prestataire' },
      {
        onSuccess: () => onDone({ template_code: CONTRACT_TEMPLATE_CODE }),
        onError: () => setLocalError('La signature a échoué. Vérifiez votre connexion, puis réessayez.'),
      },
    );
  };

  return (
    <StepShell
      title="Contrat prestataire"
      hint={document ? 'Lisez le contrat, puis signez.' : `Version ${CONTRACT_VERSION}`}
    >
      <ScrollView style={styles.contractBox} nestedScrollEnabled testID="onboarding-contract-body">
        <Text style={styles.contractText}>
          {document
            ? contractPlainText(document.body_rendered_html)
            : `En tant que prestataire, je m'engage à intervenir aux horaires convenus, à traiter les clients avec respect, et à signaler sans délai tout incident survenu pendant une mission.

Je reconnais intervenir en qualité d'indépendant ou pour le compte de ma société, et non comme salarié de la plateforme. Je reste responsable de mes obligations sociales et fiscales, ainsi que de la validité des assurances couvrant mon activité.

Je m'engage à ne sous-traiter aucune mission sans accord préalable, et à respecter la confidentialité des informations auxquelles j'accède au domicile ou sur le site du client.`}
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
        onPress={submit}
        fullWidth
        size="lg"
        loading={submitting || sign.isPending}
      />
    </StepShell>
  );
}

/**
 * Étape 3 — Identité. Selfie, détection du vivant et lecture de la pièce, chez un prestataire
 * d'identité tiers.
 *
 * `POST /provider/kyc/start` rendait déjà `hosted_flow_url` — l'adresse du parcours à ouvrir —
 * mais elle n'était JAMAIS ouverte : l'écran affichait « vérification lancée » alors que rien ne
 * s'était produit, et le prestataire attendait indéfiniment une décision qui ne viendrait pas.
 * De même, `GET /provider/kyc/status` existait sans être appelé nulle part.
 *
 * La décision tombe par webhook, à un moment que l'application ne choisit pas : on ouvre donc le
 * parcours, puis on interroge l'état jusqu'à ce qu'il soit tranché.
 */
export function KycStep({ onDone, submitting, error }: StepProps) {
  const styles = stylesFor(useThemeColors());

  const { data: status, refetch } = useKycStatus();
  const [localError, setLocalError] = useState<string | null>(null);

  const sync = useSyncKycStatus();

  /**
   * Demande au serveur d'aller chercher la décision auprès du prestataire d'identité.
   *
   * `GET /kyc/status` ne fait que relire la ligne en base : tant que rien ne l'a mise à jour,
   * elle reste « en attente ». La décision arrive normalement par webhook, mais rien ne la
   * réclame quand celui-ci n'arrive pas — en développement, où le fournisseur est simulé, elle
   * n'arriverait jamais. `POST /kyc/verifications/{id}/sync` est le point d'entrée prévu pour
   * cela, et il n'était appelé de nulle part.
   */
  const refreshDecision = () => {
    const id = status?.verification_id;
    if (!id) {
      void refetch();

      return;
    }

    setLocalError(null);
    sync.mutate(
      { verificationId: id },
      {
        onSuccess: () => void refetch(),
        onError: () => setLocalError('Impossible de récupérer le résultat. Réessayez.'),
      },
    );
  };

  const start = useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post('/provider/kyc/start');

      return data as { hosted_flow_url?: string | null };
    },
    onSuccess: async (data) => {
      setLocalError(null);

      // Linking plutôt qu'expo-web-browser : module cœur de React Native, déjà employé par le
      // parcours Stripe. Ajouter une dépendance native imposerait de reconstruire le client de
      // développement, et son absence dans une build existante ferait planter l'écran.
      if (data.hosted_flow_url) {
        try {
          await Linking.openURL(data.hosted_flow_url);
        } catch {
          setLocalError("Impossible d'ouvrir la vérification. Vérifiez votre navigateur, puis réessayez.");
        }
      }

      void refetch();
    },
    onError: () => setLocalError('Impossible de démarrer la vérification. Réessayez.'),
  });

  const verified = isKycVerified(status);
  const pending = isKycPending(status);
  const underReview = isKycUnderReview(status);
  const refused = isKycRefused(status);

  return (
    <StepShell
      title="Vérification d'identité"
      hint="Selfie et pièce d'identité, contrôlés automatiquement. Environ trois minutes."
    >
      {verified ? (
        <View style={styles.notice}>
          <Text style={styles.noticeText} testID="kyc-verified">
            Identité vérifiée ✓
          </Text>
        </View>
      ) : null}

      {pending || underReview ? (
        <View style={styles.notice}>
          <Text style={styles.noticeText} testID="kyc-pending">
            {underReview
              ? "Votre dossier est examiné par une personne de notre équipe. Vous n'avez rien à faire de plus."
              : "Vérification en cours. Elle se validera toute seule dès que votre identité sera confirmée — vous pouvez fermer l'application et revenir plus tard."}
          </Text>
        </View>
      ) : null}

      {refused ? (
        <View style={styles.notice}>
          <Text style={styles.noticeText} testID="kyc-refused">
            Votre vérification n'a pas abouti
            {status?.rejection_reason ? ` : ${status.rejection_reason}` : ''}. Vous pouvez la
            relancer.
          </Text>
        </View>
      ) : null}

      <StepError error={localError ?? error} />

      {verified ? (
        <Button label="Continuer" onPress={() => onDone()} fullWidth size="lg" loading={submitting} />
      ) : underReview ? (
        // Un examen humain est en cours : relancer créerait une seconde vérification pour rien.
        // Reste l'actualisation, pour ne pas dépendre du seul webhook.
        <Button
          label="Actualiser"
          onPress={() => refreshDecision()}
          variant="secondary"
          fullWidth
          size="lg"
          loading={sync.isPending}
        />
      ) : pending ? (
        // Une vérification est ouverte : c'est sa décision qu'il faut aller chercher, pas une
        // nouvelle vérification. En mode simulé comme avant l'arrivée du webhook, c'est le seul
        // moyen de faire avancer l'étape — sans quoi elle reste « en cours » indéfiniment.
        <Button
          label="J'ai terminé, vérifier"
          onPress={() => refreshDecision()}
          fullWidth
          size="lg"
          loading={sync.isPending}
        />
      ) : (
        <Button
          label={refused ? 'Reprendre la vérification' : 'Démarrer la vérification'}
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
 * Étape 4 — Justificatifs. Le validateur exige une pièce d'identité déposée
 * (provider_onboarding_documents, type identity_card).
 *
 * Deux sources : photographier la pièce, ou choisir un fichier déjà scanné. La photo couvre le
 * cas courant, le fichier couvre les PDF. Le format et la taille sont refusés ici quand le
 * serveur les refuserait — inutile de faire remonter 40 Mo pour recevoir un 422.
 */
/**
 * Étape 4 — Justificatifs.
 *
 * Un seul type était accepté, écrit en dur : `identity_card`. Un électricien n'était donc jamais
 * invité à déposer sa certification, ni un peintre son assurance — que la validation finale du
 * dossier EXIGE pourtant. Le dossier pouvait ainsi être complet côté prestataire et impossible à
 * approuver côté admin, sans que rien ne l'indique.
 *
 * La liste vient maintenant du serveur, dérivée des métiers déclarés, et chaque pièce porte son
 * propre état : en attente de vérification, approuvée, ou refusée avec son motif — c'est ce qui
 * permet de corriger un dossier sans attendre un email.
 */
export function DocumentsStep({ onDone, submitting, error }: StepProps) {
  const styles = stylesFor(useThemeColors());

  const { data, isLoading, refetch } = useOnboardingDocuments();
  const [pending, setPending] = useState<Record<string, PickedFile>>({});
  const [localError, setLocalError] = useState<string | null>(null);
  const [uploadingType, setUploadingType] = useState<string | null>(null);

  const requirements = data?.requirements ?? [];

  const choose = async (type: string, pick: () => Promise<PickedFile | null>) => {
    setLocalError(null);
    try {
      const picked = await pick();
      if (!picked) return; // annulation : ce n'est pas une erreur

      const reason = rejectionReason(picked, picked.size);
      if (reason) {
        setLocalError(reason);

        return;
      }
      setPending(prev => ({ ...prev, [type]: picked }));
    } catch (e) {
      setLocalError(e instanceof Error ? e.message : 'Sélection impossible.');
    }
  };

  const upload = useMutation({
    mutationFn: async (type: string) => {
      const file = pending[type]!;
      const body = new FormData();
      body.append('document_type', type);
      // La forme { uri, name, type } est celle qu'attend FormData en React Native pour un
      // fichier local ; un Blob ne fonctionne pas ici.
      body.append('file', { uri: file.uri, name: file.name, type: file.mimeType } as never);

      return apiClient.post('/provider/onboarding/documents', body, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    },
    onMutate: (type: string) => setUploadingType(type),
    onSettled: () => setUploadingType(null),
    onSuccess: (_res, type) => {
      setPending(prev => {
        const next = { ...prev };
        delete next[type];

        return next;
      });
      void refetch();
    },
    onError: () => setLocalError("L'envoi a échoué. Vérifiez votre connexion, puis réessayez."),
  });

  /**
   * Une pièce refusée doit être redéposée : elle ne compte pas comme fournie, sans quoi le
   * prestataire croirait son dossier complet alors qu'il est bloqué à la revue.
   */
  const isSatisfied = (requirement: DocumentRequirement): boolean =>
    requirement.document !== null && requirement.document.status !== 'rejected';

  const missing = requirements.filter(r => r.required && !isSatisfied(r));

  if (isLoading) {
    return (
      <StepShell title="Vos justificatifs">
        <Text style={styles.stepHint}>Chargement des pièces demandées…</Text>
      </StepShell>
    );
  }

  return (
    <StepShell
      title="Vos justificatifs"
      hint="Les pièces demandées dépendent de vos métiers. PDF, JPG ou PNG, 10 Mo maximum."
    >
      {requirements.map(requirement => {
        const picked = pending[requirement.type];
        const document = requirement.document;
        const rejected = document?.status === 'rejected';

        return (
          <View
            key={requirement.type}
            style={styles.documentRow}
            testID={`onboarding-document-${requirement.type}`}
          >
            <View style={styles.documentHead}>
              <Icon
                name={
                  isSatisfied(requirement)
                    ? 'checkmark-circle'
                    : rejected
                      ? 'alert-circle-outline'
                      : 'ellipse-outline'
                }
                size={18}
                color={
                  isSatisfied(requirement)
                    ? colors.success[600]
                    : rejected
                      ? colors.danger[600]
                      : colors.surface[400]
                }
              />
              <Text style={styles.documentLabel}>{requirement.label}</Text>
            </View>

            {requirement.help ? <Text style={styles.documentHelp}>{requirement.help}</Text> : null}

            {rejected && document?.rejection_reason ? (
              <Text style={styles.documentRejected} testID={`document-rejected-${requirement.type}`}>
                Refusé : {document.rejection_reason}
              </Text>
            ) : null}

            {document && !rejected ? (
              <Text style={styles.documentStatus}>
                {document.status === 'approved' ? 'Vérifiée ✓' : 'En cours de vérification'}
                {document.file_name ? ` — ${document.file_name}` : ''}
              </Text>
            ) : null}

            {picked ? (
              <View style={styles.filePicked}>
                <Icon name="document-text-outline" size={18} color={colors.success[600]} />
                <Text style={styles.fileName} numberOfLines={1}>{picked.name}</Text>
                <Button
                  label="Envoyer"
                  onPress={() => upload.mutate(requirement.type)}
                  variant="secondary"
                  loading={uploadingType === requirement.type}
                />
              </View>
            ) : !document || rejected ? (
              <View style={styles.pickRow}>
                <Button
                  label="Prendre en photo"
                  onPress={() => choose(requirement.type, () => pickImage('camera'))}
                  variant="secondary"
                  fullWidth
                />
                <Button
                  label="Choisir un fichier"
                  onPress={() => choose(requirement.type, pickDocument)}
                  variant="secondary"
                  fullWidth
                />
              </View>
            ) : null}
          </View>
        );
      })}

      <StepError error={localError ?? error} />
      <Button
        label={missing.length === 0 ? 'Continuer' : `${missing.length} pièce(s) manquante(s)`}
        onPress={() =>
          missing.length === 0
            ? onDone()
            : setLocalError(`Déposez : ${missing.map(m => m.label).join(', ')}.`)
        }
        fullWidth
        size="lg"
        loading={submitting}
      />
    </StepShell>
  );
}

/**
 * Étape 5 — Métiers. Satisfaite dès l'inscription, où le métier est déclaré : cet écran sert donc
 * surtout à confirmer, et à en ajouter. Le payload `trade_codes` est le troisième chemin accepté
 * par le validateur.
 */
export function SkillsStep({ onDone, submitting, error }: StepProps) {
  const styles = stylesFor(useThemeColors());

  const { data: trades } = useTrades();
  const { data: zones } = useServiceZones();
  const [selected, setSelected] = useState<string[]>([]);
  const [selectedZones, setSelectedZones] = useState<number[]>([]);
  const [localError, setLocalError] = useState<string | null>(null);

  const toggle = (slug: string) =>
    setSelected(prev => (prev.includes(slug) ? prev.filter(s => s !== slug) : [...prev, slug]));

  const toggleZone = (id: number) =>
    setSelectedZones(prev => (prev.includes(id) ? prev.filter(z => z !== id) : [...prev, id]));

  /**
   * Les zones ne passent pas par la complétion d'étape : elles se persistent sur
   * `POST /provider/onboarding/skills`, qui les accepte depuis toujours mais qu'aucun écran
   * n'appelait avec elles. On enregistre donc AVANT de valider l'étape.
   */
  const save = useMutation({
    mutationFn: () =>
      apiClient.post('/provider/onboarding/skills', {
        skills: selected,
        service_zone_ids: selectedZones,
      }),
    onSuccess: () => onDone({ trade_codes: selected }),
    onError: () => setLocalError("Impossible d'enregistrer vos métiers. Réessayez."),
  });

  const submit = () => {
    if (selected.length === 0) {
      setLocalError('Choisissez au moins un métier.');

      return;
    }

    if (selectedZones.length === 0) {
      setLocalError("Choisissez au moins une zone d'intervention.");

      return;
    }

    setLocalError(null);
    save.mutate();
  };

  return (
    <StepShell
      title="Vos métiers"
      hint="Ils déterminent les missions qui vous seront proposées, et où."
    >
      <View style={styles.tradeGrid}>
        {(trades ?? []).map(trade => {
          const active = selected.includes(trade.slug);

          return (
            <TouchableOpacity
              key={trade.id}
              style={[styles.tradeChip, active && styles.tradeChipSelected]}
              onPress={() => { toggle(trade.slug); setLocalError(null); }}
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

      <Text style={styles.stepHint}>Vos zones d'intervention</Text>
      <View style={styles.tradeGrid}>
        {(zones ?? []).map(zone => {
          const active = selectedZones.includes(zone.id);

          return (
            <TouchableOpacity
              key={zone.id}
              style={[styles.tradeChip, active && styles.tradeChipSelected]}
              onPress={() => { toggleZone(zone.id); setLocalError(null); }}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: active }}
              accessibilityLabel={zone.name}
              testID={`onboarding-zone-${zone.id}`}
            >
              <Text style={[styles.tradeChipText, active && styles.tradeChipTextSelected]}>{zone.name}</Text>
            </TouchableOpacity>
          );
        })}
      </View>

      <StepError error={localError ?? error} />
      <Button
        label="Confirmer mes métiers"
        onPress={submit}
        fullWidth
        size="lg"
        loading={submitting || save.isPending}
      />
    </StepShell>
  );
}

/**
 * VOTRE VÉHICULE — pour les seuls métiers sous règles taxi.
 *
 * L'étape est présentée à TOUT LE MONDE, comme toutes les autres du parcours, et se replie d'elle-
 * même quand personne n'est concerné : le serveur dit `required: false`, et l'écran propose de
 * passer plutôt que de réclamer une voiture à un jardinier.
 *
 * LA DATE DE PREMIÈRE IMMATRICULATION est le champ qui compte. C'est elle qui donne l'âge, et c'est
 * pour elle qu'Uber, Bolt et Heetch réclament la carte grise : l'année du modèle ne dit pas quand
 * la voiture a pris la route.
 */
export function VehicleStep({ onDone, submitting, error }: StepProps) {
  const styles = stylesFor(useThemeColors());

  const { data, isLoading, refetch } = useVehicleDossier();
  const [plate, setPlate] = useState('');
  const [brand, setBrand] = useState('');
  const [model, setModel] = useState('');
  const [registeredAt, setRegisteredAt] = useState('');
  const [localError, setLocalError] = useState<string | null>(null);

  React.useEffect(() => {
    if (!data?.vehicle) return;
    setPlate(data.vehicle.plate ?? '');
    setBrand(data.vehicle.brand ?? '');
    setModel(data.vehicle.model ?? '');
    setRegisteredAt(data.vehicle.registered_at ?? '');
  }, [data?.vehicle]);

  const save = useMutation({
    mutationFn: async () =>
      apiClient.post('/provider/onboarding/vehicle', {
        plate,
        brand: brand || null,
        model: model || null,
        registered_at: registeredAt || null,
      }),
    onSuccess: () => { void refetch(); onDone(); },
    onError: () => setLocalError("L'enregistrement a échoué. Vérifiez la plaque et la date, puis réessayez."),
  });

  if (isLoading) {
    return (
      <StepShell title="Votre véhicule">
        <Text style={styles.stepHint}>Chargement…</Text>
      </StepShell>
    );
  }

  if (data && !data.required) {
    return (
      <StepShell
        title="Votre véhicule"
        hint="Aucun de vos métiers n’exige de véhicule déclaré. Vous pouvez passer cette étape."
      >
        <Button label="Continuer" onPress={() => onDone()} fullWidth size="lg" loading={submitting} />
      </StepShell>
    );
  }

  const submit = () => {
    setLocalError(null);

    if (plate.trim().length < 4) {
      setLocalError('Indiquez la plaque d’immatriculation du véhicule.');

      return;
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(registeredAt.trim())) {
      setLocalError('Indiquez la date de première immatriculation au format AAAA-MM-JJ (elle figure sur la carte grise).');

      return;
    }

    save.mutate();
  };

  return (
    <StepShell
      title="Votre véhicule"
      hint={`Exigé pour : ${(data?.trades ?? []).join(', ') || 'vos métiers de transport'}. Le véhicule doit avoir moins de ${data?.max_age_years ?? 4} ans.`}
    >
      <TextInput label="Plaque d’immatriculation" value={plate} onChangeText={setPlate} autoCapitalize="characters" />
      <TextInput label="Marque" value={brand} onChangeText={setBrand} />
      <TextInput label="Modèle" value={model} onChangeText={setModel} />
      <TextInput
        label="Première immatriculation (AAAA-MM-JJ)"
        value={registeredAt}
        onChangeText={setRegisteredAt}
        placeholder="2023-04-17"
      />

      {/* Le verdict est affiché AVANT l'envoi : découvrir un refus après coup fait recommencer. */}
      {data && !data.compliant && data.reason ? (
        <Text style={styles.stepHint}>{data.reason}</Text>
      ) : null}

      {data && !data.registration_document_uploaded ? (
        <Text style={styles.stepHint}>
          Pensez à déposer le certificat d’immatriculation à l’étape « justificatifs » : c’est lui qui
          atteste la date.
        </Text>
      ) : null}

      <StepError error={localError ?? error} />
      <Button
        label="Enregistrer mon véhicule"
        onPress={submit}
        fullWidth
        size="lg"
        loading={submitting || save.isPending}
      />
    </StepShell>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  shell: { gap: spacing.md },
  stepTitle: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.mode.tool.ink,
  },
  stepHint: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: -spacing.xs },
  contractBox: {
    maxHeight: 240,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: t.border,
    backgroundColor: t.card,
  },
  contractText: { fontSize: typography.fontSize.sm, lineHeight: 22, color: t.text },
  acceptRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  acceptText: { flex: 1, fontSize: typography.fontSize.sm, color: t.text },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: t.textMuted,
    flexShrink: 0,
  },
  checkboxChecked: { backgroundColor: colors.brand[500], borderColor: colors.brand[500] },
  notice: {
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: t.tint.brand,
    borderWidth: 1,
    borderColor: colors.brand[500],
  },
  noticeText: { fontSize: typography.fontSize.sm, color: t.brandText, lineHeight: 20 },
  error: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.danger[500],
    backgroundColor: t.tint.danger,
  },
  errorText: { flex: 1, fontSize: typography.fontSize.sm, color: t.danger },
  pickRow: { gap: spacing.sm },
  documentRow: {
    gap: spacing.xs,
    paddingVertical: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: t.border,
  },
  documentHead: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  documentLabel: {
    flex: 1,
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  documentHelp: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  documentStatus: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  // danger[700] sur fond blanc : le motif de refus est ce qu'il faut lire en premier.
  documentRejected: { fontSize: typography.fontSize.sm, color: t.danger },
  filePicked: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.success[600],
    backgroundColor: t.card,
  },
  fileName: { flex: 1, fontSize: typography.fontSize.sm, color: t.text },
  fileRemove: { fontSize: typography.fontSize.sm, color: t.danger, fontWeight: typography.fontWeight.semibold },
  tradeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  tradeChip: {
    paddingVertical: spacing.xs + 2,
    paddingHorizontal: spacing.sm + 2,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: t.border,
    backgroundColor: t.card,
  },
  tradeChipSelected: { borderColor: colors.brand[600], backgroundColor: t.tint.brand },
  tradeChipText: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  tradeChipTextSelected: { color: t.brandText, fontWeight: typography.fontWeight.semibold },
});
