import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
export const PORT = 8081;
export const BASE_URL = `http://127.0.0.1:${PORT}`;

export const uploadsDir = path.join(root, 'storage', 'app', 'e2e-public');
export const uploadsLink = path.join(root, 'public', 'storage-e2e');

export const ADMIN = { phone: '01000000000', password: 'e2e-admin-pass' };
export const MODERATOR = { phone: '01111111111', password: '123456789' };

export const appEnv = {
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    APP_URL: BASE_URL,
    SEED_DEMO_DATA: 'false',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    SMS_DRIVER: 'log',
    CAPTCHA_DRIVER: 'null',
    LOG_CHANNEL: 'single',
    ADMIN_PHONE: ADMIN.phone,
    ADMIN_PASSWORD: ADMIN.password,
    PUBLIC_DISK_ROOT: uploadsDir,
    PUBLIC_DISK_URL: `${BASE_URL}/storage-e2e`,
    CLASSIFIEDS_REQUIRE_REVIEW: 'true',
};
