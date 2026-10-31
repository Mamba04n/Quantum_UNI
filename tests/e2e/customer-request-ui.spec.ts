import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { TRANSPARENT_PNG_1X1 } from './fixtures/image';

test.use({ video: 'off' });
test('solicitud: composición, captura, evidencia y guardado', async ({ page }, testInfo) => {
    test.skip(process.env.E2E_REQUEST_FIXTURES !== '1', 'Requires isolated customer-request-ui.php fixtures.');
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.url().includes('/livewire/') && response.status() >= 400) errors.push(`${response.status()} ${response.url()}`);
    });
    await loginAs(page, 'ux-request@quantum.test');
    await page.goto('/sales/solicitud-cliente');
    console.log('Altura del formulario con 1 producto:', await page.locator('form[wire\\:submit="save"]').evaluate(form => form.getBoundingClientRect().height));
    if (process.env.E2E_REQUEST_BASELINE === '1') return;
    const rows = page.locator('tr[wire\\:key^="customer-request-item-"]');
    const field = (name: string) => page.locator(`[wire\\:model="${name}"]`);
    await expect(rows).toHaveCount(1);
    await expect(page.getByAltText('Vista previa de la evidencia')).toHaveCount(0);
    for (const width of [1920, 1366, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: testInfo.outputPath(`solicitud-1-${width}.png`) });
    }
    await page.setViewportSize({ width: 1366, height: 900 });
    await page.getByLabel('Buscar cliente existente').fill('Cliente UX solicitud');
    await page.getByRole('button', { name: /Cliente UX solicitud/ }).click();
    await expect(field('quoteToEmail')).toHaveValue('cliente@ux.test');
    await expect(field('machinery')).toHaveValue('Excavadora');
    await expect(field('equipmentSerial')).toHaveValue('UX-320');
    await page.getByLabel('Maquinaria registrada del cliente').selectOption('');
    await expect(field('machinery')).toHaveValue('');
    await page.getByLabel('Vincular cliente existente', { exact: true }).selectOption('');
    await expect(field('quoteToName')).toHaveValue('');
    for (const channel of ['gmail', 'llamada', 'presencial', 'otro', 'whatsapp']) {
        await field('sourceChannel').selectOption(channel);
        await expect(field('sourceChannel')).toHaveValue(channel);
    }
    const productSelect = page.getByLabel('Producto / origen, producto 1', { exact: true });
    await productSelect.selectOption(await productSelect.locator('option').filter({ hasText: 'Filtro UX' }).getAttribute('value') as string);
    await expect(field('items.0.part_name')).toHaveValue('Filtro UX');
    await page.getByLabel('Producto / origen, producto 1', { exact: true }).selectOption('');
    await page.getByRole('button', { name: 'Agregar producto', exact: true }).click();
    await expect(rows).toHaveCount(2);
    await field('items.1.part_name').fill('Conservar segunda fila');
    await page.getByRole('button', { name: 'Eliminar producto 1', exact: true }).click();
    await expect(rows).toHaveCount(1);
    await expect(field('items.0.part_name')).toHaveValue('Conservar segunda fila');
    await page.reload();
    for (const count of [5, 20, 60]) {
        await page.getByRole('button', { name: 'Carga Masiva (Excel / Texto)', exact: true }).click();
        await page.locator('[wire\\:click="setBulkMode(\'text\')"]').click();
        await page.locator('textarea[wire\\:model\\.live\\.debounce\\.300ms="bulkPasteText"]').fill(
            Array.from({ length: count }, (_, i) => `UX-${i + 1}\tPieza ${i + 1}\t1\tPZA\tCAT`).join('\n')
        );
        await page.getByRole('button', { name: new RegExp(`Insertar ${count} Partidas`) }).click();
        await expect(rows).toHaveCount(count);
        const height = await rows.first().evaluate(row => row.getBoundingClientRect().height);
        expect(height).toBeLessThanOrEqual(64);
        for (const width of [1920, 1366, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await page.getByRole('region', { name: 'Productos solicitados: tabla editable' }).scrollIntoViewIfNeeded();
            await page.screenshot({ path: testInfo.outputPath(`solicitud-${count}-${width}.png`) });
        }
        await page.reload();
        await page.setViewportSize({ width: 1366, height: 900 });
    }
    await page.getByRole('button', { name: 'Guardar solicitud', exact: true }).click();
    await expect(page.locator('#request-item-0-part_name-error')).toBeVisible();
    await field('quoteToName').fill('Solicitud UI de prueba');
    await field('machinery').fill('Excavadora manual');
    await field('description').fill('Solicitud de prueba de composición y guardado.');
    await field('items.0.part_name').fill('Pieza manual');
    await field('items.0.part_code').fill('UX-MANUAL');
    await page.getByLabel('Imagen o archivo de prueba').setInputFiles({ name: 'evidencia.png', mimeType: 'image/png', buffer: TRANSPARENT_PNG_1X1 });
    await expect(page.getByAltText('Vista previa de la evidencia')).toBeVisible();
    await page.getByLabel('Imagen o archivo de prueba').setInputFiles('tests/e2e/fixtures/evidence.pdf');
    await expect(page.getByText('evidence.pdf', { exact: true })).toBeVisible();
    await expect(page.getByAltText('Vista previa de la evidencia')).toHaveCount(0);
    await page.getByRole('button', { name: 'Guardar solicitud', exact: true }).click();
    await expect(page).toHaveURL(/fase-cotizacion/);
    await page.goto('/sales/solicitud-cliente');
    await page.getByRole('link', { name: 'Cancelar', exact: true }).click();
    await expect(page).toHaveURL(/fase-cotizacion/);
    await page.goto('/admin/users');
    for (const width of [1920, 1366, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await page.screenshot({ path: testInfo.outputPath(`referencia-usuarios-${width}.png`) });
    }
    expect(errors).toEqual([]);
});
