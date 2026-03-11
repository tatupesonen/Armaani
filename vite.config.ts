import { execSync } from 'child_process';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv, type Plugin } from 'vite';

/**
 * Vite plugin that regenerates TypeScript types from game handler schemas.
 * Watches `app/GameHandlers/*.php` during dev and runs the artisan command.
 */
function gameTypes(): Plugin {
    const command = 'php artisan game:generate-types';
    let timeout: ReturnType<typeof setTimeout> | null = null;

    function generate() {
        try {
            execSync(command, { stdio: 'ignore', timeout: 10_000 });
        } catch {
            // silently ignore — artisan may not be available or DB may be locked
        }
    }

    return {
        name: 'game-types',
        buildStart() {
            if (process.env.VITE_SKIP_GAME_TYPES !== '1') {
                generate();
            }
        },
        configureServer(server) {
            server.watcher.add('app/GameHandlers/**/*.php');
            server.watcher.add('app/Contracts/GameHandler.php');
            server.watcher.on('change', (path) => {
                if (
                    path.includes('GameHandlers/') ||
                    path.includes('Contracts/GameHandler')
                ) {
                    if (timeout) clearTimeout(timeout);
                    timeout = setTimeout(generate, 300);
                }
            });
        },
    };
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
            }),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            wayfinder({
                formVariants: true,
            }),
            gameTypes(),
        ],
        server: {
            host: '0.0.0.0',
            origin: `http://${env.SERVER_HOST || 'localhost'}:5173`,
            cors: true,
        },
        esbuild: {
            jsx: 'automatic',
        },
    };
});
