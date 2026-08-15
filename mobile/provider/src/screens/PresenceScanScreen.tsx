import React, { useState, useCallback, useMemo } from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Screen, Button, TextInput } from '@/ui';
import { useConfirmPresence, useCompleteByQr, readScanPosition } from '@/tracking';
import {spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { messageDErreur } from '@brio/shared/format';

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
 *
 * Elle ouvre en revanche une brèche que le scan n'avait pas : six chiffres se dictent au
 * téléphone. La position relevée au moment de la validation part donc avec le code, et le serveur
 * la confronte au lieu de l'intervention — dicté ou scanné, le code ne vaut que sur place.
 */
export function PresenceScanScreen({ route }: Props) {
  const styles = stylesFor(useThemeColors());

  const params = route.params;
  // Les deux bouts de la visite partagent cet écran : même caméra, même repli manuel, mêmes
  // messages d'erreur. Seul change ce qui est validé — une session de suivi à l'arrivée, une
  // mission à la clôture.
  const isCompletion = params.purpose === 'completion';
  const sessionId = 'sessionId' in params ? params.sessionId : null;
  const missionId = 'missionId' in params ? params.missionId : null;

  const navigation = useNavigation<any>();
  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(false);
  const [manualCode, setManualCode] = useState('');
  // Le relevé de position précède l'envoi et prend jusqu'à huit secondes : sans état dédié, le
  // bouton resterait inerte pendant ce temps, et l'attente passerait pour un plantage.
  const [locating, setLocating] = useState(false);
  const presence = useConfirmPresence(sessionId);
  const completion = useCompleteByQr(missionId);
  const confirm = isCompletion ? completion : presence;
  const busy = confirm.isPending || locating;

  const handlers = useMemo(
    () => ({
      onSuccess: (result: any) => {
        // Le démarrage est un effet de bord : il peut ne pas avoir lieu — mission déjà
        // commencée, prestataire non rattaché — sans que la présence en souffre. Annoncer
        // « mission démarrée » dans ce cas serait un mensonge.
        const [title, message] = isCompletion
          ? ['Mission clôturée', 'Le client a validé la fin de la prestation.']
          : [
              'Présence confirmée',
              result?.mission_started
                ? "L'intervention a démarré. Le client a été notifié."
                : 'Le client a bien été notifié.',
            ];

        Alert.alert(title, message, [
          { text: 'Continuer', onPress: () => navigation.goBack() },
        ]);
      },
      onError: (e: any) => {
        // Le message du serveur est celui qui explique la cause — code expiré, déjà brûlé, trop
        // de tentatives, ou trop loin des lieux. Le remplacer par un texte générique le priverait
        // de sens. Un refus de POSITION n'a rien à voir avec un mauvais code : les confondre sous
        // « Code refusé » enverrait le prestataire redemander un code qui n'a aucun problème.
        const errors = e?.response?.data?.errors ?? {};
        const position = errors.position?.[0];

        // `messageDErreur` ne rend JAMAIS `e.message` : c'est le texte interne de la bibliothèque
        // HTTP, et « Request failed with status code 422 » s'affichait tel quel dans cette alerte.
        Alert.alert(
          position ? 'Position refusée' : 'Code refusé',
          position ?? errors.code?.[0] ?? messageDErreur(e, 'Ce code n’est pas valide.'),
        );
        setScanned(false);
      },
    }),
    [isCompletion, navigation],
  );

  const submit = useCallback(
    async (code: string) => {
      // Relevé pris ICI, à l'instant de la validation, et non repris du suivi en cours : celui-ci
      // se fige sur sa dernière valeur dès qu'on cesse d'émettre, ce qui suffirait à valider
      // depuis n'importe où après avoir quitté les lieux.
      //
      // Les deux bouts de la visite le font. La clôture y a même plus d'intérêt que l'arrivée :
      // c'est elle qui encaisse.
      setLocating(true);
      const position = await readScanPosition();
      setLocating(false);

      if (isCompletion) {
        completion.mutate({ code, position }, handlers);

        return;
      }

      presence.mutate({ code, position }, handlers);
    },
    [isCompletion, completion, presence, handlers],
  );

  /**
   * Le QR porte une charge signalée par son type. On la refuse si elle n'est pas la nôtre,
   * plutôt que d'y pêcher six chiffres au hasard — un QR quelconque traînant sur une table ne
   * doit pas déclencher une tentative.
   */
  const handleScan = useCallback(
    ({ data }: { data: string }) => {
      if (scanned || busy) return;
      setScanned(true);

      // Chaque bout de la visite a son étiquette. Un code de présence envoyé au point d'entrée
      // de clôture serait refusé par le serveur, mais consommerait un essai au passage — et
      // laisserait le prestataire devant un « code invalide » incompréhensible.
      const expected = isCompletion ? 'brio.completion' : 'brio.presence';

      let code: string | null = null;
      try {
        const parsed = JSON.parse(data);
        if (parsed?.t === expected && typeof parsed.c === 'string') {
          code = parsed.c;
        }
      } catch {
        code = null;
      }

      if (!code) {
        Alert.alert(
          'QR non reconnu',
          isCompletion
            ? "Ce n'est pas le code de fin du client."
            : "Ce n'est pas le code de présence du client.",
        );
        setScanned(false);

        return;
      }

      submit(code);
    },
    [scanned, busy, submit, isCompletion],
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
      {/*
        LA VISÉE EST POSÉE À CÔTÉ DE LA CAMÉRA, PLUS DEDANS.

        `CameraView` n'accepte plus d'enfants depuis la nouvelle version d'`expo-camera` : elle
        émet « The <CameraView> component does not support children » — un bandeau d'avertissement
        qui s'affichait PAR-DESSUS l'interface et recouvrait le champ de saisie de repli, celui
        qu'on utilise justement quand la caméra ne suffit pas.

        La caméra remplit donc son conteneur, et la visée se superpose en position absolue.
        `pointerEvents="box-none"` laisse passer les gestes vers ce qui est en dessous : sans lui,
        le cadre avalerait les touches destinées à la caméra.
      */}
      <View style={styles.camera}>
        <CameraView
          style={StyleSheet.absoluteFill}
          facing="back"
          barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
          onBarcodeScanned={scanned ? undefined : handleScan}
        />
        <View style={[StyleSheet.absoluteFill, styles.overlay]} pointerEvents="box-none">
          <View style={styles.frame} />
          <Text style={styles.instruction}>
            {isCompletion
              ? 'Scannez le code de fin affiché par le client'
              : 'Scannez le code affiché sur le téléphone du client'}
          </Text>
          {locating && (
            <Text style={styles.instruction} testID="presence-locating">
              Relevé de votre position…
            </Text>
          )}
          {scanned && !busy && (
            <Button label="Scanner à nouveau" onPress={() => setScanned(false)} variant="secondary" />
          )}
        </View>
      </View>

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
          label={isCompletion ? 'Clôturer la mission' : 'Confirmer ma présence'}
          onPress={() =>
            manualCode.length === 6
              ? submit(manualCode)
              : Alert.alert('Code incomplet', 'Le code du client compte six chiffres.')
          }
          loading={busy}
          fullWidth
        />
      </View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  camera: { flex: 1 },
  manual: { padding: spacing.md, gap: spacing.sm, backgroundColor: t.card },
  centered: { flex: 1, justifyContent: 'center', gap: spacing.md, padding: spacing.lg },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  hint: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  overlay: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.lg },
  frame: {
    width: 240,
    height: 240,
    borderWidth: 3,
    borderColor: t.card,
    borderRadius: radius.md,
    backgroundColor: 'transparent',
  },
  instruction: {
    color: t.card,
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    textAlign: 'center',
    paddingHorizontal: spacing.lg,
  },
});
