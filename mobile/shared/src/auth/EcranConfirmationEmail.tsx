import React, { useCallback, useState } from 'react';
import { View, Text, ScrollView, SafeAreaView } from 'react-native';
import { AnimatedHalo, FormError, Stagger, Wordmark, authStyles } from '../ui/authShell';
import { Button } from '../ui/Button';
import { colors, spacing, typography } from '../theme';
import { useAuth } from './useAuth';
import { adresseAConfirmer, useRelireLeCompte, useRenvoyerLEmailDeConfirmation } from './emailVerification';
import { useTraduction } from '@/i18n';

/**
 * LE MUR DE CONFIRMATION D'ADRESSE.
 *
 * Le serveur exige une adresse confirmée sur 530 de ses 537 routes authentifiées. Sans cet écran,
 * un compte non confirmé collectionnerait un refus par écran sans jamais rien pouvoir y faire.
 *
 * Il se place AVANT tout aiguillage d'espace : contrairement au dossier d'inscription ou au
 * contrôle facial, qui ne concernent que le prestataire de terrain, celui-ci barre la route à
 * tous les rôles. Plus bas, il enverrait un administrateur dans une console dont chaque requête
 * répond 403.
 *
 * TROIS GESTES, PAS UN DE PLUS : constater, redemander, partir. Ce sont exactement les routes que
 * le serveur laisse ouvertes.
 */
export function EcranConfirmationEmail() {
  const { t: tr } = useTraduction();
  const { user, setUser, logout } = useAuth();
  const renvoi = useRenvoyerLEmailDeConfirmation();
  const relecture = useRelireLeCompte();

  const [annonce, setAnnonce] = useState<string | null>(null);
  const [toujoursEnAttente, setToujoursEnAttente] = useState(false);

  const relire = useCallback(() => {
    setAnnonce(null);
    setToujoursEnAttente(false);

    relecture.mutate(undefined, {
      onSuccess: (compte) => {
        setUser(compte);

        // Le mur se lève tout seul : c'est `adresseAConfirmer` qui décide, sur le compte relu.
        if (adresseAConfirmer(compte)) {
          setToujoursEnAttente(true);
        }
      },
    });
  }, [relecture, setUser]);

  const renvoyer = useCallback(() => {
    setToujoursEnAttente(false);

    renvoi.mutate(undefined, {
      onSuccess: (resultat) => {
        setAnnonce(resultat.message);

        // Confirmée entre-temps, depuis un autre appareil : inutile de faire redemander.
        if (resultat.dejaConfirmee) {
          relire();
        }
      },
    });
  }, [renvoi, relire]);

  const erreur = renvoi.error ?? relecture.error;

  return (
    <SafeAreaView style={authStyles.container} testID="ecran-confirmation-email">
      <AnimatedHalo />

      <ScrollView contentContainerStyle={authStyles.scroll} keyboardShouldPersistTaps="handled">
        <View style={authStyles.header}>
          <Wordmark />
          <Text style={authStyles.subtitle}>{tr('ecran_confirmation_email.plus_qu_une_etape')}</Text>
        </View>

        <Stagger index={0}>
          <View style={authStyles.card}>
            <Text style={styles.titre}>{tr('ecran_confirmation_email.confirmez_votre_adresse_e_mail')}</Text>

            <Text style={styles.corps}>
              {tr('ecran_confirmation_email.nous_avons_envoye_un_lien')} <Text style={styles.adresse}>{user?.email ?? 'votre adresse'}</Text>.
              Ouvrez-le pour activer votre compte, puis revenez ici.
            </Text>

            {annonce ? (
              <Text style={styles.annonce} accessibilityLiveRegion="polite">
                {annonce}
              </Text>
            ) : null}

            {toujoursEnAttente ? (
              <Text style={styles.attente} accessibilityLiveRegion="polite">
                {tr('ecran_confirmation_email.le_lien_n_a_pas')}
              </Text>
            ) : null}

            {erreur ? (
              <FormError
                message={erreur.message}
                onRetry={() => {
                  renvoi.reset();
                  relecture.reset();
                }}
                testID="erreur-confirmation-email"
              />
            ) : null}

            <View style={styles.actions}>
              <Button
                label="J'ai confirmé"
                onPress={relire}
                loading={relecture.isPending}
                disabled={renvoi.isPending}
                fullWidth
                testID="bouton-relire-le-compte"
              />

              <Button
                label="Renvoyer l'e-mail"
                variant="outline"
                onPress={renvoyer}
                loading={renvoi.isPending}
                disabled={relecture.isPending}
                fullWidth
                testID="bouton-renvoyer-email"
              />
            </View>
          </View>
        </Stagger>

        <Stagger index={1}>
          <View style={authStyles.footer}>
            <Button
              label="Se déconnecter"
              variant="link"
              onPress={() => void logout()}
              fullWidth
              testID="bouton-se-deconnecter"
            />
          </View>
        </Stagger>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = {
  titre: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.sm,
  },
  corps: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[700],
    lineHeight: 21,
  },
  adresse: {
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  annonce: {
    marginTop: spacing.md,
    fontSize: typography.fontSize.sm,
    color: colors.success[700],
  },
  attente: {
    marginTop: spacing.md,
    fontSize: typography.fontSize.sm,
    color: colors.warning[700],
  },
  actions: {
    marginTop: spacing.lg,
    gap: spacing.sm,
  },
} as const;
