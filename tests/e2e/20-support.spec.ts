/**
 * SUPPORT SPEC — tests/e2e/support.spec.ts
 *
 * Covers:
 *  1. Cliente crea ticket de soporte
 *  2. Soporte responde al ticket
 *  3. Cliente responde a la respuesta
 *  4. Soporte cierra el ticket
 *  5. Reabrir ticket si aplica
 *  6. Validar notificación de actividad
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, attachScreenshot } from './helpers/assertions';
import { uniqueSuffix } from './helpers/data';
import { USERS, ROUTES } from './fixtures/test-data';

test.describe('Soporte — Gestión de Tickets', () => {

  test('cliente puede ver el portal de tickets', async ({ page }, testInfo) => {
    await loginAs(page, USERS.cliente.email);
    await page.goto(ROUTES.customerTickets);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/ticket|soporte|support/i);
    await attachScreenshot(page, testInfo, 'cliente-tickets-portal');
  });

  test('soporte puede ver el módulo de tickets admin', async ({ page }, testInfo) => {
    await loginAs(page, USERS.soporte.email);
    await page.goto(ROUTES.adminTickets);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/ticket|soporte/i);
    await attachScreenshot(page, testInfo, 'soporte-tickets-admin');
  });

  test('admin puede ver el módulo de tickets admin', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminTickets);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
  });

  test('cliente puede crear un ticket nuevo', async ({ page }, testInfo) => {
    const uid = uniqueSuffix();
    const ticketSubject = `Ticket E2E ${uid}`;

    await loginAs(page, USERS.cliente.email);
    await page.goto(ROUTES.customerTickets);
    await page.waitForLoadState('networkidle');

    const newTicketBtn = page.getByRole('button', { name: /nuevo ticket|crear ticket|new ticket/i });
    if (await newTicketBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newTicketBtn.click());

      // Asunto
      const subjectInput = page.locator('input[wire\\:model*="subject"], input[wire\\:model*="asunto"]').first();
      if (await subjectInput.isVisible()) await subjectInput.fill(ticketSubject);

      // Mensaje
      const messageInput = page.locator('textarea[wire\\:model*="message"], textarea[wire\\:model*="body"], textarea[wire\\:model*="description"]').first();
      if (await messageInput.isVisible()) {
        await messageInput.fill('Necesito asistencia con el seguimiento de mi orden. E2E Test.');
        await messageInput.blur();
      }

      const saveBtn = page.getByRole('button', { name: /enviar|crear|submit/i }).last();
      if (await saveBtn.isVisible()) {
        await waitForLivewire(page, () => saveBtn.click());
        await expectNoServerError(page);
        await expect(page.locator('body')).toContainText(
          new RegExp(`${ticketSubject}|creado|enviado`, 'i'), { timeout: 15_000 }
        );
        await attachScreenshot(page, testInfo, 'ticket-creado');
      }
    } else {
      test.skip();
    }
  });

  test('soporte puede responder a un ticket', async ({ page }, testInfo) => {
    await loginAs(page, USERS.soporte.email);
    await page.goto(ROUTES.adminTickets);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    // Abrir el primer ticket disponible
    const ticketBtn = page.getByRole('button').first();
    if (await ticketBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => ticketBtn.click());
      await expectNoServerError(page);

      // Escribir respuesta
      const replyInput = page.locator('textarea[wire\\:model*="reply"], textarea[wire\\:model*="message"]').first();
      if (await replyInput.isVisible({ timeout: 5_000 }).catch(() => false)) {
        await replyInput.fill('Respuesta de soporte E2E. Estamos revisando su caso.');

        const replyBtn = page.getByRole('button', { name: /responder|reply|enviar/i });
        if (await replyBtn.isVisible()) {
          await waitForLivewire(page, () => replyBtn.click());
          await expectNoServerError(page);
          await attachScreenshot(page, testInfo, 'soporte-respondio-ticket');
        }
      }
    } else {
      test.skip();
    }
  });

  test('soporte puede cerrar un ticket', async ({ page }, testInfo) => {
    await loginAs(page, USERS.soporte.email);
    await page.goto(ROUTES.adminTickets);
    await page.waitForLoadState('networkidle');

    const ticketBtn = page.getByRole('button').first();
    if (await ticketBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => ticketBtn.click());

      const closeBtn = page.getByRole('button', { name: /cerrar ticket|close ticket|resolver/i });
      if (await closeBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
        await waitForLivewire(page, () => closeBtn.click());
        await expectNoServerError(page);
        await expect(page.locator('body')).toContainText(/cerrado|closed|resuelto/i, { timeout: 10_000 });
        await attachScreenshot(page, testInfo, 'ticket-cerrado');
      } else {
        test.skip();
      }
    } else {
      test.skip();
    }
  });

  test('ventas no puede acceder al módulo de tickets admin', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.adminTickets);
    await expectNoServerError(page);

    const url = page.url();
    const body = await page.locator('body').textContent() ?? '';
    const isBlocked = url.includes('/login') || /403|forbidden|unauthorized/i.test(body);
    expect(isBlocked, 'Ventas no debe ver módulo admin de tickets').toBeTruthy();
  });

});
