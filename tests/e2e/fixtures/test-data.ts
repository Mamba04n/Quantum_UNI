/**
 * Canonical test data for Quantum ERP E2E suite.
 * These are the "golden" records used consistently across all spec files.
 * Dynamic data (unique per test) is generated with Date.now() in each spec.
 */

export const USERS = {
  admin:    { email: 'admin@quantum.test',    role: 'admin' },
  ventas:   { email: 'ventas@quantum.test',   role: 'ventas' },
  compras:  { email: 'compras@quantum.test',  role: 'compras' },
  finanzas: { email: 'finanzas@quantum.test', role: 'agente_finanzas' },
  bodega:   { email: 'bodega@quantum.test',   role: 'bodega' },
  soporte:  { email: 'soporte@quantum.test',  role: 'soporte' },
  cliente:  { email: 'cliente.test@quantum.test', role: 'cliente_tienda' },
} as const;

export type UserKey = keyof typeof USERS;

export const PASSWORD = process.env.E2E_PASSWORD ?? 'Password123!';

// ── Cliente base ──────────────────────────────────────────────────────────────
export const CUSTOMER_AGRO = {
  name:        'Agroservicios San Miguel',
  contactName: 'Carlos Ramírez',
  phone:       '+505 8888 1122',
  email:       'carlos@agrosanmiguel.com',
  address:     'Rivas, Nicaragua',
  department:  'Rivas',
  type:        'Agrícola',
  country:     'Nicaragua',
} as const;

// ── Maquinaria ────────────────────────────────────────────────────────────────
export const MACHINE_TRACTOR = {
  type:    'Tractor',
  brand:   'John Deere',
  model:   '5075E',
  serial:  'JD5075-2026-NI',
  hours:   '3250',
} as const;

// ── Productos ─────────────────────────────────────────────────────────────────
export const PRODUCT_FILTER = {
  name:       'Filtro hidráulico',
  partNumber: 'HF-7890',
  brand:      'Donaldson',
  model:      'HD-45',
  quantity:   2,
  unitPrice:  120,     // costo proveedor USD (ACSIS)
  clientPrice: 180,    // precio cliente USD
} as const;

export const PRODUCT_ALTERNATOR = {
  name:       'Alternador',
  partNumber: 'ALT-220V',
  brand:      'Bosch',
  model:      'BX-200',
  quantity:   1,
  unitPrice:  240,     // costo proveedor USD (KB)
  clientPrice: 340,    // precio cliente USD
} as const;

export const TAX_PERCENT = 15; // IVA Nicaragua 15%

// ── Proveedores ───────────────────────────────────────────────────────────────
export const SUPPLIER_ACSIS = {
  name:          'ACSIS Parts',
  country:       'Estados Unidos',
  contactName:   'Michael Smith',
  email:         'sales@acsisparts.com',
  phone:         '+1 305 555 8822',
  paymentMethod: 'Transferencia internacional',
} as const;

export const SUPPLIER_KB = {
  name:          'KB Repuestos',
  country:       'Corea del Sur',
  contactName:   'Kim Park',
  email:         'sales@kbparts.kr',
  phone:         '+82 10 5555 9090',
  paymentMethod: 'Pago online',
} as const;

// ── Supplier bid prices ───────────────────────────────────────────────────────
/** First ACSIS quote for Filtro */
export const BID_ACSIS_FILTER_V1 = { unitCost: '120.00', deliveryDays: '15' };
/** Second ACSIS quote for Filtro (different price — must NOT overwrite v1) */
export const BID_ACSIS_FILTER_V2 = { unitCost: '135.00', deliveryDays: '20' };
/** KB quote for Alternador */
export const BID_KB_ALTERNATOR   = { unitCost: '240.00', deliveryDays: '25' };

// ── Rutas ERP ─────────────────────────────────────────────────────────────────
export const ROUTES = {
  dashboard:          '/dashboard',
  login:              '/login',
  clients:            '/clients',
  solicitudCliente:   '/sales/solicitud-cliente',
  quotationsRequest:  '/sales/quotations/manage/request',
  quotationsBid:      '/sales/quotations/manage/bid',
  quotationsOffer:    '/sales/quotations/manage/offer',
  quotationsApproval: '/sales/quotations/manage/approval',
  tracker:            '/sales/tracker',
  adminUsers:         '/admin/users',
  adminActivity:      '/admin/activity',
  adminApprovals:     '/admin/approvals',
  adminOfferReviews:  '/admin/offer-reviews',
  adminSuppliers:     '/admin/proveedores',
  adminKardex:        '/admin/kardex',
  adminKpis:          '/admin/kpis',
  adminTickets:       '/admin/tickets',
  financeCxp:         '/finance/cxp',
  financeCxc:         '/finance/cxc',
  financePayments:    '/finance/payments',
  financeInvoices:    '/finance/invoices',
  inventoryProducts:  '/inventory/products',
  inventoryMoves:     '/inventory/moves',
  warehouseIncoming:  '/warehouse/incoming-orders',
  customerPortal:     '/mi-portal',
  customerTickets:    '/cliente/tickets',
  webBlog:            '/web/blog',
  webCatalogos:       '/web/catalogos',
} as const;

// ── Estados humanos esperados (no debe haber términos técnicos) ───────────────
export const HUMAN_STATUS_MAP: Record<string, string> = {
  pending_purchase:        'Pendiente de compra',
  lista_para_licitar:      'Lista para licitación',
  en_licitacion:           'En licitación',
  en_revision_admin:       'En revisión admin',
  oferta_enviada:          'Oferta enviada',
  cliente_acepto:          'Cliente aceptó',
  en_transito_aduanas:     'En tránsito / Aduanas',
  solicitado_proveedor:    'Pedido a proveedor',
  recibido_bodega:         'Recibido en bodega',
  entregado:               'Entregado',
  cancelado:               'Cancelado',
};
