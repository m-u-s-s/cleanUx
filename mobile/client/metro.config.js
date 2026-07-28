const { getDefaultConfig } = require('expo/metro-config');

// Expo has configured Metro for monorepos automatically since SDK 52, and this
// app is on SDK 56: getDefaultConfig walks up to the workspace root declared in
// mobile/package.json, watches it, and resolves through both
// client/node_modules and mobile/node_modules.
//
// Do not re-add manual watchFolders / resolver.nodeModulesPaths /
// resolver.extraNodeModules here. The previous hand-rolled version pointed at
// '../../node_modules' — the Laravel app's dependencies, two levels up — while
// omitting mobile/node_modules, where expo itself is hoisted.
//
// Cross-package imports ('@/sentry' -> ../shared/src/sentry) are rewritten by
// babel-plugin-module-resolver, see babel.config.js.
const config = getDefaultConfig(__dirname);

module.exports = config;
