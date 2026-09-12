const assert = require('node:assert/strict');
const path = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');
const { transformFileSync } = require('@babel/core');

const variables = {
  EXPO_PUBLIC_API_URL: 'https://api.example.test/api',
  EXPO_PUBLIC_WEB_URL: 'https://app.example.test',
  EXPO_PUBLIC_STRIPE_PUBLISHABLE_KEY: 'pk_test_audit',
  EXPO_PUBLIC_SENTRY_DSN: 'https://public@example.test/1',
  EXPO_PUBLIC_TURNSTILE_SITE_KEY: 'public-test-site-key',
};

function compileConfig(values) {
  const previous = Object.fromEntries(Object.keys(variables).map(key => [key, process.env[key]]));
  try {
    for (const key of Object.keys(variables)) {
      if (values[key] === undefined) delete process.env[key];
      else process.env[key] = values[key];
    }
    return transformFileSync(path.resolve(__dirname, '../shared/src/config/env.ts'), {
      configFile: false,
      babelrc: false,
      presets: [require.resolve('babel-preset-expo')],
      caller: { name: 'metro', bundler: 'metro', platform: 'android', isDev: false },
    }).code;
  } finally {
    for (const [key, value] of Object.entries(previous)) {
      if (value === undefined) delete process.env[key];
      else process.env[key] = value;
    }
  }
}

test('production config embeds public variables without a runtime environment', () => {
  const code = compileConfig(variables);
  const context = { exports: {}, process: { env: {} } };
  vm.runInNewContext(code, context);
  assert.deepEqual({ ...context.exports.env }, {
    apiUrl: variables.EXPO_PUBLIC_API_URL,
    webUrl: variables.EXPO_PUBLIC_WEB_URL,
    stripePublishableKey: variables.EXPO_PUBLIC_STRIPE_PUBLISHABLE_KEY,
    sentryDsn: variables.EXPO_PUBLIC_SENTRY_DSN,
    turnstileSiteKey: variables.EXPO_PUBLIC_TURNSTILE_SITE_KEY,
  });
});

test('missing public variables preserve the existing local defaults', () => {
  const code = compileConfig({});
  const context = { exports: {}, process: { env: {} } };
  vm.runInNewContext(code, context);
  assert.deepEqual({ ...context.exports.env }, {
    apiUrl: 'http://localhost:8000/api',
    webUrl: 'http://localhost:8000',
    stripePublishableKey: '', sentryDsn: '', turnstileSiteKey: '',
  });
});
