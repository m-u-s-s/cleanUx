/** @type {import('eslint').Linter.Config} */
module.exports = {
    env: { browser: true, es2021: true, node: true },
    extends: ['eslint:recommended'],
    parserOptions: { ecmaVersion: 'latest', sourceType: 'module' },
    globals: {
        Livewire: 'readonly',
        Alpine: 'readonly',
        Echo: 'readonly',
        Pusher: 'readonly',
        posthog: 'readonly',
    },
    rules: {
        'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
        'no-console': ['warn', { allow: ['warn', 'error'] }],
        semi: ['error', 'always'],
        'no-undef': 'error',
        'no-var': 'warn',
        'prefer-const': 'warn',
    },
    ignorePatterns: ['vendor/', 'node_modules/', 'public/build/', 'storage/', 'bootstrap/cache/', 'mobile/'],
};
