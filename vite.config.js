import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted WOFF2 only (SIL OFL, see resources/fonts). The remote provider also
            // emitted WOFF faces, which made browsers download every weight twice.
            fonts: [
                local('Instrument Sans', {
                    // Fallback metric tuning needs the optional fontaine package; layout shift is already 0.
                    optimizedFallbacks: false,
                    variants: [
                        { src: 'resources/fonts/instrument-sans-400.woff2', weight: 400 },
                        { src: 'resources/fonts/instrument-sans-500.woff2', weight: 500 },
                        { src: 'resources/fonts/instrument-sans-600.woff2', weight: 600 },
                    ],
                }),
                local('Instrument Serif', {
                    optimizedFallbacks: false,
                    variants: [{ src: 'resources/fonts/instrument-serif-400.woff2', weight: 400 }],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
