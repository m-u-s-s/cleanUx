import React, { useState, useCallback, useEffect, useRef } from 'react';
import { View, Text, FlatList, Switch, Alert, StyleSheet, TextInput as RNTextInput } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Screen, Button, Badge, Divider } from '@/ui';
import { useMissionDetail, useMissionLifecycle } from '@/missions';
import { useInspection, useToggleChecklistItem } from '@/inspection';
import { useGpsWatcher, useSendPing, useStartTracking } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { formatAdresse, messageDErreur } from '@brio/shared/format';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionField'>;

const PING_INTERVAL_MS = 10000;

export function MissionExecutionScreen({ route }: Props) {
  const styles = stylesFor(useThemeColors());

  const { missionId } = route.params;
  const { data: mission } = useMissionDetail(missionId);
  const { data: inspection } = useInspection(missionId);
  const toggleItem = useToggleChecklistItem();
  const lifecycle = useMissionLifecycle(missionId);

  const startTracking = useStartTracking(missionId);
  const [sessionId, setSessionId] = useState<number | null>(null);
  const sendPing = useSendPing(sessionId);
  const [gpsActive, setGpsActive] = useState(true);
  const [endCode, setEndCode] = useState('');
  const [elapsedSeconds, setElapsedSeconds] = useState(0);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Start tracking session when screen mounts and mission is in_progress
  useEffect(() => {
    if (mission?.status === 'in_progress' && sessionId === null) {
      startTracking.mutate(undefined, {
        onSuccess: (session) => setSessionId(session.id),
      });
    }
  }, [mission?.status]);

  // Elapsed timer
  useEffect(() => {
    timerRef.current = setInterval(() => {
      setElapsedSeconds((s) => s + 1);
    }, 1000);
    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, []);

  // GPS ping every 10s
  useGpsWatcher(
    gpsActive && mission?.status === 'in_progress',
    useCallback(
      (pos) => {
        if (sessionId !== null) {
          sendPing.mutate({
            latitude: pos.latitude,
            longitude: pos.longitude,
            speed: pos.speed ?? undefined,
            heading: pos.heading ?? undefined,
          });
        }
      },
      [sessionId, sendPing],
    ),
  );

  const formatElapsed = (seconds: number): string => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}h ${m.toString().padStart(2, '0')}m`;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
  };

  const handleComplete = () => {
    Alert.alert(
      'Terminer la mission',
      endCode ? `Code de fin: ${endCode}. Confirmer ?` : 'Aucun code de fin saisi. Continuer quand même ?',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Terminer',
          style: 'destructive',
          onPress: () =>
            lifecycle.mutate(
              { action: 'complete', code: endCode.trim() || undefined },
              {
                onError: (e) =>
                  Alert.alert('Erreur', messageDErreur(e, 'Impossible de terminer la mission.')),
              },
            ),
        },
      ],
    );
  };

  if (!mission) {
    return (
      <Screen>
        <Text style={styles.loading}>Chargement...</Text>
      </Screen>
    );
  }

  return (
    <Screen scroll testID="mission-execution-screen">
      {/* Status + Timer */}
      <View style={styles.header}>
        <Text style={styles.title}>{mission.service_name}</Text>
        <Badge label={mission.status} variant="success" />
      </View>

      <View style={styles.timerCard}>
        <Text style={styles.timerLabel}>Temps écoulé</Text>
        <Text style={styles.timerValue}>{formatElapsed(elapsedSeconds)}</Text>
      </View>

      {/* Client info */}
      <View style={styles.infoCard}>
        <Text style={styles.infoTitle}>Client</Text>
        <Text style={styles.infoValue}>{mission.client_name}</Text>
        <Divider />
        <Text style={styles.infoTitle}>Adresse</Text>
        <Text style={styles.infoValue}>
          {formatAdresse(mission.address, mission.city)}
        </Text>
        {mission.client_phone && (
          <>
            <Divider />
            <Text style={styles.infoTitle}>Téléphone</Text>
            <Text style={styles.infoValue}>{mission.client_phone}</Text>
          </>
        )}
      </View>

      {/* Checklist */}
      {inspection && inspection.items.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Checklist</Text>
          <FlatList
            data={inspection.items}
            scrollEnabled={false}
            keyExtractor={(i) => String(i.id)}
            renderItem={({ item }) => (
              <View style={styles.checkItem}>
                <Text style={styles.checkLabel}>{item.label}</Text>
                <Switch
                  value={Boolean(item.completed)}
                  onValueChange={(v) =>
                    toggleItem.mutate({ inspectionId: inspection.id, itemId: item.id, value: v })
                  }
                  trackColor={{ true: colors.success[500] }}
                />
              </View>
            )}
            ItemSeparatorComponent={() => <Divider />}
          />
        </View>
      )}

      {/* GPS toggle */}
      <View style={styles.gpsRow}>
        <Text style={styles.gpsLabel}>Partage GPS actif</Text>
        <Switch
          value={gpsActive}
          onValueChange={setGpsActive}
          trackColor={{ true: colors.brand[500] }}
          accessibilityLabel="Activer ou désactiver le partage GPS"
        />
      </View>

      {/* End code */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Code de fin (QR / manuel)</Text>
        <RNTextInput
          style={styles.codeInput}
          value={endCode}
          onChangeText={setEndCode}
          placeholder="Saisir le code de fin..."
          placeholderTextColor={colors.surface[400]}
          autoCapitalize="characters"
          accessibilityLabel="Code de fin de mission"
        />

      </View>

      {/* Complete button */}
      {mission.status === 'in_progress' && (
        <View style={styles.actions}>
          <Button
            label="Terminer la mission"
            onPress={handleComplete}
            variant="danger"
            fullWidth
            size="lg"
            loading={lifecycle.isPending}
          />
        </View>
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  loading: { color: t.textSecondary, textAlign: 'center', marginTop: spacing.xl },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    flex: 1,
    marginRight: spacing.sm,
  },
  timerCard: {
    backgroundColor: t.tint.brand,
    borderRadius: radius.md,
    padding: spacing.md,
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  timerLabel: { fontSize: typography.fontSize.sm, color: colors.brand[600] },
  timerValue: {
    fontSize: 36,
    fontWeight: typography.fontWeight.bold,
    color: colors.brand[700],
    fontVariant: ['tabular-nums'],
    marginTop: spacing.xs,
  },
  infoCard: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
    marginBottom: spacing.md,
    gap: spacing.xs,
  },
  infoTitle: { fontSize: typography.fontSize.xs, color: t.textMuted },
  infoValue: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  section: { marginBottom: spacing.md },
  sectionTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.sm,
  },
  checkItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
  },
  checkLabel: { fontSize: typography.fontSize.sm, color: t.text, flex: 1 },
  gpsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.md,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: t.border,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
    marginBottom: spacing.md,
  },
  gpsLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  codeInput: {
    borderWidth: 1,
    borderColor: t.border,
    borderRadius: radius.md,
    padding: spacing.md,
    fontSize: typography.fontSize.base,
    color: t.text,
    backgroundColor: t.card,
    letterSpacing: 2,
  },
  actions: { marginTop: spacing.md, marginBottom: spacing.xl },
});
