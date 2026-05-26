import { device, element, by, expect } from 'detox';

describe('Referral Screen', () => {
  beforeAll(async () => {
    await device.launchApp({ newInstance: true });
    // Assume logged in (would need auth setup in real E2E)
  });

  it('should display referral code', async () => {
    // Navigate to profile -> referral
    await expect(element(by.id('referral-code'))).toBeVisible();
  });

  it('should have share and copy buttons', async () => {
    await expect(element(by.text('Partager'))).toBeVisible();
    await expect(element(by.text('Copier'))).toBeVisible();
  });
});
