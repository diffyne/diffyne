import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/js/diffyne.js'),
            name: 'Diffyne',
            fileName: 'diffyne',
            formats: ['iife']
        },
        outDir: 'public/js',
        emptyOutDir: true,
        rollupOptions: {
            output: {
                entryFileNames: 'diffyne.js',
                assetFileNames: 'diffyne.[ext]'
            }
        },
        minify: 'terser',
        sourcemap: false
    }
});
