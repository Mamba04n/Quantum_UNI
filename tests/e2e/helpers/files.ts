/**
 * File upload helpers for Playwright E2E tests.
 * These functions upload files to Livewire file inputs using inline buffers
 * (no external files needed except for the existing evidence.pdf fixture).
 */
import { type Page } from '@playwright/test';
import { resolve } from 'node:path';
import { TRANSPARENT_PNG_1X1, FAKE_EXE_BUFFER } from '../fixtures/image.js';

// Use process.cwd() to locate fixtures relative to project root (compatible with both CJS and ESM)
const fixturesDir = resolve(process.cwd(), 'tests/e2e/fixtures');

/**
 * Uploads the shared evidence.pdf fixture to a file input.
 * Waits for the Livewire upload-file response.
 *
 * @param page - Playwright page
 * @param selector - CSS selector for the file input (default: first file input)
 */
export async function uploadPdf(
  page: Page,
  selector = 'input[type="file"]',
): Promise<void> {
  const uploadDone = page.waitForResponse(
    (r) => r.url().includes('/livewire/upload-file') && r.status() === 200,
    { timeout: 30_000 },
  );
  await page.locator(selector).setInputFiles(resolve(fixturesDir, 'evidence.pdf'));
  await uploadDone;

  // Also wait for the subsequent Livewire update that refreshes the component state
  await page
    .waitForResponse(
      (r) => r.url().includes('/livewire/update') && r.status() === 200,
      { timeout: 15_000 },
    )
    .catch(() => undefined); // Some components don't fire update after upload
}

/**
 * Uploads an inline 1×1 PNG to a file input.
 *
 * @param page - Playwright page
 * @param selector - CSS selector for the file input
 * @param filename - filename to use (default: 'test-image.png')
 */
export async function uploadImage(
  page: Page,
  selector = 'input[type="file"]',
  filename = 'test-image.png',
): Promise<void> {
  const uploadDone = page.waitForResponse(
    (r) => r.url().includes('/livewire/upload-file') && r.status() === 200,
    { timeout: 30_000 },
  );
  await page.locator(selector).setInputFiles({
    name: filename,
    mimeType: 'image/png',
    buffer: TRANSPARENT_PNG_1X1,
  });
  await uploadDone;
}

/**
 * Attempts to upload a fake .exe file to a file input.
 * Use for NEGATIVE tests — assert that the app rejects this file.
 *
 * @param page - Playwright page
 * @param selector - CSS selector for the file input
 */
export async function uploadInvalidFile(
  page: Page,
  selector = 'input[type="file"]',
): Promise<void> {
  // We do NOT wait for a successful upload-file response here.
  // The test should assert that the file was rejected.
  await page.locator(selector).setInputFiles({
    name: 'malware.exe',
    mimeType: 'application/octet-stream',
    buffer: FAKE_EXE_BUFFER,
  });

  // Give the app a moment to validate and show error message
  await page
    .waitForResponse(
      (r) => (r.url().includes('/livewire/upload-file') || r.url().includes('/livewire/update')) && r.status() === 200,
      { timeout: 5_000 },
    )
    .catch(() => undefined);
}

/**
 * Uploads a file that exceeds the typical max size limit (simulated via a large buffer).
 * Uses a 11MB buffer to exceed common 10MB limits.
 *
 * @param page - Playwright page
 * @param selector - CSS selector for the file input
 */
export async function uploadOversizedFile(
  page: Page,
  selector = 'input[type="file"]',
): Promise<void> {
  const elevenMB = Buffer.alloc(11 * 1024 * 1024, 0x00);
  await page.locator(selector).setInputFiles({
    name: 'oversized-document.pdf',
    mimeType: 'application/pdf',
    buffer: elevenMB,
  });

  await page
    .waitForResponse(
      (r) => r.url().includes('/livewire') && r.status() >= 200,
      { timeout: 10_000 },
    )
    .catch(() => undefined);
}
