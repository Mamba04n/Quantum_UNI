import { defineConfig, devices } from '@playwright/test';

const baseURL    = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';
const chromePath = process.env.E2E_CHROME_PATH;
const channel    = process.env.CI ? undefined : 'chrome';

// Pasar SLOW_MO=600 para ver las acciones a cámara lenta (ms entre acciones)
const slowMo = process.env.SLOW_MO ? parseInt(process.env.SLOW_MO, 10) : 0;
// Pasar HEADED=1 para abrir el browser visible
const headed = process.env.HEADED === '1';

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup:    './tests/e2e/global-setup.ts',
  globalTeardown: './tests/e2e/global-teardown.ts',
  timeout: 120_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'playwright-report' }],
  ],
  use: {
    baseURL,
    trace:      'retain-on-failure',
    screenshot: 'only-on-failure',
    video:      'retain-on-failure',
    actionTimeout:     10_000,
    navigationTimeout: 30_000,
    extraHTTPHeaders: {
      'X-E2E-Test': '1',
    },
  },
  projects: [
    {
      name: 'chrome-desktop',
      use: {
        ...devices['Desktop Chrome'],
        channel,
        headless: !headed,
        launchOptions: {
          ...(chromePath ? { executablePath: chromePath } : {}),
          slowMo,
        },
        viewport: { width: 1440, height: 950 },
      },
    },
    {
      name: 'chrome-fullscreen',
      use: {
        channel,
        headless: !headed,
        launchOptions: {
          ...(chromePath ? { executablePath: chromePath } : {}),
          slowMo,
          args: ['--start-maximized'],
        },
        viewport: null,
      },
    },
    {
      name: 'chrome-mobile',
      use: {
        ...devices['Pixel 7'],
        channel,
        headless: !headed,
        launchOptions: {
          ...(chromePath ? { executablePath: chromePath } : {}),
          slowMo,
        },
      },
    },
  ],
});
