import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { expect, test } from '@playwright/test';
import { MODERATOR, root } from './support/env.js';
import { expectNoHorizontalScroll, freshPhone, imageFixtures, otpFor } from './support/helpers.js';

const shots = path.join(root, 'e2e', 'screenshots');
fs.mkdirSync(shots, { recursive: true });

const PASSWORD = 'e2e-password-1';

async function register(page, name, phone) {
    await page.goto('/register');
    await page.locator('#name').fill(name);
    await page.locator('#phone').fill(phone);
    await page.locator('#password').fill(PASSWORD);
    await page.locator('#password_confirmation').fill(PASSWORD);
    await page.getByRole('button', { name: 'إنشاء الحساب' }).click();

    await expect(page).toHaveURL(/verify/);
    await page.locator('#code').fill(await otpFor(phone));
    await page.getByRole('button', { name: 'تأكيد' }).click();
    await expect(page).not.toHaveURL(/verify/);
}

test.describe.configure({ mode: 'serial' });

let state;

test.describe('full journey', () => {
    test.beforeAll(({}, info) => {
        state = { title: `سيارة تويوتا كورولا للبيع ${info.project.name} ${Date.now() % 100000}`, seller: freshPhone() };
    });

    test('a visitor registers with an OTP and lands signed in', async ({ page }, info) => {
        await register(page, 'بائع تجريبي', state.seller);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/dashboard/);
        await expect(page.getByRole('heading', { name: 'لوحتي' })).toBeVisible();
        await expectNoHorizontalScroll(page, 'dashboard');
        await page.screenshot({ path: path.join(shots, `${info.project.name}-dashboard-empty.png`), fullPage: true });

        state.storage = await page.context().storageState();
    });

    test('a wrong OTP is rejected and the right one is accepted', async ({ page }) => {
        const phone = freshPhone();
        await page.goto('/register');
        await page.locator('#name').fill('مستخدم بكود خاطئ');
        await page.locator('#phone').fill(phone);
        await page.locator('#password').fill(PASSWORD);
        await page.locator('#password_confirmation').fill(PASSWORD);
        await page.getByRole('button', { name: 'إنشاء الحساب' }).click();

        await page.locator('#code').fill('000000');
        await page.getByRole('button', { name: 'تأكيد' }).click();
        await expect(page).toHaveURL(/verify/);
        await expect(page.locator('.text-red-700').first()).toBeVisible();

        await page.locator('#code').fill(await otpFor(phone));
        await page.getByRole('button', { name: 'تأكيد' }).click();
        await expect(page).not.toHaveURL(/verify/);
    });

    test('the seller posts a car through the multi-step form with dynamic fields and 3 photos', async ({ browser }, info) => {
        const context = await browser.newContext({
            storageState: state.storage,
            viewport: info.project.use.viewport,
            isMobile: info.project.use.isMobile,
            hasTouch: info.project.use.hasTouch,
            locale: 'ar-EG',
        });
        const page = await context.newPage();
        const photos = imageFixtures(path.join(os.tmpdir(), `shams-e2e-${info.project.name}`));

        await page.goto('/ads/create');
        await expectNoHorizontalScroll(page, 'form step 1');
        await page.screenshot({ path: path.join(shots, `${info.project.name}-form-1-category.png`), fullPage: true });

        await page.getByRole('button', { name: 'سيارات', exact: true }).click();
        await page.getByRole('button', { name: 'سيارات للبيع', exact: true }).click();

        await expect(page.locator('#title')).toBeVisible();
        await page.locator('#title').fill(state.title);
        await page.locator('#description').fill('سيارة بحالة ممتازة، صيانة دورية بالتوكيل، ترخيص ساري، السعر قابل للتفاوض مع الجادين فقط.');
        await page.locator('#price').fill('285000');
        await page.locator('#governorate_id').selectOption({ index: 1 });
        await page.locator('#phone').fill(state.seller);

        await expect(page.locator('#field_brand')).toBeVisible();
        await page.locator('#field_brand').selectOption({ index: 1 });
        await page.locator('#field_model').fill('كورولا');
        await page.locator('#field_year').fill('2019');
        await expectNoHorizontalScroll(page, 'form step 2');
        await page.screenshot({ path: path.join(shots, `${info.project.name}-form-2-details.png`), fullPage: true });
        await page.getByRole('button', { name: 'التالي' }).click();

        await page.locator('input[type=file][name="images[]"]').setInputFiles(photos);
        await expect(page.locator('img[src^="blob:"]')).toHaveCount(3);
        await expectNoHorizontalScroll(page, 'form step 3');
        await page.screenshot({ path: path.join(shots, `${info.project.name}-form-3-images.png`), fullPage: true });
        await page.getByRole('button', { name: 'التالي' }).click();

        await expect(page.getByText(state.title).first()).toBeVisible();
        await expectNoHorizontalScroll(page, 'form step 4');
        await page.getByRole('button', { name: 'نشر الإعلان' }).click();

        await expect(page).toHaveURL(/dashboard/);
        await expect(page.getByText('تم استلام إعلانك وسيظهر بعد مراجعته')).toBeVisible();

        await page.getByRole('link', { name: /قيد المراجعة/ }).click();
        const link = page.locator('article', { hasText: state.title }).locator('a[href*="/ad/"]').first();
        state.listingUrl = new URL(await link.getAttribute('href')).pathname;
        expect(state.listingUrl).toMatch(/^\/ad\/\d+/);

        await page.screenshot({ path: path.join(shots, `${info.project.name}-dashboard-pending.png`), fullPage: true });
        await context.close();
    });

    test('a pending listing is invisible to visitors', async ({ page }) => {
        expect((await page.goto(state.listingUrl)).status()).toBe(404);

        await page.goto('/search?q=' + encodeURIComponent(state.title));
        await expect(page.getByText('0 إعلان')).toBeVisible();
        await expect(page.locator('article')).toHaveCount(0);
    });

    test('a moderator approves it in the admin panel', async ({ page }, info) => {
        await page.goto('/admin/login');
        await page.locator('#form\\.phone').fill(MODERATOR.phone);
        await page.locator('#form\\.password').fill(MODERATOR.password);
        await page.getByRole('button', { name: 'تسجيل الدخول' }).click();
        await expect(page).toHaveURL(/\/admin(?!\/login)/);

        await page.goto('/admin/listings');
        await page.getByRole('searchbox', { name: 'بحث', exact: true }).fill(state.title);
        const row = page.getByRole('row', { name: new RegExp(state.title) });
        await expect(row).toBeVisible();
        await page.screenshot({ path: path.join(shots, `${info.project.name}-admin-listings.png`), fullPage: true });

        await row.getByRole('button', { name: 'موافقة' }).click();
        await page.getByRole('button', { name: 'تأكيد' }).click();
        await expect(page.getByText('تمت الموافقة على الإعلان.')).toBeVisible();
    });

    test('the approved listing is public and hides the phone until the visitor asks for it', async ({ page }, info) => {
        const response = await page.goto(state.listingUrl);
        expect(response.status()).toBe(200);
        await expect(page.getByRole('heading', { name: state.title })).toBeVisible();
        await expect(page.getByText('كورولا').first()).toBeVisible();
        await expectNoHorizontalScroll(page, 'public listing');

        const html = await response.text();
        expect(html).not.toContain(state.seller.replace(/^0/, ''));

        await page.getByRole('button', { name: 'إظهار الرقم' }).click();
        await expect(page.locator('a[href^="tel:"]')).toContainText(state.seller.replace(/^0/, ''));
        await page.screenshot({ path: path.join(shots, `${info.project.name}-listing-revealed.png`), fullPage: true });

        await page.goto('/search?q=' + encodeURIComponent('كورولا'));
        await expect(page.getByText(state.title).first()).toBeVisible();
    });

    test('another user favorites the listing and reports it', async ({ page }, info) => {
        await register(page, 'مشتري تجريبي', freshPhone());

        await page.goto(state.listingUrl);
        const heart = page.getByRole('button', { name: 'إضافة أو إزالة من المفضلة' }).first();
        await expect(heart).toHaveAttribute('aria-pressed', 'false');
        await heart.click();
        await expect(heart).toHaveAttribute('aria-pressed', 'true');

        await page.reload();
        await expect(page.getByRole('button', { name: 'إضافة أو إزالة من المفضلة' }).first()).toHaveAttribute('aria-pressed', 'true');

        await page.goto('/favorites');
        await expect(page.getByText(state.title)).toBeVisible();

        await page.goto(state.listingUrl);
        await page.getByRole('button', { name: 'إبلاغ عن الإعلان' }).click();
        const dialog = page.getByRole('dialog', { name: 'الإبلاغ عن الإعلان' });
        await expect(dialog).toBeVisible();
        await page.screenshot({ path: path.join(shots, `${info.project.name}-report-modal.png`) });
        await expectNoHorizontalScroll(page, 'report modal');
        await dialog.getByLabel('احتيال أو نصب').check();
        await dialog.getByLabel('ملاحظات').fill('اختبار آلي للبلاغ.');
        await dialog.getByRole('button', { name: 'إرسال البلاغ' }).click();
        await expect(page.getByText('شكراً لك، تم استلام بلاغك')).toBeVisible();

        await page.getByRole('button', { name: 'إبلاغ عن الإعلان' }).click();
        await page.getByRole('dialog').getByLabel('إعلان مكرر').check();
        await page.getByRole('dialog').getByRole('button', { name: 'إرسال البلاغ' }).click();
        await expect(page.getByText('لقد أبلغت عن هذا الإعلان من قبل.')).toBeVisible();
    });

    test('a guest who taps the heart is taken to sign in', async ({ page }) => {
        await page.goto(state.listingUrl);
        await page.getByRole('button', { name: 'إضافة أو إزالة من المفضلة' }).first().click();
        await expect(page).toHaveURL(/login/);
    });
});
