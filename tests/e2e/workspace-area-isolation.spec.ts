import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';

test.use({ video: 'off', extraHTTPHeaders: {} });

test('cada dashboard contiene únicamente procesos de su área', async ({ page }, info) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => {
    if (response.status() >= 400 && /livewire|\/build\//.test(response.url())) errors.push(`${response.status()} ${response.url()}`);
  });
  await loginAs(page, process.env.E2E_ADMIN_EMAIL ?? 'ux-quotation@quantum.test');
  const areas: Record<string, string[]> = {
    ventas: ['Nueva solicitud', 'Venta directa', 'Cotizaciones', 'Pedidos al extranjero'],
    finanzas: ['Flujo financiero', 'Cuentas por cobrar', 'Cuentas por pagar', 'Tipos de cambio'],
    bodega: ['Productos y existencias', 'Recepción de pedidos', 'Movimientos de inventario', 'Visor de kardex'],
    clientes: ['Directorio de clientes', 'Atención y tickets'],
    proveedores: ['Directorio de proveedores', 'Orden de compra'],
    operaciones: ['Centro de aprobaciones', 'Autorización de despacho', 'Seguimiento maestro'],
    equipo: ['Usuarios', 'Chat interno'],
    reportes: ['Dashboard general', 'Indicadores KPI', 'Exportaciones', 'Monitor de actividad'],
    configuracion: ['Blog', 'Catálogos', 'Mi perfil'],
  };
  for (const [area, labels] of Object.entries(areas)) {
    const response = await page.goto(`/inicio/${area}`);
    expect(response?.status(), area).toBe(200);
    const content = page.getByTestId('workspace-content');
    await expect(content).toHaveAttribute('data-area', area);
    const processes = page.getByTestId('area-processes');
    await expect(content.getByRole('heading').filter({ hasText: 'Acciones de' })).toBeVisible();
    await expect(page.locator('header').getByRole('navigation', { name: /^Módulos de/ })).toHaveCount(0);
    await expect(page.getByRole('searchbox')).toHaveCount(1);
    await expect(content.getByRole('link')).toHaveCount(labels.length + 1);
    await expect(processes.getByRole('link')).toHaveCount(labels.length);
    for (const label of labels) await expect(processes.getByRole('link').filter({ hasText: label })).toBeVisible();
    await expect(content.getByRole('navigation', { name: 'Áreas de trabajo' })).toHaveCount(0);
    await expect(content.getByText('Accesos rápidos', { exact: true })).toHaveCount(0);
    const links = await content.getByRole('link').evaluateAll(nodes => nodes.map(node => new URL((node as HTMLAnchorElement).href).pathname));
    if (area === 'finanzas') expect(links.every(path => path === '/inicio' || path.startsWith('/finance/'))).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: info.outputPath(`${area}-1440.png`), fullPage: true, animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 950 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: info.outputPath(`${area}-390.png`), fullPage: true, animations: 'disabled' });
    const destination = processes.getByRole('link').first();
    const href = await destination.getAttribute('href');
    await destination.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(href!);
    await expect(page.getByRole('navigation', { name: /^Módulos de/ })).toBeVisible();
    await expect(page.getByRole('navigation', { name: /^Módulos de/ }).getByRole('link', { name: 'Resumen', exact: true })).toHaveAttribute('href', new RegExp(`/inicio/${area}$`));
    await page.setViewportSize({ width: 1440, height: 950 });
  }
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 950 });
    await page.goto('/inicio/finanzas');
    const search = page.getByRole('searchbox', { name: 'Buscar proceso en Finanzas' });
    await search.fill('tipos');
    await expect(page.getByTestId('area-processes').getByRole('link')).toHaveCount(1);
    await search.fill('sin coincidencias');
    await expect(page.getByRole('status').filter({ hasText: 'No se encontraron procesos en Finanzas.' })).toBeVisible();
    await search.fill('');
    await expect(page.getByTestId('area-processes').getByRole('link')).toHaveCount(4);
    await expect(page.getByTestId('area-processes').getByRole('link').filter({ hasText: 'Tipos de cambio' })).toBeVisible();
    await page.mouse.move(0, 0);
    await page.screenshot({ path: info.outputPath(`finanzas-${width}.png`), fullPage: true, animations: 'disabled' });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.goto('/admin/users');
    await expect(page.getByText('Equipo Interno', { exact: true })).toBeVisible();
    await page.screenshot({ path: info.outputPath(`reference-${width}.png`), fullPage: true, animations: 'disabled' });
  }
  expect(errors).toEqual([]);
});
