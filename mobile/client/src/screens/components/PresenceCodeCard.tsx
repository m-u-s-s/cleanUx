import React, { useEffect, useMemo } from 'react';
import { View, Text, StyleSheet, ActivityIndicator } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { Button } from '@/ui';
import { usePresenceCode } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';

/**
 * Preuve de présence du prestataire, à montrer sur place.
 *
 * La géo-barrière atteste d'une proximité, pas d'une présence : un téléphone à 100 m de la porte
 * la franchit, et la session bascule seule. Le client affiche donc un code à usage unique que le
 * prestataire scanne — ce qui exige les deux appareils au même endroit, ce qu'aucune coordonnée
 * GPS ne peut établir seule.
 *
 * Le code est demandé UNE fois à l'ouverture. Chaque appel au serveur en forge un neuf et périme
 * le précédent : rafraîchir périodiquement le remplacerait sous le nez du prestataire en train de
 * le scanner. Le bouton laisse au client la main pour en redemander un si celui-ci a expiré.
 *
 * Les six chiffres restent lisibles sous le QR : une caméra sale, un écran fêlé ou une lumière
 * rasante ne doivent pas bloquer une intervention — le prestataire peut alors les saisir.
 */
export function PresenceCodeCard({ bookingId }: { bookingId: number }) {
  const { mutate, data, isPending, isError } = usePresenceCode(bookingId);

  useEffect(() => {
    mutate();
  }, [bookingId]);

  /**
   * Charge utile du QR. Le libellé et la version sont explicites pour que le scanner du
   * prestataire puisse refuser un QR étranger plutôt que d'en tirer un code au hasard.
   */
  const payload = useMemo(
    () => (data ? JSON.stringify({ t: 'cleanux.presence', v: 1, s: data.session_id, c: data.code }) : null),
    [data],
  );

  return (
    <View style={styles.card} testID="presence-code-card">
      <Text style={styles.title}>Confirmez la présence</Text>
      <Text style={styles.hint}>
        Montrez ce code à votre prestataire. Il le scanne pour attester qu'il est bien chez vous.
      </Text>

      {isPending ? (
        <View style={styles.placeholder} testID="presence-code-loading">
          <ActivityIndicator color={colors.brand[500]} />
        </View>
      ) : isError || !payload || !data ? (
        <View style={styles.placeholder}>
          <Text style={styles.errorText}>Code indisponible pour le moment.</Text>
        </View>
      ) : (
        <>
          <View style={styles.qrFrame} testID="presence-code-qr">
            <QRCode value={payload} size={180} backgroundColor="#ffffff" color={colors.surface[900]} />
          </View>
          <Text style={styles.codeLabel}>ou dictez ce code</Text>
          <Text style={styles.code} testID="presence-code-digits">
            {data.code}
          </Text>
        </>
      )}

      <Button
        label="Générer un nouveau code"
        onPress={() => mutate()}
        variant="secondary"
        fullWidth
        loading={isPending}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.lg,
    alignItems: 'center',
    gap: spacing.sm,
    ...shadows.xs,
  },
  title: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
  },
  hint: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[600],
    textAlign: 'center',
  },
  qrFrame: {
    padding: spacing.md,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.surface[200],
  },
  placeholder: {
    height: 212,
    justifyContent: 'center',
    alignItems: 'center',
  },
  errorText: { fontSize: typography.fontSize.sm, color: colors.surface[500] },
  codeLabel: { fontSize: typography.fontSize.xs, color: colors.surface[500] },
  // Chiffres largement espacés : ils sont lus à voix haute autant qu'ils sont regardés.
  code: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    letterSpacing: 6,
    fontVariant: ['tabular-nums'],
  },
});
