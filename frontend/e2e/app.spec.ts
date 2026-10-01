import { Page, expect, test } from '@playwright/test';
import path from 'node:path';

const SAMPLE_XLSX = path.resolve(__dirname, '../../docs/import example (2) Фул.xlsx');
const EMAIL = process.env['E2E_EMAIL'] ?? 'admin@example.com';
const PASSWORD = process.env['E2E_PASSWORD'] ?? 'admin123';

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByTestId('login-email').fill(EMAIL);
  await page.getByTestId('login-password').fill(PASSWORD);
  await page.getByTestId('login-submit').click();
  await expect(page).toHaveURL(/\/products/);
}

test.describe('auth', () => {
  test('protected pages redirect to login', async ({ page }) => {
    await page.goto('/import');
    await expect(page).toHaveURL(/\/login\?returnUrl=%2Fimport/);
  });

  test('wrong password shows an error', async ({ page }) => {
    await page.goto('/login');
    await page.getByTestId('login-email').fill(EMAIL);
    await page.getByTestId('login-password').fill('wrong-password');
    await page.getByTestId('login-submit').click();
    await expect(page.getByTestId('login-error')).toContainText('Неверный email или пароль');
  });
});

test.describe.serial('import → list → card', () => {
  test('imports the sample xlsx asynchronously and shows progress + report', async ({ page }) => {
    await login(page);
    await page.getByTestId('nav-import').click();
    await expect(page).toHaveURL(/\/import/);

    await page.getByTestId('import-file-input').setInputFiles(SAMPLE_XLSX);
    await expect(page.getByTestId('import-file-name')).toContainText('.xlsx');
    await page.getByTestId('import-submit').click();

    const status = page.getByTestId('import-status');
    await expect(status).toBeVisible();
    await expect(page.getByTestId('import-status-label')).toHaveText('Завершён', { timeout: 90_000 });
    await expect(page.getByTestId('import-progress')).toHaveText('100%');
    await expect(page.getByTestId('import-failed')).toContainText('С ошибками: 0');

    // The sample contains a broken image link in row 34 — it must appear in the report as a warning.
    const report = page.getByTestId('import-report');
    await expect(report).toContainText('SNL-5581481_p.jpg');
  });

  test('re-import does not create duplicates', async ({ page }) => {
    await login(page);
    await page.goto('/import');
    await page.getByTestId('import-file-input').setInputFiles(SAMPLE_XLSX);
    await page.getByTestId('import-submit').click();
    await expect(page.getByTestId('import-status-label')).toHaveText('Завершён', { timeout: 90_000 });
    await expect(page.getByTestId('import-created')).toContainText('Создано: 0');
    await expect(page.getByTestId('import-updated')).toContainText('Обновлено: 40');
  });

  test('list supports server-side pagination and filters', async ({ page }) => {
    await login(page);
    const table = page.getByTestId('products-table');
    await expect(table.getByTestId('product-link').first()).toBeVisible();

    const firstPageFirst = await table.getByTestId('product-link').first().textContent();
    const pageRequest = page.waitForRequest((r) => r.url().includes('/api/products') && r.url().includes('page=2'));
    await page.getByRole('button', { name: /следующая|next/i }).click();
    await pageRequest;
    await expect(page.getByTestId('products-summary')).toContainText('страница 2');
    await expect(table.getByTestId('product-link').first()).not.toHaveText(firstPageFirst ?? '');

    await page.getByTestId('filter-name').fill('легинсы');
    await page.getByTestId('filter-price-min').fill('1000');
    await page.getByTestId('filter-apply').click();
    await expect(page.getByTestId('products-summary')).toContainText('Всего: 5');
    await expect(table.getByTestId('product-link')).toHaveCount(5);
    await page.screenshot({ path: 'test-results/products-list.png', fullPage: true });
    for (const name of await table.getByTestId('product-link').allTextContents()) {
      expect(name.toLowerCase()).toContain('легинсы');
    }
  });

  test('product card shows fields, attributes, images and last import', async ({ page }) => {
    await login(page);
    await page.getByTestId('filter-name').fill('Бермуды мужские, Grigio/Verde');
    await page.getByTestId('filter-apply').click();
    await expect(page.getByTestId('products-summary')).toContainText('Всего: 1');
    await page.getByTestId('product-link').first().click();

    await expect(page).toHaveURL(/\/products\/\d+$/);
    const card = page.getByTestId('product-card');
    await expect(page.getByTestId('product-name')).toContainText('Бермуды мужские, Grigio/Verde');
    await expect(page.getByTestId('product-code')).toHaveText('3UHfAid1jaMiwgBuNvnsf3');
    await expect(page.getByTestId('product-price')).toContainText(/1\s?320/);
    await expect(page.getByTestId('product-discount')).toContainText('33,33');
    await expect(card.getByTestId('product-attributes')).toContainText('Бренд');
    await expect(card.getByTestId('product-attributes')).toContainText('OMSA');

    const image = page.getByTestId('product-main-image');
    await expect(image).toBeVisible();
    await expect(image).toHaveAttribute('src', /^\/uploads\/products\/.+\.jpg$/);
    expect(await image.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);

    await expect(page.getByText('Последний импорт')).toBeVisible();
  });
});
