import { beforeEach } from 'vitest';

beforeEach(() => {
  // Reset CSS variables on root before each test
  document.documentElement.removeAttribute('data-theme');
});
