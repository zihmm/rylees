// Used by babel-jest (test environment only). Vite uses esbuild, not Babel.

// Babel (unlike Vite/esbuild) can't parse `import.meta` outside a native ES
// module, so it throws on `import.meta.env` in source files. Rewrite the
// `import.meta` meta-property to `{ env: process.env }` for tests only.
function transformImportMetaEnv({ types: t }) {
  return {
    visitor: {
      MetaProperty(path) {
        path.replaceWith(
          t.objectExpression([
            t.objectProperty(
              t.identifier('env'),
              t.memberExpression(t.identifier('process'), t.identifier('env'))
            ),
          ])
        );
      },
    },
  };
}

module.exports = {
  presets: [['@babel/preset-env', { targets: { node: 'current' } }]],
  plugins: [transformImportMetaEnv],
};
