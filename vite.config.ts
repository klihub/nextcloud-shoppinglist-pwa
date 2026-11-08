import { defineConfig } from 'vite';

export default defineConfig({
  // Nextcloud serves app assets from the app's js/ directory.
  base: './',
  build: {
    outDir: 'js',
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: 'src/main.tsx',
      output: {
        entryFileNames: 'main.js',
        assetFileNames: 'main.[ext]',
      },
    },
  },
});
