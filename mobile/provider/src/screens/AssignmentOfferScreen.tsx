import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp, NativeStackScreenProps } from '@react-navigation/native-stack';
import Animated, { FadeIn, useSharedValue, withTiming, useAnimatedStyle } from 'react-native-reanimated';
import { Screen, Button, Badge, Divider } from '@/ui';
import { useAcceptMission, useDeclineMission } from '@/missions';
import type { MissionAssignment } from '@/missions';
import { useCurrentPosition, distanceKmTo } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

interface Props {
  assignment: MissionAssignment;
  onDismiss: () => void;
}

const OFFER_TIMEOUT_SECONDS = 15;

export function AssignmentOfferScreen({ assignment, onDismiss }: Props) {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const accept = useAcceptMission();
  const decline = useDeclineMission();
  const [secondsLeft, setSecondsLeft] = useState(OFFER_TIMEOUT_SECONDS);
  const progress = useSharedValue(1);
  const position = useCurrentPosition();
  const distanceKm = distanceKmTo(position, assignment);

  useEffect(() => {
    progress.value = withTiming(0, { duration: OFFER_TIMEOUT_SECONDS * 1000 });
    const interval = setInterval(() => {
      setSecondsLeft((s) => {
        if (s <= 1) {
          clearInterval(interval);
          onDismiss();
          return 0;
        }
        return s - 1;
      });
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  const animatedBar = useAnimatedStyle(() => ({
    width: `${progress.value * 100}%`,
  }));

  const handleAccept = useCallback(() => {
    accept.mutate(assignment.id, {
      onSuccess: () => {
        // `mission_id`, PAS `booking_id` : la route de destination est liée au modèle Mission
        // (GET /provider/missions/{missionId}). Même défaut que celui corrigé sur la carte.
        navigation.navigate('MissionDetail', { missionId: assignment.mission_id });
        onDismiss();
      },
      onError: () => Alert.alert('Erreur', "Impossible d'accepter cette mission."),
    });
  }, [accept, assignment, navigation, onDismiss]);

  const handleDecline = useCallback(() => {
    Alert.alert('Décliner', 'Êtes-vous sûr de vouloir décliner cette mission ?', [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Décliner',
        style: 'destructive',
        onPress: () =>
          decline.mutate(assignment.id, { onSuccess: onDismiss }),
      },
    ]);
  }, [decline, assignment, onDismiss]);

  return (
    <Animated.View entering={FadeIn.duration(300)} style={styles.container}>
      <View style={styles.card}>
        {/* Timer bar */}
        <View style={styles.timerTrack}>
          <Animated.View style={[styles.timerBar, animatedBar]} />
        </View>
        <Text style={styles.timerText}>{secondsLeft}s pour répondre</Text>

        <Text style={styles.heading}>Nouvelle mission disponible</Text>

        {/* Mission details */}
        <View style={styles.details}>
          <Text style={styles.serviceName}>{assignment.service_name}</Text>
          <View style={styles.row}>
            <Text style={styles.label}>Client</Text>
            <Text style={styles.value}>{assignment.client_name}</Text>
          </View>
          <Divider />
          <View style={styles.row}>
            <Text style={styles.label}>Adresse</Text>
            <Text style={styles.value}>
              {assignment.address}, {assignment.city}
            </Text>
          </View>
          <Divider />
          <View style={styles.row}>
            <Text style={styles.label}>Date/Heure</Text>
            <Text style={styles.value}>
              {assignment.scheduled_date} à {assignment.scheduled_time}
            </Text>
          </View>
          {assignment.estimated_duration_minutes != null && (
            <>
              <Divider />
              <View style={styles.row}>
                <Text style={styles.label}>Durée estimée</Text>
                <Text style={styles.value}>{assignment.estimated_duration_minutes} min</Text>
              </View>
            </>
          )}
          {distanceKm != null && (
            <>
              <Divider />
              <View style={styles.row}>
                <Text style={styles.label}>Distance</Text>
                <Badge label={`${distanceKm.toFixed(1)} km`} variant="brand" />
              </View>
            </>
          )}
        </View>

        {/* Actions */}
        <View style={styles.actions}>
          <View style={styles.actionBtn}>
            <Button
              label="Accepter"
              onPress={handleAccept}
              fullWidth
              size="lg"
              loading={accept.isPending}
            />
          </View>
          <View style={styles.actionBtn}>
            <Button
              label="Décliner"
              onPress={handleDecline}
              variant="ghost"
              fullWidth
              size="lg"
            />
          </View>
        </View>
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'flex-end',
    padding: spacing.md,
    paddingBottom: spacing['2xl'],
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: radius.xl,
    padding: spacing.lg,
    ...shadows.lg,
  },
  timerTrack: {
    height: 4,
    backgroundColor: colors.surface[200],
    borderRadius: radius.pill,
    marginBottom: spacing.xs,
    overflow: 'hidden',
  },
  timerBar: {
    height: '100%',
    backgroundColor: colors.warning[500],
    borderRadius: radius.pill,
  },
  timerText: {
    fontSize: typography.fontSize.xs,
    color: colors.warning[600],
    textAlign: 'right',
    marginBottom: spacing.md,
  },
  heading: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.md,
  },
  details: {
    backgroundColor: colors.surface[50],
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  serviceName: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.brand[600],
    marginBottom: spacing.sm,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
  },
  label: { fontSize: typography.fontSize.sm, color: colors.surface[500] },
  value: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[900],
    flex: 1,
    textAlign: 'right',
    marginLeft: spacing.sm,
  },
  actions: { flexDirection: 'row', gap: spacing.sm },
  actionBtn: { flex: 1 },
});
