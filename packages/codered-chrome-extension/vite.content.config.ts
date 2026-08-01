import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
  root: '.',
  base: './',
  cacheDir: 'node_modules/.vite-content',
  build: {
    outDir: 'dist',
    emptyOutDir: false,
    minify: 'esbuild',
    target: 'es2022',
    lib: {
      entry: resolve(__dirname, 'src/content/content.ts'),
      name: 'CodeREDShalomContent',
      formats: ['iife'],
      fileName: () => 'content.js',
    },
    rollupOptions: {
      output: {
        inlineDynamicImports: true,
      },
    },
  },
});
