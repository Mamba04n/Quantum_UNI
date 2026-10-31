/**
 * Global Setup para Playwright E2E.
 *
 * Si E2E_MANAGE_SERVER=0 (o el servidor ya está corriendo en 127.0.0.1:8000),
 * simplemente verifica que esté disponible y corre el seeder.
 *
 * El servidor administrado usa el router versionado de Laravel y registra su
 * PID exacto para que globalTeardown no deje procesos huérfanos.
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { request } from 'node:http';
import { dirname, resolve } from 'node:path';

const baseURL        = process.env.E2E_BASE_URL   ?? 'http://127.0.0.1:8000';
const serverURL      = new URL(baseURL);
const manageServer   = process.env.E2E_MANAGE_SERVER !== '0';
const skipSeeder     = process.env.E2E_SKIP_SEEDER === '1';
const pidFile        = resolve(process.cwd(), 'storage/framework/e2e-server.pid');

async function globalSetup() {
  // ── 1. Verificar que el servidor Laravel ya está corriendo ─────────────────
  const serverUp = await isServerReady();

  if (!serverUp) {
    if (manageServer) {
      // Arrancar artisan serve en background si se pide
      const { spawn } = await import('node:child_process');
      const server = spawn('php', [
        '-S',
        `${serverURL.hostname}:${serverURL.port || '8000'}`,
        'tests/e2e/laravel-router.php',
      ], {
        cwd:        process.cwd(),
        detached:   true,
        stdio:      'ignore',
        windowsHide: true,
      });
      server.unref();
      if (server.pid) {
        mkdirSync(dirname(pidFile), { recursive: true });
        writeFileSync(pidFile, String(server.pid));
      }
      console.log(`⚡ Arrancando servidor E2E en ${baseURL}...`);
      await waitForServer();
    } else {
      throw new Error(
        `⛔ El servidor no está disponible en ${baseURL}.\n` +
        `   Arranca primero: php artisan serve\n` +
        `   O define E2E_MANAGE_SERVER=1 para arrancarlo automáticamente.`
      );
    }
  } else {
    console.log(`✅ Servidor detectado en ${baseURL}`);
  }

  // ── 2. Correr el seeder E2E ────────────────────────────────────────────────
  if (!skipSeeder) {
    console.log('🌱 Ejecutando E2EVisualSeeder...');
    execFileSync(
      'php',
      ['artisan', 'db:seed', '--class=E2EVisualSeeder', '--no-interaction'],
      { cwd: process.cwd(), stdio: 'inherit' }
    );
  }
}

export default globalSetup;

// ── Helpers ──────────────────────────────────────────────────────────────────

async function waitForServer(maxMs = 30_000): Promise<void> {
  const startedAt = Date.now();
  while (Date.now() - startedAt < maxMs) {
    if (await isServerReady()) return;
    await new Promise((r) => setTimeout(r, 500));
  }
  throw new Error(`⛔ El servidor no arrancó en ${maxMs / 1000}s en ${baseURL}`);
}

function isServerReady(): Promise<boolean> {
  const url = new URL(baseURL);
  return new Promise((resolve) => {
    const req = request(
      { hostname: url.hostname, port: url.port || 80, path: '/', timeout: 2_000 },
      (res) => { res.resume(); resolve((res.statusCode ?? 500) < 500); }
    );
    req.on('error',   () => resolve(false));
    req.on('timeout', () => { req.destroy(); resolve(false); });
    req.end();
  });
}
