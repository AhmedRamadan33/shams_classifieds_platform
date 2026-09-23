import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';
import { root } from './support/env.js';
import { expectNoHorizontalScroll, expectRtlArabic } from './support/helpers.js';

const shots = path.join(root, 'e2e', 'screenshots');
fs.mkdirSync(shots, { recursive: true });

const staticPages = [
    ['home', '/'],
    ['category-cars', '/category/cars'],
    ['category-governorate', '/category/cars/cairo'],
    ['search', '/search?q=' + encodeURIComponent('شقة')],
    ['search-empty', '/search?q=' + encodeURIComponent('لا يوجد شيء بهذا الاسم')],
    ['login', '/login'],
    ['register', '/register'],
    ['forgot-password', '/forgot-password'],
    ['about', '/p/about'],
    ['terms', '/p/terms'],
    ['not-found', '/this-page-does-not-exist'],
];

test.describe('layout', () => {
    for (const [name, url] of staticPages) {
        test(`${name} is RTL, Arabic and has no horizontal scroll`, async ({ page }, info) => {
            await page.goto(url);
            await expectRtlArabic(page);
            await expect(page.locator('h1, h2').first()).toBeVisible();
            await expectNoHorizontalScroll(page, url);

            const font = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
            expect(font).toContain('Tajawal');

            await page.screenshot({ path: path.join(shots, `${info.project.name}-${name}.png`), fullPage: true });
        });
    }

    test('a listing page fits the viewport', async ({ page }, info) => {
        await page.goto('/');
        const link = page.locator('a[href*="/ad/"]').first();
        await link.click();
        await expect(page).toHaveURL(/\/ad\/\d+/);
        await expectRtlArabic(page);
        await expectNoHorizontalScroll(page, 'listing page');
        await expect(page.getByRole('button', { name: 'إظهار الرقم' })).toBeVisible();
        await page.screenshot({ path: path.join(shots, `${info.project.name}-listing.png`), fullPage: true });
    });

    test('cards never overflow their column on the listing grid', async ({ page }) => {
        await page.goto('/category/cars');
        const overflowing = await page.evaluate(() => [...document.querySelectorAll('article')].filter((el) => el.scrollWidth > el.clientWidth + 1).length);
        expect(overflowing).toBe(0);
    });
});

test.describe('mobile navigation', () => {
    test.skip(({ viewport }) => viewport.width >= 1024, 'mobile only');

    test('the header drawer opens, lists the links and closes with Escape', async ({ page }) => {
        await page.goto('/');
        const drawer = page.locator('#mobile-drawer');
        await expect(drawer).toBeHidden();

        await page.getByRole('button', { name: 'فتح القائمة' }).click();
        await expect(drawer).toBeVisible();
        await expect(drawer.getByRole('link', { name: 'تسجيل الدخول' })).toBeVisible();
        await expect(drawer.getByRole('link', { name: 'أضف إعلانك' })).toBeVisible();

        const box = await drawer.locator('nav').boundingBox();
        expect(box.x + box.width / 2).toBeGreaterThan(375 / 2);

        await page.keyboard.press('Escape');
        await expect(drawer).toBeHidden();
    });

    test('the filter drawer opens and applies a filter', async ({ page }) => {
        await page.goto('/category/cars');
        const filters = page.getByRole('dialog', { name: 'تصفية النتائج' });
        await expect(filters).toBeHidden();

        await page.getByRole('button', { name: 'الفلاتر' }).click();
        await expect(filters).toBeVisible();
        await expectNoHorizontalScroll(page, 'filter drawer open');

        await filters.locator('select[name="governorate"]').selectOption({ index: 1 });
        await filters.getByRole('button', { name: 'تطبيق' }).click();
        await expect(page).toHaveURL(/\/category\/cars\/[^/?]+/);
        await expect(filters).toBeHidden();
    });
});

test.describe('desktop navigation', () => {
    test.skip(({ viewport }) => viewport.width < 1024, 'desktop only');

    test('filters are a permanent sidebar and the search box is in the header', async ({ page }) => {
        await page.goto('/category/cars');
        await expect(page.getByRole('dialog', { name: 'تصفية النتائج' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'الفلاتر' })).toBeHidden();
        await expect(page.locator('#header-search')).toBeVisible();

        await page.locator('#header-search').fill('شقة');
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/search\?q=/);
    });
});
