import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';

test.use({ video: 'off', extraHTTPHeaders: {} });
test('perfil, seguridad y confirmación de cuenta conservan su comportamiento', async ({ page }, info) => {
  const errors: string[] = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('response', r => { if (r.status() >= 400 && /livewire|\/build\//.test(r.url())) errors.push(`${r.status()} ${r.url()}`); });
  await loginAs(page, 'ux-quotation@quantum.test');
  for (const width of [1920, 1440, 768, 390]) {
    await page.setViewportSize({ width, height: 950 });
    await page.goto('/profile');
    await expect(page.getByRole('heading', { name: 'Mi perfil', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Información personal' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Tu cuenta', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Actividad reciente de tu cuenta' })).toBeVisible();
    await expect(page.getByTestId('profile-activity')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Guardar datos' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Actualizar contraseña' })).toBeVisible();
    await expect(page.getByText(/Correo (verificado|pendiente de verificación)/)).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: info.outputPath(`profile-${width}.png`), fullPage: true, animations: 'disabled' });
    await page.getByRole('button', { name: 'Eliminar cuenta', exact: true }).first().click();
    await expect(page.getByRole('button', { name: 'Cancelar', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Cancelar', exact: true })).not.toBeVisible();
    await page.goto('/admin/users');
    await page.screenshot({ path: info.outputPath(`reference-${width}.png`), fullPage: true, animations: 'disabled' });
  }
  await page.goto('/profile');
  await page.getByRole('button', { name: 'Guardar datos' }).click();
  await expect(page.getByText('Guardado.', { exact: true }).first()).toBeVisible();
  expect(errors).toEqual([]);
});
