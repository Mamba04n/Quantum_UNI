import { type APIRequestContext } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

async function post<T>(
  request: APIRequestContext,
  path: string,
  body: Record<string, unknown> = {},
): Promise<T> {
  const response = await request.post(`${baseURL}${path}`, {
    data: body,
    timeout: 60_000,
    headers: {
      Accept: 'application/json',
      'X-E2E-Test': '1',
    },
  });

  if (!response.ok()) {
    const text = await response.text();
    throw new Error(`${path} returned ${response.status()}: ${text.slice(0, 800)}`);
  }

  return response.json() as Promise<T>;
}

export interface CustomerFixture {
  id: number;
  name: string;
  email: string;
}

export interface CommercialFixture {
  quotationId: number;
  reference: string;
  customerId: number;
  supplierId: number;
  itemIds: number[];
  reviewId?: number;
  approvalId?: number;
  trackerId?: number;
  trackingCode?: string;
}

export interface OrderTrackerFixture {
  orderId: number;
  orderStatusId: number;
  trackerId: number;
  trackingCode: string;
  itemIds: number[];
  orderStatusUrl: string;
  packingListDocumentId?: number;
  packingListUrl?: string;
}

export async function setupBase(request: APIRequestContext): Promise<void> {
  await post(request, '/testing/e2e/setup-base');
}

export async function cleanupE2E(request: APIRequestContext): Promise<void> {
  await post(request, '/testing/e2e/cleanup');
}

export async function createCustomerFixture(
  request: APIRequestContext,
  data: { name?: string; email?: string; status?: 'active' | 'inactive' } = {},
): Promise<CustomerFixture> {
  return post<CustomerFixture>(request, '/testing/e2e/create-customer', data);
}

export async function createClientOfferReadyForReview(request: APIRequestContext): Promise<CommercialFixture> {
  return post<CommercialFixture>(request, '/testing/e2e/create-client-offer-ready-for-review');
}

export async function createClientOfferPendingReview(request: APIRequestContext): Promise<CommercialFixture> {
  return post<CommercialFixture>(request, '/testing/e2e/create-client-offer-ready-for-review', {
    pendingReview: true,
  });
}

export async function createAdminApprovalPending(request: APIRequestContext): Promise<CommercialFixture> {
  return post<CommercialFixture>(request, '/testing/e2e/create-admin-approval-pending');
}

export async function createOrderTrackerWithStatus(request: APIRequestContext): Promise<OrderTrackerFixture> {
  return post<OrderTrackerFixture>(request, '/testing/e2e/create-order-tracker-with-status');
}

export async function createOrderStatusPartialDelivery(request: APIRequestContext): Promise<OrderTrackerFixture> {
  return post<OrderTrackerFixture>(request, '/testing/e2e/create-order-status-partial-delivery');
}

export async function createOrderStatusPackingListFlow(request: APIRequestContext): Promise<OrderTrackerFixture> {
  return post<OrderTrackerFixture>(request, '/testing/e2e/create-order-status-packing-list-flow');
}
