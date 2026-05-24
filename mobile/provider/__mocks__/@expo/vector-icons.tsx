/**
 * Mock for @expo/vector-icons — used in Jest (no native font loading in tests).
 * Renders a plain <Text> with the icon name so assertions can still find it.
 */
import React from 'react';
import { Text } from 'react-native';

function createMockIconSet(displayName: string) {
  const MockIcon = ({ name, size, color, ...rest }: { name?: string; size?: number; color?: string; [key: string]: unknown }) =>
    React.createElement(Text, { ...rest, testID: `icon-${name ?? 'unknown'}` }, name ?? '');
  MockIcon.displayName = displayName;
  MockIcon.glyphMap = new Proxy({} as Record<string, number>, { get: () => 0 });
  return MockIcon;
}

export const Ionicons = createMockIconSet('Ionicons');
export const MaterialIcons = createMockIconSet('MaterialIcons');
export const FontAwesome = createMockIconSet('FontAwesome');
export const AntDesign = createMockIconSet('AntDesign');
export const Feather = createMockIconSet('Feather');
export const MaterialCommunityIcons = createMockIconSet('MaterialCommunityIcons');

export default { Ionicons, MaterialIcons, FontAwesome, AntDesign, Feather, MaterialCommunityIcons };
