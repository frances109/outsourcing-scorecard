import { defineConfig } from 'vite';

export default defineConfig({
    root: '.',
    publicDir: 'public',
    base: '/scorecard/',
    build: {
        outDir: 'dist',
        emptyOutDir: true,
    },
});
