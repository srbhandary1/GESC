import js from '@eslint/js'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import globals from 'globals'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  {
    ignores: [
      '**/coverage',
      '**/dist',
      '**/dist-demo',
      '**/node_modules',
      '**/playwright-report',
      '**/test-results',
      'apps/api/vendor',
      // Native demo wrapper: Gradle project plus the copied built bundle.
      'apps/web/android',
      // Native production wrapper: Gradle output plus the copied built bundle.
      'apps/web/android-app',
    ],
  },
  {
    files: ['**/*.{js,mjs,ts,tsx}'],
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    languageOptions: {
      ecmaVersion: 2023,
    },
  },
  {
    files: [
      'apps/web/**/*.{js,mjs,ts,tsx}',
      'apps/marketing/**/*.{js,mjs,ts}',
      'packages/design-system/**/*.{js,mjs,ts,tsx}',
    ],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
  },
  {
    files: [
      'packages/localization/**/*.{js,mjs,ts}',
      'scripts/**/*.{js,mjs,ts}',
    ],
    languageOptions: {
      globals: globals.node,
    },
  },
  {
    files: [
      'apps/web/src/**/*.{ts,tsx}',
      'packages/design-system/src/**/*.{ts,tsx}',
    ],
    plugins: {
      'react-hooks': reactHooks,
    },
    rules: {
      ...reactHooks.configs.flat.recommended.rules,
    },
  },
  {
    files: ['apps/web/src/**/*.{ts,tsx}'],
    plugins: {
      'react-refresh': reactRefresh,
    },
    rules: {
      'react-refresh/only-export-components': [
        'warn',
        { allowConstantExport: true },
      ],
    },
  },
)
