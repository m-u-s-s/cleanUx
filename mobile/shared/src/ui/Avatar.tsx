import React from 'react';
import { View, Text, Image, StyleSheet } from 'react-native';
import { colors, typography } from '@/theme';

interface AvatarProps {
  name: string;
  imageUri?: string;
  size?: number;
  accessibilityLabel?: string;
}

function getInitials(name: string): string {
  return name
    .split(' ')
    .slice(0, 2)
    .map(w => w[0]?.toUpperCase() ?? '')
    .join('');
}

export function Avatar({ name, imageUri, size = 40, accessibilityLabel }: AvatarProps) {
  const circleStyle = { width: size, height: size, borderRadius: size / 2 };
  const a11yLabel = accessibilityLabel ?? name;

  if (imageUri) {
    return (
      <Image
        source={{ uri: imageUri }}
        style={circleStyle}
        accessibilityLabel={a11yLabel}
      />
    );
  }

  return (
    <View
      style={[circleStyle, styles.fallback]}
      accessibilityLabel={a11yLabel}
    >
      <Text style={[styles.initials, { fontSize: size * 0.4 }]}>
        {getInitials(name)}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fallback: {
    backgroundColor: colors.brand[100],
    alignItems: 'center',
    justifyContent: 'center',
  },
  initials: {
    color: colors.brand[700],
    fontWeight: typography.fontWeight.semibold,
  },
});
