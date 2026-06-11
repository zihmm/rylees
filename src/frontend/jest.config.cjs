module.exports = {
  testEnvironment: 'jsdom',
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',
    '^.+\\.[jt]sx?$': 'babel-jest',
  },
  moduleFileExtensions: ['vue', 'js', 'json'],
  // Stub static assets (svg/png/css) imported by components.
  moduleNameMapper: {
    '\\.(css|png|jpg|svg)$': '<rootDir>/tests/__mocks__/fileMock.js',
    '@fontsource/.*': '<rootDir>/tests/__mocks__/fileMock.js',
  },
  testMatch: ['<rootDir>/tests/**/*.test.js'],
  // axios ships ESM; allow it (and dayjs) through the transform.
  transformIgnorePatterns: ['/node_modules/(?!(axios|dayjs)/)'],
};
