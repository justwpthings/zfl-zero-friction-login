import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react({ jsxRuntime: 'classic' })],
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/frontend/index.tsx'),
      },
      output: {
        entryFileNames: 'zfl-[name].[hash].js',
        chunkFileNames: 'zfl-[name].[hash].js',
        assetFileNames: 'zfl-[name].[hash].[ext]',
        format: 'iife',
        globals: {
          react: 'window.React',
          'react-dom': 'window.ReactDOM',
        },
      },
      external: ['react', 'react-dom'],
    },
    manifest: 'manifest.json',
    sourcemap: false,
  },
});
