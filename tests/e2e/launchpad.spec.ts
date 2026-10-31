import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { USERS } from './fixtures/test-data';

test.use({ video: 'off' });

test('launchpad, navegación contextual y búsqueda accesible', async ({ page }, testInfo) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => {
    if (response.url().includes('/livewire/') && response.status() >= 400) errors.push(`${response.status()} ${response.url()}`);
    if (response.url().includes('/build/') && response.status() >= 400) errors.push(`${response.status()} ${response.url()}`);
  });
  await loginAs(page, process.env.E2E_ADMIN_EMAIL ?? USERS.admin.email);
  await page.goto('/inicio');
  await expect(page.getByRole('heading', { name: 'Inicio', exact: true })).toBeVisible();
  await expect(page.locator('aside')).toHaveCount(0);
  await expect(page.locator('body')).toHaveCSS('display', 'flex');
  await expect(page.getByRole('heading', { name: 'Continuar trabajando' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('inicio.png'), fullPage: true });
  for (const area of ['compras', 'ventas', 'bodega', 'finanzas', 'clientes', 'proveedores', 'reportes']) {
    const response = await page.goto(`/inicio/${area}`);
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('link', { name: 'Todas las áreas', exact: true }).first()).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath(`${area}.png`), fullPage: true });
  }
  if (testInfo.project.name === 'chrome-desktop') {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/inicio/ventas');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath('ventas-tablet.png'), fullPage: false });
    await page.setViewportSize({ width: 1440, height: 950 });
  }
  for (const path of ['/admin/users', '/clients', '/admin/proveedores', '/dashboard', '/sales/quotations/fase-cotizacion', '/warehouse/incoming-orders', '/inventory/products', '/inventory/moves', '/purchases/create', '/finance/cxc', '/finance/cxp', '/finance/payments', '/admin/exports']) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBe(200);
    await expect(page.getByRole('navigation', { name: 'Ruta de navegación' })).toBeVisible();
    await expect(page.locator('aside')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), path).toBe(true);
    await page.screenshot({ path: testInfo.outputPath(`${path.replaceAll('/', '-')}.png`), fullPage: false });
    if (path === '/clients') {
      const search = page.getByRole('searchbox', { name: 'Buscar cliente, contacto o RUC' });
      await search.fill('sin-coincidencias-launchpad');
      await page.waitForResponse(response => response.url().includes('/livewire/update') && response.request().method() === 'POST');
      await search.fill('');
      await page.waitForResponse(response => response.url().includes('/livewire/update') && response.request().method() === 'POST');
    }
    if (path === '/sales/quotations/fase-cotizacion') {
      const openOrder = page.getByRole('link', { name: 'Abrir orden' }).first();
      if (await openOrder.count()) {
        await openOrder.click();
        await expect(page.getByRole('navigation', { name: 'Progreso del proceso comercial' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('cotizacion-detalle.png'), fullPage: false });
      }
    }
  }
  expect(errors).toEqual([]);
});
