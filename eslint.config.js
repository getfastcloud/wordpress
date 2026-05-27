import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);
const wpPlugin = require('@wordpress/eslint-plugin');

export default tseslint.config(
  // WordPress i18n rules (valid-sprintf, text-domain, no-variables, etc.)
  ...wpPlugin.configs.i18n,

  // TypeScript type-checked rules — requires parserOptions.projectService so
  // ESLint has full type information and can catch assignment/access errors.
  ...tseslint.configs.recommendedTypeChecked,

  // Vue 3 essential rules (correctness without opinionated formatting)
  ...pluginVue.configs['flat/essential'],

  // Enable TypeScript project service for all files so type-checked rules work
  {
    languageOptions: {
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
  },

  // Vue files: use TypeScript parser for <script lang="ts"> blocks
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
        extraFileExtensions: ['.vue'],
      },
    },
  },

  // Project-wide overrides
  {
    rules: {
      // Enforce the fastcloud-offload-media text domain in all __() calls
      '@wordpress/i18n-text-domain': ['error', { allowedTextDomain: 'fastcloud-offload-media' }],
      // Vue components in this project use single-word names by convention
      'vue/multi-word-component-names': 'off',
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': ['error', { ignoreRestSiblings: true }],
    },
  },

  // Framework bootstrap files import .vue SFCs which projectService cannot resolve,
  // producing "error typed" values. Disable the affected rules for these files only.
  {
    files: ['**/main.ts', '**/router.ts'],
    rules: {
      '@typescript-eslint/no-unsafe-argument': 'off',
      '@typescript-eslint/no-unsafe-assignment': 'off',
    },
  },

  // Vue SFC internals that reference component types unresolvable by projectService.
  {
    files: ['**/*.vue'],
    rules: {
      '@typescript-eslint/no-unsafe-return': 'off',
    },
  },

  // Ignore build output and vendor code
  {
    ignores: ['dist/**', 'assets/**', 'node_modules/**'],
  },
);
