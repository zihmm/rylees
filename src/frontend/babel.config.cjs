// Used by babel-jest (test environment only). Vite uses esbuild, not Babel.
module.exports = {
  presets: [['@babel/preset-env', { targets: { node: 'current' } }]],
};
