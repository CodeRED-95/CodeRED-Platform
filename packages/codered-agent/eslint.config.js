import js from '@eslint/js';
export default [js.configs.recommended,{files:['**/*.ts'],languageOptions:{parserOptions:{ecmaVersion:'latest',sourceType:'module'},globals:{process:'readonly',console:'readonly',Buffer:'readonly',URL:'readonly',fetch:'readonly',setTimeout:'readonly',setInterval:'readonly',clearInterval:'readonly'}}}];
