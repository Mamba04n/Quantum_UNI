import { existsSync, readFileSync, rmSync } from 'fs';
import { resolve } from 'path';

const manageWebServer = process.env.E2E_MANAGE_SERVER !== '0';
const pidFile = resolve(process.cwd(), 'storage/framework/e2e-server.pid');

async function globalTeardown() {
  if (!manageWebServer || !existsSync(pidFile)) {
    return;
  }

  const pid = Number(readFileSync(pidFile, 'utf8').trim());
  if (Number.isInteger(pid) && pid > 0) {
    try {
      process.kill(pid);
    } catch {
      // The process may already be gone; teardown should stay quiet.
    }
  }

  rmSync(pidFile, { force: true });
}

export default globalTeardown;
