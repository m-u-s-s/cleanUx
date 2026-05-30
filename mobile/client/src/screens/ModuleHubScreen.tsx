import React, { useEffect, useState } from 'react';
import { ScrollView, Pressable, Text, View } from 'react-native';
import { fetchParityMap, type ParityModule } from '@/parity';
import { Icon, Screen } from '@/ui';
import { colors, spacing } from '@/theme';

/**
 * Maps a `native` parity module key to its in-app route. As modules are
 * migrated, add entries here; a native flag with no mapping safely falls
 * back to embedded web.
 */
const NATIVE_ROUTES: Record<string, { screen: string; params?: object }> = {
  booking: { screen: 'BookingWizard' },
  tracking: { screen: 'MissionTracking' },
  chat: { screen: 'ChatList' },
};

export function ModuleHubScreen({ navigation }: { navigation: any }) {
  const [modules, setModules] = useState<ParityModule[]>([]);

  useEffect(() => {
    fetchParityMap().then(setModules).catch(() => setModules([]));
  }, []);

  const open = (m: ParityModule) => {
    const native = m.mobile === 'native' ? NATIVE_ROUTES[m.key] : undefined;
    if (native) {
      navigation.navigate(native.screen, native.params);
      return;
    }
    navigation.navigate('EmbeddedModule', { path: m.path, title: m.title });
  };

  return (
    <Screen>
      <ScrollView contentContainerStyle={{ padding: spacing.md }}>
        {modules.map((m) => (
          <Pressable
            key={m.key}
            onPress={() => open(m)}
            style={{ flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.md }}
          >
            <Icon name={m.icon as any} size={22} color={colors.brand[500]} />
            <View style={{ marginLeft: spacing.md }}>
              <Text style={{ fontSize: 16, color: colors.surface[900] }}>{m.title}</Text>
            </View>
          </Pressable>
        ))}
      </ScrollView>
    </Screen>
  );
}
