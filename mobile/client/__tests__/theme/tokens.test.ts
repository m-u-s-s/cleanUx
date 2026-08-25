import { colors, spacing, radius, typography, shadows, animation } from '@/theme';

describe('Design tokens', () => {
  describe('colors', () => {
    it('has brand palette with 500 as primary', () => {
      expect(colors.brand[500]).toBe('#6366f1');
    });
    it('has surface neutral palette', () => {
      expect(colors.surface[50]).toBe('#fafafa');
      expect(colors.surface[950]).toBe('#0a0a0a');
    });
    it('has semantic colors', () => {
      expect(colors.success[500]).toBe('#10b981');
      expect(colors.warning[500]).toBe('#f59e0b');
      expect(colors.danger[500]).toBe('#ef4444');
    });
    it('has accent colors from CSS tokens', () => {
      expect(colors.accent.amber).toBe('#ffb648');
      expect(colors.accent.cyan).toBe('#4fe3d6');
      expect(colors.accent.violet).toBe('#8b7bff');
    });
  });
  describe('spacing', () => {
    it('uses 4px base scale', () => {
      expect(spacing.xs).toBe(4);
      expect(spacing.sm).toBe(8);
      expect(spacing.md).toBe(16);
      expect(spacing.lg).toBe(24);
      expect(spacing.xl).toBe(32);
    });
  });
  describe('radius', () => {
    it('matches CSS --cx-radius-* tokens', () => {
      // Les rayons ont grandi avec le passage au verre : un angle vif trahit une
      // matiere epaisse. Les memes valeurs vivent dans `resources/css/tokens.css`,
      // et `LeThemeEstLeMemeSurLesTroisSurfacesTest` compare les deux fichiers.
      expect(radius.sm).toBe(12);
      expect(radius.md).toBe(18);
      expect(radius.lg).toBe(24);
      expect(radius.xl).toBe(32);
      expect(radius.pill).toBe(999);
    });
  });
  describe('typography', () => {
    it('defines font families', () => {
      expect(typography.fontFamily.body).toBeDefined();
      expect(typography.fontFamily.display).toBeDefined();
    });
    it('defines font sizes', () => {
      expect(typography.fontSize.sm).toBe(14);
      expect(typography.fontSize.base).toBe(16);
      expect(typography.fontSize.lg).toBe(18);
    });
  });
  describe('shadows', () => {
    it('defines RN-compatible shadow objects', () => {
      expect(shadows.soft).toHaveProperty('shadowColor');
      expect(shadows.soft).toHaveProperty('shadowOffset');
      expect(shadows.soft).toHaveProperty('shadowOpacity');
      expect(shadows.soft).toHaveProperty('shadowRadius');
      expect(shadows.soft).toHaveProperty('elevation');
    });
  });
  describe('animation', () => {
    it('defines timing constants matching CSS', () => {
      expect(animation.duration.fast).toBe(180);
      expect(animation.duration.base).toBe(280);
      expect(animation.duration.slow).toBe(420);
    });
    it('defines easing bezier', () => {
      expect(animation.easing.default).toEqual([0.16, 1, 0.3, 1]);
    });
  });
});
