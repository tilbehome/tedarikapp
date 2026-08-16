import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import reactHooks from 'eslint-plugin-react-hooks';

export default tseslint.config(
  { ignores: ['dist', '../public/panel'] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
    },
    plugins: {
      'react-hooks': reactHooks,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      // K14/K29: parada JS aritmetiği YASAK. Aşağıdaki kural, para alanları üzerinde
      // sayısal dönüşüm yapılmasını yakalamaya yardım eder (asıl denetim kod incelemesi).
      'no-restricted-globals': ['error', 'parseFloat', 'parseInt'],
      'no-restricted-properties': [
        'error',
        { object: 'Number', property: 'parseFloat', message: 'Para JS tarafında hesaplanmaz (K14/K29).' },
        { object: 'Number', property: 'parseInt', message: 'Para JS tarafında hesaplanmaz (K14/K29).' },
      ],
    },
  },
);
