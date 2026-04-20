import { test, expect } from '@playwright/test';

test.describe('Smoke', () => {
    test('home page responds without a server error', async ({ page }) => {
        const response = await page.goto('/');
        expect(response?.status()).toBeLessThan(500);
    });
});
