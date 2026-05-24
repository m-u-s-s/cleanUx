import React from 'react';
import { BottomSheet } from '@/ui/BottomSheet';
import { fadeUpConfig, fadeInConfig, shimmerConfig } from '@/ui/animations';
import * as UI from '@/ui/index';

describe('UI layout', () => {
  it('BottomSheet exports without crash', () => {
    expect(BottomSheet).toBeDefined();
    expect(typeof BottomSheet).toBe('object'); // forwardRef returns an object
  });

  it('animation configs are defined', () => {
    expect(fadeUpConfig).toBeDefined();
    expect(fadeUpConfig.duration).toBeGreaterThan(0);

    expect(fadeInConfig).toBeDefined();
    expect(fadeInConfig.duration).toBeGreaterThan(0);

    expect(shimmerConfig).toBeDefined();
    expect(shimmerConfig.duration).toBe(1000);
  });

  it('barrel export exposes all 13 components + 3 animation configs', () => {
    expect(UI.Button).toBeDefined();
    expect(UI.Badge).toBeDefined();
    expect(UI.Icon).toBeDefined();
    expect(UI.Avatar).toBeDefined();
    expect(UI.Tag).toBeDefined();
    expect(UI.Divider).toBeDefined();
    expect(UI.TextInput).toBeDefined();
    expect(UI.StatCard).toBeDefined();
    expect(UI.KPICard).toBeDefined();
    expect(UI.PulseDot).toBeDefined();
    expect(UI.Skeleton).toBeDefined();
    expect(UI.Screen).toBeDefined();
    expect(UI.BottomSheet).toBeDefined();
    expect(UI.fadeUpConfig).toBeDefined();
    expect(UI.fadeInConfig).toBeDefined();
    expect(UI.shimmerConfig).toBeDefined();
  });
});
