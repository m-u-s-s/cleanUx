import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { Button } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

type Props = NativeStackScreenProps<RootStackParamList, 'QRScan'>;

export function QRScanScreen({ route, navigation }: Props) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { bookingId, action } = route.params;
  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(false);
  const [processing, setProcessing] = useState(false);

  const handleBarCodeScanned = async ({ data }: { data: string }) => {
    if (scanned || processing) return;
    setScanned(true);
    setProcessing(true);

    try {
      const endpoint =
        action === 'start'
          ? `/client/bookings/${bookingId}/qr-start`
          : `/client/bookings/${bookingId}/qr-end`;

      await apiClient.post(endpoint, { qr_code: data });

      Alert.alert(
        action === 'start' ? tr('q_r_scan.mission_demarree') : tr('q_r_scan.mission_terminee'),
        action === 'start'
          ? tr('q_r_scan.le_prestataire_est_confirme_sur')
          : tr('q_r_scan.la_mission_est_terminee_le'),
        [{ text: 'OK', onPress: () => navigation.goBack() }],
      );
    } catch (e: any) {
      Alert.alert(tr('q_r_scan.erreur'), e.message ?? 'QR code invalide ou expiré');
      setScanned(false);
    } finally {
      setProcessing(false);
    }
  };

  if (!permission) {
    return (
      <View style={styles.container}>
        <Text style={styles.loadingText}>{tr('q_r_scan.chargement')}</Text>
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.container}>
        <Text style={styles.title}>{tr('q_r_scan.acces_camera_requis')}</Text>
        <Text style={styles.subtitle}>
          Scannez le QR code du prestataire pour{' '}
          {action === 'start' ? 'démarrer' : 'terminer'} la mission
        </Text>
        <Button label={tr('q_r_scan.autoriser_la_camera')} onPress={requestPermission} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <CameraView
        style={styles.camera}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={scanned ? undefined : handleBarCodeScanned}
      />
      <View style={styles.overlay}>
        <View style={styles.scanFrame} />
        <Text style={styles.instruction}>
          {action === 'start'
            ? tr('q_r_scan.scannez_le_qr_du_prestataire')
            : tr('q_r_scan.scannez_le_qr_pour_confirmer')}
        </Text>
        {scanned && !processing && (
          <Button
            label={tr('q_r_scan.scanner_a_nouveau')}
            onPress={() => setScanned(false)}
            variant="secondary"
          />
        )}
      </View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: t.text,
  },
  loadingText: {
    color: t.card,
    fontSize: typography.fontSize.base,
  },
  camera: { flex: 1, width: '100%' },
  overlay: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    alignItems: 'center',
    paddingBottom: spacing['3xl'],
    paddingHorizontal: spacing.md,
  },
  scanFrame: {
    width: 250,
    height: 250,
    borderWidth: 2,
    borderColor: colors.accent.amber,
    borderRadius: radius.lg,
    marginBottom: spacing.lg,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.card,
    marginBottom: spacing.sm,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: t.border,
    textAlign: 'center',
    marginBottom: spacing.lg,
    paddingHorizontal: spacing.lg,
  },
  instruction: {
    fontSize: typography.fontSize.sm,
    color: t.card,
    textAlign: 'center',
    marginBottom: spacing.md,
  },
});
