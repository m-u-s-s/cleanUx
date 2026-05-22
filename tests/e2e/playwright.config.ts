import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  timeout: 30_000,
  retries: 0,
  use: {
    baseURL: process.env.APP_URL || 'http://localhost:8000',
    ...devices['iPhone 13 Pro'],
  },
  projects: [
    { name: 'mobile-webkit', use: { ...devices['iPhone 13 Pro'] } },
    { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
  ],
});
