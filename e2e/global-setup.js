import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { appEnv, root, uploadsDir, uploadsLink } from './support/env.js';

const run = (args) => execFileSync('php', args, { cwd: root, env: { ...process.env, ...appEnv }, stdio: 'inherit' });

export default async function globalSetup() {
    execFileSync('php', ['-r', "(new PDO('mysql:host=127.0.0.1', 'root', ''))->exec('CREATE DATABASE IF NOT EXISTS shams_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"], { cwd: root });

    fs.rmSync(uploadsDir, { recursive: true, force: true });
    fs.mkdirSync(uploadsDir, { recursive: true });

    if (!fs.existsSync(uploadsLink)) {
        fs.symlinkSync(uploadsDir, uploadsLink, 'junction');
    }

    run(['artisan', 'migrate:fresh', '--seed', '--force']);
    run(['e2e/support/seed.php']);
    run(['artisan', 'cache:clear']);
}
