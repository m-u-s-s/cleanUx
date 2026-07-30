import React, { useState, useCallback } from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Screen, Button, TextInput } from '@/ui';
import { useConfirmPresence } from '@/tracking';
import { colors, spacing, typography, radius } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'PresenceScan'>;

/**
 * Scan du code que le client affiche, pour attester d'une présence sur place.
 *
 * La géo-barrière fait basculer la session à 150 m de la porte : elle atteste d'une proximité,
 * pas d'une présence. Lire le code de l'écran du client exige les deux appareils au même endroit,
 * ce qu'aucune coordonnée GPS ne peut établir seule.
 *
 * La saisie manuelle reste ouverte : une caméra sale, un écran fêlé ou une lumière rasante ne
 * doivent pas bloquer une intervention. Le client peut alors dicter ses six chiffres.
 */
export function PresenceScanScreen({ route }: Props) {
  const { sessionId } = route.params;
  const navigation = useNavigation<any>();
  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(false);
  const [manualCode, setManualCode] = useState('');
  const confirm = useConfirmPresence(sessionId);

  const submit = useCallback(
    (code: string) => {
      confirm.mutate(
        { code },
        {
          onSuccess: (result) => {
            // Le démarrage est un effet de bord : il peut ne pas avoir lieu — mission déjà
            // commencée, prestataire non rattaché — sans que la présence en souffre. Annoncer
            // « mission démarrée » dans ce cas serait un mensonge.
            Alert.alert(
              'Présence confirmée',
              result?.mission_started
                ? "L'intervention a démarré. Le client a été notifié."
                : 'Le client a bien été notifié.',
              [{ text: 'Continuer', onPress: () => navigation.goBack() }],
            );
          },
          onError: (e: any) => {
            // Le message du serveur est celui qui explique la cause — code expiré, déjà brûlé,
            // trop de tentatives. Le remplacer par un texte générique le priverait de sens.
            const detail = e?.response?.data?.errors?.code?.[0] ?? e?.message;
            Alert.alert('Code refusé', detail ?? 'Ce code n’est pas valide.');
            setScanned(false);
          },
        },
      );
    },
    [confirm, navigation],
  );

  /**
   * Le QR porte une charge signalée par son type. On la refuse si elle n'est pas la nôtre,
   * plutôt que d'y pêcher six chiffres au hasard — un QR quelconque traînant sur une table ne
   * doit pas déclencher une tentative.
   */
  const handleScan = useCallback(
    ({ data }: { data: string }) => {
      if (scanned || confirm.isPending) return;
      setScanned(true);

      let code: string | null = null;
      try {
        const parsed = JSON.parse(data);
        if (parsed?.t === 'cleanux.presence' && typeof parsed.c === 'string') {
          code = parsed.c;
        }
      } catch {
        code = null;
      }

      if (!code) {
        Alert.alert('QR non reconnu', "Ce n'est pas le code de présence du client.");
        setScanned(false);

        return;
      }

      submit(code);
    },
    [scanned, confirm.isPending, submit],
  );

  // `useCameraPermissions` rend `null` le temps de lire l'état du système.
  if (!permission) {
    return (
      <Screen testID="presence-scan-loading">
        <View style={styles.centered} />
      </Screen>
    );
  }

  if (!permission.granted) {
    return (
      <Screen testID="presence-scan-denied">
        <View style={styles.centered}>
          <Text style={styles.title}>Accès à la caméra</Text>
          <Text style={styles.hint}>
            La caméra est nécessaire pour lire le code affiché par le client.
          </Text>
          <Button label="Autoriser la caméra" onPress={requestPermission} fullWidth />
        </View>
      </Screen>
    );
  }

  return (
    <View style={styles.container} testID="presence-scan-screen">
      <CameraView
        style={styles.camera}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={scanned ? undefined : handleScan}
      >
        <View style={styles.overlay}>
          <View style={styles.frame} />
          <Text style={styles.instruction}>
            Scannez le code affiché sur le téléphone du client
          </Text>
          {scanned && !confirm.isPending && (
            <Button label="Scanner à nouveau" onPress={() => setScanned(false)} variant="secondary" />
          )}
        </View>
      </CameraView>

      {/* Saisie de repli : une caméra sale, un écran fêlé ou une lumière rasante ne doivent pas
          bloquer une intervention. L'écran du client invite d'ailleurs à dicter ces six
          chiffres — sans ce champ, cette consigne ne mènerait nulle part. */}
      <View style={styles.manual}>
        <TextInput
          label="Ou saisissez les six chiffres"
          value={manualCode}
          onChangeText={setManualCode}
          keyboardType="number-pad"
          maxLength={6}
          placeholder="000000"
          testID="presence-manual-code"
        />
        <Button
          label="Confirmer ma présence"
          onPress={() =>
            manualCode.length === 6
              ? submit(manualCode)
              : Alert.alert('Code incomplet', 'Le code du client compte six chiffres.')
          }
          loading={confirm.isPending}
          fullWidth
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  camera: { flex: 1 },
  manual: { padding: spacing.md, gap: spacing.sm, backgroundColor: '#fff' },
  centered: { flex: 1, justifyContent: 'center', gap: spacing.md, padding: spacing.lg },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
  },
  hint: { fontSize: typography.fontSize.sm, color: colors.surface[600] },
  overlay: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.lg },
  frame: {
    width: 240,
    height: 240,
    borderWidth: 3,
    borderColor: '#fff',
    borderRadius: radius.md,
    backgroundColor: 'transparent',
  },
  instruction: {
    color: '#fff',
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    textAlign: 'center',
    paddingHorizontal: spacing.lg,
  },
});
