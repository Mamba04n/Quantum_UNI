/**
 * Data management helpers for E2E tests.
 * Use these in beforeEach / afterEach hooks to set up and clean up test data
 * via the Laravel testing endpoints.
 */
import { type APIRequestContext } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

/**
 * Calls a Laravel testing endpoint and returns the parsed JSON response.
 * Throws if the endpoint returns a non-2xx status.
 */
async function callTestingEndpoint(
  request: APIRequestContext,
  path: string,
  body: Record<string, unknown> = {},
): Promise<unknown> {
  const response = await request.post(`${baseURL}${path}`, {
    data: body,
    headers: {
      'Accept': 'application/json',
      'X-E2E-Test': '1',
    },
  });

  if (!response.ok()) {
    const text = await response.text();
    throw new Error(
      `Testing endpoint ${path} returned ${response.status()}: ${text.slice(0, 500)}`,
    );
  }

  return response.json();
}

/**
 * Triggers the full E2E seeder (users, base supplier, base customer).
 * Call this in globalSetup or test.beforeAll.
 */
export async function setupE2EData(request: APIRequestContext): Promise<void> {
  await callTestingEndpoint(request, '/testing/e2e/setup');
}

/**
 * Removes all records created by E2E tests (prefixed with "E2E_" or tagged).
 * Call this in afterAll or afterEach to keep the database clean.
 */
export async function cleanupE2EData(request: APIRequestContext): Promise<void> {
  await callTestingEndpoint(request, '/testing/e2e/cleanup');
}

/**
 * Creates a customer entity via the testing endpoint and returns its ID.
 */
export async function createCustomer(
  request: APIRequestContext,
  data: {
    name: string;
    contactName?: string;
    email?: string;
    phone?: string;
    address?: string;
    country?: string;
  },
): Promise<{ id: number; name: string }> {
  return callTestingEndpoint(request, '/testing/e2e/create-customer', data) as Promise<{
    id: number;
    name: string;
  }>;
}

/**
 * Creates a supplier entity via the testing endpoint and returns its ID.
 */
export async function createSupplier(
  request: APIRequestContext,
  data: {
    name: string;
    country?: string;
    contactName?: string;
    email?: string;
    phone?: string;
  },
): Promise<{ id: number; name: string }> {
  return callTestingEndpoint(request, '/testing/e2e/create-supplier', data) as Promise<{
    id: number;
    name: string;
  }>;
}

/**
 * Creates a product via the testing endpoint and returns its ID.
 */
export async function createProduct(
  request: APIRequestContext,
  data: {
    name: string;
    partNumber?: string;
    brand?: string;
    model?: string;
  },
): Promise<{ id: number; name: string }> {
  return callTestingEndpoint(request, '/testing/e2e/create-product', data) as Promise<{
    id: number;
    name: string;
  }>;
}

/**
 * Returns login credentials for a given role (useful for dynamic user lookup).
 */
export async function getLoginData(
  request: APIRequestContext,
  role: string,
): Promise<{ email: string; password: string }> {
  return callTestingEndpoint(request, '/testing/e2e/login-user-data', { role }) as Promise<{
    email: string;
    password: string;
  }>;
}

/**
 * Generates a unique string suffix based on current timestamp.
 * Use this to make test data unique per test run.
 *
 * @example
 * const uid = uniqueSuffix();
 * const clientName = `Cliente E2E ${uid}`;
 */
export function uniqueSuffix(): string {
  return Date.now().toString().slice(-8);
}

/**
 * Generates a unique email for a test entity.
 *
 * @example
 * const email = uniqueEmail('cliente');
 * // → "cliente.17832841@e2e.test"
 */
export function uniqueEmail(prefix: string): string {
  return `${prefix}.${uniqueSuffix()}@e2e.test`;
}
