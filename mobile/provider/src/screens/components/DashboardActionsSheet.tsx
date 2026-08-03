import React, { forwardRef, useImperativeHandle, useRef, useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { useNavigation } from '@react-navigation/native';
import { BottomSheet, KPICard, Skeleton, Button, Divider } from '@/ui';
import { PresenceToggle } from '@/screens/components/PresenceToggle';
import { useMissionInbox } from '@/missions';
import { useWalletBalance } from '@/earnings';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

type QuickAction = { label: string; screen: string; params?: object };

const QUICK_ACTIONS: QuickAction[] = [
  // En tête : une course immédiate se joue en secondes, et c'est la seule action de cette
  // feuille qui périme si on la lit trop tard.
  { label: 'Courses immédiates', screen: 'AsapOffers' },
  { label: 'Disponibilités', screen: 'Availability' },
  { label: 'Badges', screen: 'Badges' },
  // L'onglet Revenus vit dans MainTabs : sans le param imbriqué, le tap ne fait rien.
  { label: 'Revenus', screen: 'MainTabs', params: { screen: 'Earnings' } },
  { label: 'Messagerie', screen: 'ProviderChatList' },
];

export const DashboardActionsSheet = forwardRef<GorhomBottomSheet>((_props, ref) => {
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<any>();

  // Le sheet est monté en permanence (Task 10 le pilote par ref pour pouvoir l'ouvrir depuis un
  // bouton "Actions" sur la carte) : `@/ui`'s BottomSheet ne démonte jamais ses enfants, gorhom
  // se contente de les repositionner hors écran à index={-1}. Sans cet état local, le polling
  // 15s de useMissionInbox et la requête de useWalletBalance tourneraient en continu pour un
  // contenu que personne ne regarde tant que le sheet n'est pas ouvert.
  const [isOpen, setIsOpen] = useState(false);
  // Ref interne vers l'instance gorhom réelle : la ref exposée au parent (ci-dessous) n'est plus
  // un pass-through direct, elle passe par useImperativeHandle pour intercepter expand()/close().
  const innerRef = useRef<GorhomBottomSheet>(null);

  const { data: assignments, isLoading: loadingMissions } = useMissionInbox(isOpen);
  const { data: wallet, isLoading: loadingWallet } = useWalletBalance(isOpen);

  const expandSheet: GorhomBottomSheet['expand'] = animationConfigs => {
    setIsOpen(true);
    innerRef.current?.expand(animationConfigs);
  };

  const closeSheet: GorhomBottomSheet['close'] = animationConfigs => {
    setIsOpen(false);
    innerRef.current?.close(animationConfigs);
  };

  // Contrat exposé à l'appelant (Task 10) : inchangé — expand()/close(). En interne, ces deux
  // méthodes synchronisent désormais `isOpen`, qui gate les requêtes ci-dessus. Les quatre autres
  // méthodes de BottomSheetMethods sont simplement relayées vers l'instance gorhom réelle, sans
  // toucher à `isOpen` (elles ne ferment pas le sheet).
  useImperativeHandle(
    ref,
    () => ({
      snapToIndex: (index, animationConfigs) => innerRef.current?.snapToIndex(index, animationConfigs),
      snapToPosition: (position, animationConfigs) => innerRef.current?.snapToPosition(position, animationConfigs),
      expand: expandSheet,
      collapse: animationConfigs => innerRef.current?.collapse(animationConfigs),
      close: closeSheet,
      forceClose: animationConfigs => {
        setIsOpen(false);
        innerRef.current?.forceClose(animationConfigs);
      },
    }),
    [],
  );

  // Ferme le sheet avant de naviguer : sinon il reste ouvert au-dessus de l'écran de destination,
  // ou encore déplié au retour sur le dashboard.
  const closeThenNavigate = (screen: string, params?: object) => {
    closeSheet();
    if (params) navigation.navigate(screen, params);
    else navigation.navigate(screen);
  };

  const go = (action: QuickAction) => closeThenNavigate(action.screen, action.params);

  return (
    <BottomSheet
      ref={innerRef}
      snapPoints={['60%', '90%']}
      // Un pan-down-to-close (enablePanDownToClose, déjà actif dans @/ui's BottomSheet) ne passe
      // jamais par notre `closeSheet` : c'est gorhom qui ferme tout seul et appelle onClose. Sans
      // ce câblage, `isOpen` resterait bloqué à `true` et le polling continuerait après un simple
      // geste de fermeture — le chemin que les utilisateurs emprunteront le plus souvent.
      onClose={() => setIsOpen(false)}
    >
      <Text style={styles.sectionTitle} accessibilityRole="header">Statut</Text>
      <PresenceToggle />

      <Divider />

      <View style={styles.kpiRow}>
        {loadingMissions || loadingWallet ? (
          <>
            <Skeleton width="48%" height={80} />
            <Skeleton width="48%" height={80} />
          </>
        ) : (
          <>
            <KPICard
              title="Missions en attente"
              value={assignments?.length ?? 0}
              tone={(assignments?.length ?? 0) > 0 ? 'warning' : 'neutral'}
            />
            <KPICard
              title="Solde disponible"
              value={wallet && wallet.available != null ? `${wallet.available.toFixed(0)} ${wallet.currency ?? ''}`.trim() : '—'}
              tone="success"
            />
          </>
        )}
      </View>

      <Text style={styles.sectionTitle} accessibilityRole="header">Accès rapide</Text>
      <View style={styles.quickActions}>
        {QUICK_ACTIONS.map(action => (
          <TouchableOpacity key={action.label} style={styles.quickCard} onPress={() => go(action)}>
            <Text style={styles.quickLabel}>{action.label}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <Button
        label="Voir toutes les missions"
        onPress={() => closeThenNavigate('MainTabs', { screen: 'Missions' })}
        variant="secondary"
        fullWidth
      />
    </BottomSheet>
  );
});

DashboardActionsSheet.displayName = 'DashboardActionsSheet';

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.sm,
  },
  kpiRow: { flexDirection: 'row', gap: spacing.sm, marginVertical: spacing.md },
  quickActions: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  quickCard: { width: '48%', backgroundColor: t.card, borderRadius: radius.md, padding: spacing.md, ...shadows.xs, alignItems: 'center' },
  quickLabel: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.medium, color: colors.brand[600] },
});
