import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { resolve } from 'path';

export default defineConfig(({ mode }) => {
  const target = process.env.BUILD_TARGET || 'frontend';

  if (target === 'admin-docs') {
    return {
      plugins: [svelte()],
      build: {
        outDir: 'assets/admin',
        emptyOutDir: false,
        sourcemap: false,
        rollupOptions: {
          input: resolve(__dirname, 'src-admin/docs/main.ts'),
          output: {
            entryFileNames: 'docs.js',
            assetFileNames: 'docs.[ext]',
          },
        },
      },
    };
  }

  // Default: frontend favorites IIFE bundle
  return {
    build: {
      outDir: 'assets/js',
      emptyOutDir: false,
      sourcemap: false,
      lib: {
        entry: resolve(__dirname, 'src-ts/favorites.ts'),
        name: 'WPEFavorites',
        formats: ['iife'],
        fileName: () => 'favorites.js',
      },
      rollupOptions: {
        output: {
          entryFileNames: 'favorites.js',
        },
      },
    },
  };
});
