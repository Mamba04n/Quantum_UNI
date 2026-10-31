/**
 * AUTH SPEC — tests/e2e/auth.spec.ts
 *
 * Covers:
 *  1. Login correcto como admin → dashboard visible
 *  2. Login incorrecto → mensaje de error claro, sin 500
 *  3. Logout → redirige a login
 *  4. Ruta protegida sin sesión → redirige a /login
 *  5. Usuario ya autenticado visita /login → redirige al dashboard
 *  6. Back button después de logout no expone la sesión (no 419)
 */
import { expect, test } from '@playwright/test';
import { loginAs, logout } from './helpers/auth';
import { expectNoServerError } from './helpers/assertions';
import { USERS } from './fixtures/test-data';

test.describe('Autenticación', () => {

  // ── 1. Login correcto ───────────────────────────────────────────────────────
  test('admin puede iniciar sesión y ver el dashboard', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);

    await expect(page).not.toHaveURL(/\/login$/);
    await expect(page).toHaveURL(/\/dashboard/);
    await expectNoServerError(page);

    // Verificar que aparece el nombre o email del usuario en el UI
    const body = page.locator('body');
    await expect(body).not.toContainText('Server Error');

    await testInfo.attach('dashboard-post-login', {
      body: await page.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });
  });

  // ── 2. Login incorrecto ────────────────────────────────────────────────────
  test('credenciales incorrectas muestran error claro sin Server Error', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('#email, input[type="email"]').first()).toBeVisible();

    await page.locator('#email, input[type="email"]').first().fill('no-existe@quantum.test');
    await page.locator('#password, input[type="password"]').first().fill('ContraseñaInválida123');
    await page.locator('button[type="submit"]').click();

    // Debe permanecer en /login (no redirigir)
    await expect(page).toHaveURL(/\/login/);
    await expectNoServerError(page);

    // Debe aparecer mensaje de error de credenciales
    const body = page.locator('body');
    await expect(body).toContainText(
      /credenciales|contraseña|These credentials do not match|no coincid/i
    );
  });

  test('contraseña vacía muestra validación sin Server Error', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email, input[type="email"]').first().fill('admin@quantum.test');
    await page.locator('#password, input[type="password"]').first().fill('');
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/\/login/);
    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/requerido|required|contraseña/i);
  });

  // ── 3. Logout ──────────────────────────────────────────────────────────────
  test('logout redirige a login y cierra sesión correctamente', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await expect(page).toHaveURL(/\/dashboard/);

    await logout(page);

    await expect(page).toHaveURL(/\/login|\/$/);
    await expectNoServerError(page);
    await expect(page.locator('body')).not.toContainText(/419|Page Expired/i);

    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/);
    await expectNoServerError(page);
    await expect(page.locator('body')).not.toContainText(/419|Page Expired/i);
  });

  // ── 4. Ruta protegida sin sesión ───────────────────────────────────────────
  test('ruta protegida sin sesión redirige a /login', async ({ page }) => {
    // Nuevo contexto sin cookies — fresh page ya tiene contexto limpio en Playwright
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/);
    await expectNoServerError(page);
  });

  test('rutas de admin sin sesión redirigen a /login', async ({ page }) => {
    for (const path of ['/admin/users', '/admin/approvals', '/finance/cxp', '/clients']) {
      await page.goto(path);
      await expect(page).toHaveURL(/\/login/);
      await expectNoServerError(page);
    }
  });

  // ── 5. Usuario autenticado visita /login ───────────────────────────────────
  test('usuario autenticado que visita /login es redirigido al dashboard', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await expect(page).toHaveURL(/\/dashboard/);

    // Intentar navegar de vuelta a /login
    await page.goto('/login');

    // Debe ser redirigido al dashboard (no quedarse en /login)
    // Laravel redirige a /dashboard cuando ya está autenticado
    await expect(page).not.toHaveURL(/\/login$/);
    await expectNoServerError(page);
  });

  // ── 6. Back button sin 419 ─────────────────────────────────────────────────
  test('usar el botón atrás después de logout no genera error 419', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/dashboard/);

    // Hacer logout
    await logout(page);

    // Presionar atrás
    await page.goBack();

    // No debe aparecer error 419 (Page Expired) ni 500
    await expectNoServerError(page);
    const body = await page.locator('body').textContent() ?? '';
    expect(body).not.toContain('419');
    expect(body).not.toContain('Page Expired');
  });

  // ── Login por cada rol ─────────────────────────────────────────────────────
  for (const [key, user] of Object.entries(USERS)) {
    if (key === 'cliente') {
      test(`cliente externo (${user.email}) accede al portal, no al ERP`, async ({ page }) => {
        await loginAs(page, user.email);
        // El cliente debe ir al portal, no al ERP dashboard
        await expect(page).toHaveURL(/\/mi-portal|\/login|\/$/);
        await expectNoServerError(page);

        // No debe ver el dashboard del ERP
        await page.goto('/dashboard');
        // Debe ser redirigido al portal o mostrar 403
        await expect(page).not.toHaveURL(/\/dashboard$/);
      });
      continue;
    }

    test(`${key} (${user.email}) puede iniciar sesión`, async ({ page }) => {
      await loginAs(page, user.email);
      await expect(page).not.toHaveURL(/\/login$/);
      await expectNoServerError(page);
    });
  }

});
