/**
 * RETIRER SON CONSENTEMENT AU CONTRÔLE FACIAL.
 *
 * `useWithdrawFaceConsent` existait, la route `POST /provider/face-check/consent/withdraw`
 * existait, et son commentaire annonçait même « la conséquence est annoncée par l'écran AVANT
 * l'appel ». Cet écran-là n'a jamais été écrit : le hook n'avait AUCUN appelant.
 *
 * Ce n'est pas un détail d'ergonomie. Un visage de référence est une donnée biométrique — une
 * catégorie particulière au sens de l'article 9 du RGPD — et le droit de retirer son
 * consentement doit être aussi simple à exercer qu'il l'a été à donner.
 *
 * LA CONSÉQUENCE EST DITE AVANT, PAS APRÈS. Le retrait révoque le profil ET le bloque : le
 * prestataire ne peut plus intervenir sur les métiers qui exigent le contrôle.
 *
 * LE TEXTE QUI ENGAGE VIENT DU SERVEUR. `consent_text` et `consent_legal_note` arrivent déjà
 * traduits, et le module l'écrit lui-même : « le recopier ici donnerait deux versions d'un texte
 * relu une seule fois, et c'est celle qu'on n'aurait pas relue qui s'afficherait ». Le repli
 * codé ici ne sert que si le serveur se tait.
 */
import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Button, Divider, Screen } from '@/ui';
import { radius, spacing, typography, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useFaceCheckStatus, useWithdrawFaceConsent } from '@/faceCheck';
import { useTraduction } from '@/i18n';

export function FaceConsentScreen() {
  const { t: tr } = useTraduction();
  const jetons = useThemeColors();
  const styles = stylesFor(jetons);
  const navigation = useNavigation();

  const { data: statut } = useFaceCheckStatus(true);
  const retrait = useWithdrawFaceConsent();

  const [retire, setRetire] = useState(false);

  const demanderLeRetrait = () => {
    Alert.alert(
      tr('face_consent.retirer_votre_consentement'),
      tr('face_consent.votre_visage_de_reference_sera')
        + "qui exigent un contrôle d'identité tant que vous ne l'aurez pas ré-enregistré.",
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Retirer',
          style: 'destructive',
          onPress: () => {
            retrait.mutate(undefined, {
              onSuccess: () => setRetire(true),
              onError: (e: unknown) => Alert.alert(
                tr('face_consent.retrait_impossible'),
                e instanceof Error ? e.message : 'Réessayez dans un instant.',
              ),
            });
          },
        },
      ],
    );
  };

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.corps}>
        <Text style={styles.titre}>{tr('face_consent.controle_facial')}</Text>

        <Text style={styles.texte}>
          Votre visage de référence sert à vérifier, au moment d’une intervention, que la personne
          qui se présente est bien celle qui a accepté la mission.
        </Text>

        {statut?.consent_text ? (
          <Text style={styles.texte}>{statut.consent_text}</Text>
        ) : null}

        <View style={styles.encart}>
          <Text style={styles.encartTitre}>{tr('face_consent.donnee_biometrique')}</Text>
          <Text style={styles.encartTexte}>
            {statut?.consent_legal_note
              ?? 'Catégorie particulière au sens de l’article 9 du RGPD. Vous pouvez retirer votre '
                + 'consentement à tout moment, et l’image de référence est alors supprimée.'}
          </Text>
        </View>

        {statut?.consent_version ? (
          <Text style={styles.precision}>Version du consentement : {statut.consent_version}</Text>
        ) : null}

        <Divider />

        {retire ? (
          <View style={styles.encart}>
            <Text style={styles.encartTitre}>{tr('face_consent.consentement_retire')}</Text>
            <Text style={styles.encartTexte}>
              Votre visage de référence a été supprimé. Vous ne pourrez plus intervenir sur les
              métiers qui exigent un contrôle d’identité tant que vous ne l’aurez pas ré-enregistré.
            </Text>
            <View style={styles.action}>
              <Button label={tr('face_consent.retour')} onPress={() => navigation.goBack()} variant="secondary" fullWidth />
            </View>
          </View>
        ) : (
          <>
            <Text style={styles.avertissement}>
              Retirer votre consentement vous empêchera d’intervenir sur les métiers qui exigent
              ce contrôle, jusqu’à un nouvel enregistrement.
            </Text>

            <View style={styles.action}>
              <Button
                label={tr('face_consent.retirer_mon_consentement')}
                onPress={demanderLeRetrait}
                variant="danger"
                fullWidth
                loading={retrait.isPending}
              />
            </View>
          </>
        )}
      </ScrollView>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  corps: { padding: spacing.lg, gap: spacing.md },

  titre: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },

  texte: { fontSize: typography.fontSize.sm, lineHeight: 21, color: t.textSecondary },

  encart: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: t.border,
    backgroundColor: t.cardSubtle,
    padding: spacing.md,
    gap: spacing.xs,
  },

  encartTitre: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.semibold,
    letterSpacing: 0.6,
    textTransform: 'uppercase',
    color: t.textMuted,
  },

  encartTexte: { fontSize: typography.fontSize.sm, lineHeight: 20, color: t.textSecondary },

  precision: { fontSize: typography.fontSize.xs, color: t.textMuted },

  avertissement: {
    fontSize: typography.fontSize.sm,
    lineHeight: 20,
    fontWeight: typography.fontWeight.semibold,
    color: t.danger,
  },

  action: { marginTop: spacing.sm },
});
