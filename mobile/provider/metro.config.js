const { getDefaultConfig } = require('expo/metro-config');
const path = require('path');

const config = getDefaultConfig(__dirname);

// Add shared package to Metro watch folders
const sharedPath = path.resolve(__dirname, '../shared');
config.watchFolders = [sharedPath];

// Allow resolving from shared node_modules and workspace root
config.resolver.nodeModulesPaths = [
  path.resolve(__dirname, 'node_modules'),
  path.resolve(__dirname, '../shared/node_modules'),
  path.resolve(__dirname, '../../node_modules'),
];

// Map @brio/shared to the shared package source
config.resolver.extraNodeModules = {
  '@brio/shared': sharedPath,
};

module.exports = config;
