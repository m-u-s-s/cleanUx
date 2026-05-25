import React, { useRef, useEffect, useMemo } from 'react';
import { View, Text, StyleSheet, Dimensions } from 'react-native';
import MapView, { Marker, Polyline, PROVIDER_DEFAULT } from 'react-native-maps';
import { Screen, Badge, Skeleton } from '@/ui';
import { useTrackingSession, useTrackingTrail, useLiveTracking } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionTracking'>;

export function MissionTrackingScreen({ route }: Props) {
  const { bookingId } = route.params;
  const { data: session, isLoading } = useTrackingSession(bookingId);
  const { data: trail } = useTrackingTrail(bookingId);
  const { position: livePos, eta: liveEta } = useLiveTracking(bookingId);
  const mapRef = useRef<MapView>(null);

  const currentPos = livePos ?? (trail && trail.length > 0 ? trail[trail.length - 1] : null);
  const etaMinutes = liveEta?.eta_minutes ?? session?.eta_minutes;
  const distanceKm = liveEta?.distance_km ?? session?.distance_km;

  // Initial region only — updates happen via animateToRegion
  const initialRegion = useMemo(() => {
    if (!currentPos) {
      return { latitude: 48.8566, longitude: 2.3522, latitudeDelta: 0.05, longitudeDelta: 0.05 };
    }
    return {
      latitude: currentPos.latitude,
      longitude: currentPos.longitude,
      latitudeDelta: 0.01,
      longitudeDelta: 0.01,
    };
  }, []); // intentionally empty — only for initial render

  // Smooth camera follow instead of prop re-render
  useEffect(() => {
    if (currentPos && mapRef.current) {
      mapRef.current.animateToRegion(
        {
          latitude: currentPos.latitude,
          longitude: currentPos.longitude,
          latitudeDelta: 0.01,
          longitudeDelta: 0.01,
        },
        500,
      );
    }
  }, [currentPos?.latitude, currentPos?.longitude]);

  if (isLoading) {
    return (
      <Screen>
        <Skeleton width="100%" height={300} />
        <Skeleton width="100%" height={80} />
      </Screen>
    );
  }

  return (
    <View style={styles.container}>
      <MapView
        ref={mapRef}
        style={styles.map}
        provider={PROVIDER_DEFAULT}
        initialRegion={initialRegion}
        showsUserLocation
      >
        {currentPos && (
          <Marker
            coordinate={{ latitude: currentPos.latitude, longitude: currentPos.longitude }}
            title="Prestataire"
            pinColor={colors.brand[500]}
          />
        )}
        {trail && trail.length > 1 && (
          <Polyline
            coordinates={trail.map(p => ({ latitude: p.latitude, longitude: p.longitude }))}
            strokeWidth={3}
            strokeColor={colors.brand[400]}
          />
        )}
      </MapView>

      <View style={styles.infoCard}>
        <View style={styles.infoRow}>
          <View>
            <Text style={styles.etaLabel}>ETA</Text>
            <Text style={styles.etaValue}>
              {etaMinutes != null ? `${Math.round(etaMinutes)} min` : '—'}
            </Text>
          </View>
          <View>
            <Text style={styles.etaLabel}>Distance</Text>
            <Text style={styles.etaValue}>
              {distanceKm != null ? `${distanceKm.toFixed(1)} km` : '—'}
            </Text>
          </View>
          {session?.status && (
            <Badge
              label={session.status}
              variant={session.status === 'in_mission' ? 'success' : 'brand'}
            />
          )}
        </View>
      </View>
    </View>
  );
}

const { height } = Dimensions.get('window');

const styles = StyleSheet.create({
  container: { flex: 1 },
  map: { flex: 1, minHeight: height * 0.6 },
  infoCard: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(255, 255, 255, 0.92)',
    borderTopLeftRadius: radius.xl,
    borderTopRightRadius: radius.xl,
    padding: spacing.lg,
    paddingBottom: spacing.xl + 20,
    ...shadows.lg,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  etaLabel: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
  },
  etaValue: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: 2,
  },
});
