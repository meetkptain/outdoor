import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    setupFiles: [resolve(__dirname, 'src/tests/setup.ts')],
    globals: true,
    coverage: {
      enabled: false,
    },
  },
  resolve: {
    alias: {
      '@parapente/shared': resolve(__dirname, 'src'),
    },
  },
});

