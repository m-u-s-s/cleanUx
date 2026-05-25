import React from 'react';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '@/theme';

interface IconProps {
  name: keyof typeof Ionicons.glyphMap;
  size?: number;
  color?: string;
  accessibilityLabel?: string;
}

export function Icon({ name, size = 24, color = colors.surface[600], accessibilityLabel }: IconProps) {
  return (
    <Ionicons
      name={name}
      size={size}
      color={color}
      accessibilityLabel={accessibilityLabel ?? name}
    />
  );
}
