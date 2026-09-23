import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { expect } from '@playwright/test';
import { root } from './env.js';

const LOG = path.join(root, 'storage', 'logs', 'laravel.log');

export function freshPhone() {
    const tail = String(Math.floor(Math.random() * 1e8)).padStart(8, '0');

    return `010${tail}`;
}

export async function otpFor(phone, { timeout = 10_000 } = {}) {
    const e164 = `+20${phone.replace(/^0/, '')}`;
    const started = Date.now();

    while (Date.now() - started < timeout) {
        const lines = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n') : [];
        const marker = `SMS to ${e164}:`;
        const hits = lines.filter((line) => line.includes(marker));

        if (hits.length) {
            const message = hits.at(-1).split(marker)[1] ?? '';
            const match = message.match(/(\d{6})/);

            if (match) {
                return match[1];
            }
        }

        await new Promise((resolve) => setTimeout(resolve, 200));
    }

    throw new Error(`No OTP was logged for ${e164}`);
}

export function makePng(width, height, [r, g, b]) {
    const raw = Buffer.alloc((width * 3 + 1) * height);

    for (let y = 0; y < height; y++) {
        const row = y * (width * 3 + 1);

        for (let x = 0; x < width; x++) {
            const stripe = Math.abs(x - y) < 8;
            raw[row + 1 + x * 3] = stripe ? 255 : r;
            raw[row + 2 + x * 3] = stripe ? 255 : g;
            raw[row + 3 + x * 3] = stripe ? 255 : b;
        }
    }

    const crcTable = Array.from({ length: 256 }, (_, n) => {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;

        return c >>> 0;
    });
    const crc = (buffer) => {
        let c = 0xffffffff;
        for (const byte of buffer) c = crcTable[(c ^ byte) & 0xff] ^ (c >>> 8);

        return (c ^ 0xffffffff) >>> 0;
    };
    const chunk = (type, data) => {
        const body = Buffer.concat([Buffer.from(type), data]);
        const out = Buffer.alloc(body.length + 8);
        out.writeUInt32BE(data.length, 0);
        body.copy(out, 4);
        out.writeUInt32BE(crc(body), body.length + 4);

        return out;
    };
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    header[8] = 8;
    header[9] = 2;

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', header),
        chunk('IDAT', zlib.deflateSync(raw)),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

export function imageFixtures(dir, count = 3) {
    fs.mkdirSync(dir, { recursive: true });
    const colors = [[30, 120, 200], [200, 90, 40], [60, 160, 90]];

    return Array.from({ length: count }, (_, i) => {
        const file = path.join(dir, `photo-${i + 1}.png`);
        fs.writeFileSync(file, makePng(640, 480, colors[i % colors.length]));

        return file;
    });
}

export async function expectNoHorizontalScroll(page, label = '') {
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));

    expect(scrollWidth, `horizontal overflow ${label}`).toBeLessThanOrEqual(clientWidth);
}

export async function expectRtlArabic(page) {
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
}

export async function login(page, { phone, password }) {
    await page.goto('/login');
    await page.locator('#phone').fill(phone);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'تسجيل الدخول' }).click();
}
