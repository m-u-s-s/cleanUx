import React, { useState } from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatCentimes } from '@/format/money';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { Button, TextInput } from '@/ui';
import {
  useQuoteRevision,
  useSimulerLaRevision,
  useProposerLaRevision,
  useRetirerLaRevision,
  useDemanderDuRenfort,
} from '@/missions';
import type { MissionMediaItem } from '@/missions';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/**
 * LE NOUVEAU DEVIS, DEPUIS LE TERRAIN.
 *
 * ── QUAND, ET SEULEMENT QUAND ────────────────────────────────────────────────────────────────
 *
 * Au début, avant d'avoir touché à quoi que ce soit. Un imprévu découvert en travaillant — le
 * siphon démonté qui révèle les tuyaux — passe par le SUPPLÉMENT, qui s'ajoute au devis au lieu de
 * le remplacer. Le serveur ferme la fenêtre dès la première tâche cochée ou la première photo
 * « après », et DIT pourquoi : l'écran affiche ce motif plutôt qu'un formulaire inerte.
 *
 * ── ON SAISIT UN PRIX DE SERVICE, JAMAIS UN TOTAL ────────────────────────────────────────────
 *
 * Le serveur réapplique les remises du client. La simulation montre ce qu'il verra AVANT
 * d'engager : sans elle, le prestataire annoncerait de vive voix un chiffre qui ne serait pas celui
 * du téléphone, et le client se sentirait trompé en lisant l'écran.
 *
 * ── LA PREUVE EST OBLIGATOIRE ────────────────────────────────────────────────────────────────
 *
 * Au moins une photo « avant ». Sans elle, le client doit croire sur parole et l'arbitre doit
 * trancher sans matière — c'est-à-dire que l'abus devient gratuit.
 */
export function FieldQuoteRevision({
  missionId,
  photosAvant,
}: {
  missionId: number;
  photosAvant: MissionMediaItem[];
}) {
  const { t: tr } = useTraduction();
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { data } = useQuoteRevision(missionId);
  const simuler = useSimulerLaRevision(missionId);
  const proposer = useProposerLaRevision(missionId);
  const retirer = useRetirerLaRevision(missionId);
  const renfort = useDemanderDuRenfort(missionId);

  const [prix, setPrix] = useState('');
  const [motif, setMotif] = useState('');

  const revision = data?.revision ?? null;
  const fenetre = data?.window;

  const enCentimes = (saisie: string): number | null => {
    const valeur = Number(saisie.replace(',', '.'));

    return Number.isFinite(valeur) && valeur > 0 ? Math.round(valeur * 100) : null;
  };

  // Une proposition vit déjà : on montre son état, pas un second formulaire. Le serveur refuserait
  // de toute façon — deux propositions concurrentes rendraient le total indéterminé.
  if (revision && revision.awaiting_client) {
    return (
      <View style={styles.section} testID="revision-en-attente">
        <Text style={styles.titre}>{tr('field_quote_revision.nouveau_devis_envoye')}</Text>
        <Text style={styles.montant}>{euros(revision.revised_total_cents, revision.currency)}</Text>
        <Text style={styles.note}>
          Le client répond depuis son téléphone. Son devis d’origine était de{' '}
          {euros(revision.original_total_cents, revision.currency)}.
        </Text>
        <Button
          label={tr('field_quote_revision.retirer_ma_proposition')}
          variant="secondary"
          onPress={() =>
            retirer.mutate(revision.id, {
              onError: (e: { message?: string }) =>
                Alert.alert(tr('field_quote_revision.impossible'), e.message ?? 'La proposition n’a pas pu être retirée.'),
            })
          }
          loading={retirer.isPending}
          testID="retirer-revision"
        />
      </View>
    );
  }

  if (!fenetre?.open) {
    return (
      <View style={styles.section} testID="revision-fermee">
        <Text style={styles.titre}>{tr('field_quote_revision.nouveau_devis')}</Text>
        {/* LE MOTIF, PAS UN FORMULAIRE GRISÉ : il dit quel geste employer à la place. */}
        <Text style={styles.note}>{fenetre?.reason ?? 'Indisponible sur cette mission.'}</Text>
      </View>
    );
  }

  const envoyer = () => {
    const centimes = enCentimes(prix);

    if (centimes === null) {
      Alert.alert(tr('field_quote_revision.incomplet'), tr('field_quote_revision.indiquez_ce_que_vaut_reellement'));

      return;
    }

    if (motif.trim().length < 3) {
      Alert.alert(tr('field_quote_revision.incomplet'), tr('field_quote_revision.dites_au_client_ce_qui'));

      return;
    }

    if (photosAvant.length === 0) {
      Alert.alert(
        tr('field_quote_revision.photo_obligatoire'),
        tr('field_quote_revision.prenez_dabord_une_photo_avant'),
      );

      return;
    }

    proposer.mutate(
      { serviceCents: centimes, reasonText: motif.trim(), mediaIds: photosAvant.map((p) => p.id) },
      {
        onSuccess: () => {
          setPrix('');
          setMotif('');
        },
        onError: (e: { message?: string }) =>
          Alert.alert(tr('field_quote_revision.impossible'), e.message ?? 'La révision n’a pas pu être envoyée.'),
      },
    );
  };

  const demanderDuRenfort = () => {
    if (motif.trim().length < 3) {
      Alert.alert(tr('field_quote_revision.incomplet'), tr('field_quote_revision.dites_ce_qui_justifie_le'));

      return;
    }

    renfort.mutate(
      { reason: motif.trim() },
      {
        onSuccess: () => {
          setMotif('');
          Alert.alert(tr('field_quote_revision.renfort_demande'), tr('field_quote_revision.votre_demande_est_ouverte_quelquun'));
        },
        onError: (e: { message?: string }) =>
          Alert.alert(tr('field_quote_revision.impossible'), e.message ?? 'La demande n’a pas pu être ouverte.'),
      },
    );
  };

  const simulation = () => {
    const centimes = enCentimes(prix);

    if (centimes === null) {
      Alert.alert(tr('field_quote_revision.incomplet'), tr('field_quote_revision.indiquez_un_prix_a_simuler'));

      return;
    }

    simuler.mutate(centimes, {
      onSuccess: (quote) =>
        Alert.alert(
          tr('field_quote_revision.ce_que_le_client_verra'),
          // `'EUR'` était écrit en dur ici : la simulation annonçait au prestataire un montant
          // dans une monnaie que son client ne paiera pas — juste avant qu'il l'énonce à voix
          // haute. Le balayage sur le symbole « € » ne pouvait pas voir un code ISO.
          `${euros(quote.total_cents, fenetre?.currency ?? '')} — remises du client réappliquées.`,
        ),
      onError: (e: { message?: string }) =>
        Alert.alert(tr('field_quote_revision.impossible'), e.message ?? 'La simulation a échoué.'),
    });
  };

  return (
    <View style={styles.section} testID="revision-formulaire">
      <Text style={styles.titre}>{tr('field_quote_revision.nouveau_devis')}</Text>
      <Text style={styles.note}>
        À faire maintenant, avant de commencer. Un imprévu découvert en travaillant se propose en
        supplément.
      </Text>

      <TextInput
        /* Le symbole etait ecrit en dur : un prestataire marocain annoncait un prix
           dans une monnaie que son client ne paiera pas. */
        label={`Ce que vaut la prestation (${symboleDeLaDevise(fenetre?.currency)})`}
        value={prix}
        onChangeText={setPrix}
        placeholder="300"
        keyboardType="decimal-pad"
        testID="revision-prix"
      />

      <TextInput
        label={tr('field_quote_revision.ce_que_vous_constatez')}
        value={motif}
        onChangeText={setMotif}
        placeholder={tr('field_quote_revision.deux_cents_metres_carres_annonces')}
        multiline
        testID="revision-motif"
      />

      <Text style={styles.preuve}>
        {photosAvant.length === 0
          ? 'Aucune photo « avant » : ajoutez-en une plus haut, elle est obligatoire.'
          : `${photosAvant.length} photo(s) « avant » jointe(s).`}
      </Text>

      <View style={styles.actions}>
        <Button
          label={tr('field_quote_revision.simuler')}
          variant="secondary"
          onPress={simulation}
          loading={simuler.isPending}
          testID="revision-simuler"
        />
        <Button
          label={tr('field_quote_revision.envoyer_au_client')}
          onPress={envoyer}
          loading={proposer.isPending}
          testID="revision-envoyer"
        />
      </View>

      {/*
        DEUX RÉPONSES AU MÊME CONSTAT, et c'est pourquoi elles vivent côte à côte.

        Le chantier est plus gros que prévu : soit le prix change, soit quelqu'un vient. Séparer les
        deux gestes à l'écran ferait choisir le premier trouvé — et le premier trouvé serait la
        renégociation, celle qui met le client sous pression.
      */}
      <Button
        label={tr('field_quote_revision.ou_demander_du_renfort')}
        variant="secondary"
        onPress={demanderDuRenfort}
        loading={renfort.isPending}
        fullWidth
        testID="revision-renfort"
      />
    </View>
  );
}

/**
 * LE SYMBOLE D'UNE DEVISE, tiré d'`Intl` plutôt que d'une table recopiée.
 *
 * Formater zéro puis retirer chiffres et séparateurs laisse le symbole. Une table maison
 * aurait vieilli dès la première devise ajoutée au catalogue.
 *
 * L'espace insécable est écrit par son code : recopiée telle quelle, elle serait invisible
 * dans le fichier et le prochain lecteur la prendrait pour une espace ordinaire.
 */
function symboleDeLaDevise(devise?: string | null): string {
  const symbole = formatCentimes(0, devise).replace(/[\d\s\u00a0.,]/g, '');

  return symbole !== '' ? symbole : (devise ?? '').toUpperCase();
}

/** Un montant EN PROVENANCE DU SERVEUR, mis en forme. Rien n'est calculé ici. */
function euros(centimes: number, devise: string): string {
  // La locale ET la division vivaient ici. Les deux sont dans le formateur partage.
  return formatCentimes(centimes, devise);
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  section: { marginBottom: spacing.md, gap: spacing.sm },
  titre: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  note: { fontSize: typography.fontSize.xs, color: t.textSecondary, lineHeight: 17 },
  montant: { fontSize: typography.fontSize.xl, fontWeight: '700', color: t.text },
  preuve: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    backgroundColor: t.tint.warning,
    borderRadius: radius.sm,
    padding: spacing.sm,
  },
  actions: { flexDirection: 'row', gap: spacing.sm },
});
