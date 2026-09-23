import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { appEnv, root, uploadsDir, uploadsLink } from './support/env.js';

const run = (args) => execFileSync('php', args, { cwd: root, env: { ...process.env, ...appEnv }, stdio: 'inherit' });

export default async function globalSetup() {
    fs.rmSync(uploadsDir, { recursive: true, force: true });
    fs.mkdirSync(uploadsDir, { recursive: true });

    if (!fs.existsSync(uploadsLink)) {
        fs.symlinkSync(uploadsDir, uploadsLink, 'junction');
    }

    run(['artisan', 'migrate:fresh', '--seed', '--force']);
    run(['e2e/support/seed.php']);
    run(['artisan', 'cache:clear']);
}
