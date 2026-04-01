import { defineConfig } from 'vite';

export default defineConfig({
    root:      '.',
    publicDir: 'public',
    base:      '/outsourcing-scorecard/',
    build: {
        outDir:      'dist',
        emptyOutDir: true,
    },
});
