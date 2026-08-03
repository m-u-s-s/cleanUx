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

type Props = NativeStackScreenProps<RootStackParamList, 'QRScan'>;

export function QRScanScreen({ route, navigation }: Props) {
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
        action === 'start' ? 'Mission démarrée' : 'Mission terminée',
        action === 'start'
          ? 'Le prestataire est confirmé sur site. La mission commence !'
          : 'La mission est terminée. Le paiement sera capturé.',
        [{ text: 'OK', onPress: () => navigation.goBack() }],
      );
    } catch (e: any) {
      Alert.alert('Erreur', e.message ?? 'QR code invalide ou expiré');
      setScanned(false);
    } finally {
      setProcessing(false);
    }
  };

  if (!permission) {
    return (
      <View style={styles.container}>
        <Text style={styles.loadingText}>Chargement...</Text>
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.container}>
        <Text style={styles.title}>Accès caméra requis</Text>
        <Text style={styles.subtitle}>
          Scannez le QR code du prestataire pour{' '}
          {action === 'start' ? 'démarrer' : 'terminer'} la mission
        </Text>
        <Button label="Autoriser la caméra" onPress={requestPermission} />
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
            ? 'Scannez le QR du prestataire pour démarrer'
            : 'Scannez le QR pour confirmer la fin'}
        </Text>
        {scanned && !processing && (
          <Button
            label="Scanner à nouveau"
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
