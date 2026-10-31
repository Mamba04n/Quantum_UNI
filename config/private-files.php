<?php

return [
    /*
     * Public by design: assets published by an authorized content manager.
     * User submissions under storefront/uploads are intentionally excluded.
     */
    'public_prefixes' => [
        'storefront/products/',
        'storefront/blog/',
        'storefront/catalogs/',
    ],

    /*
     * Every entry is [table, column, classification]. Paths remain relative;
     * the filesystem disk is an application concern and is never user input.
     */
    'resources' => [
        ['quotation_requests', 'evidence_attachment_path', 'private'],
        ['quotation_requests', 'rfq_pdf_path', 'private'],
        ['quotation_requests', 'client_offer_pdf_path', 'private'],
        ['quotation_request_items', 'image_path', 'private'],
        ['tickets', 'attachment_path', 'private'],
        ['ticket_messages', 'attachment_path', 'private'],
        ['stock_moves', 'attachment_path', 'private'],
        ['stock_moves', 'receipt_document_path', 'private'],
        ['stock_move_attachments', 'file_path', 'private'],
        ['supplier_bids', 'proforma_path', 'private'],
        ['supplier_bid_responses', 'attachment_path', 'private'],
        ['supplier_bid_responses', 'supplier_document_path', 'private'],
        ['client_offer_versions', 'pdf_path', 'private'],
        ['client_offer_change_requests', 'attachment_path', 'private'],
        ['purchase_receipts', 'document_path', 'private'],
        ['order_tracker_attachments', 'file_path', 'private'],
        ['order_status_documents', 'file_path', 'private'],
        ['purchase_order_documents', 'pdf_path', 'private'],
        ['supplier_rfq_documents', 'pdf_path', 'private'],
        ['client_machines', 'image_path', 'private'],
        ['customer_machine_components', 'image_path', 'private'],
        ['employee_profiles', 'identity_document', 'private'],
        ['storefront_service_requests', 'imagen', 'private'],
        ['account_payables', 'support_document_path', 'financial_confidential'],
        ['account_receivables', 'support_document_path', 'financial_confidential'],
        ['invoices', 'invoice_file_path', 'financial_confidential'],
        ['payments', 'payment_evidence_path', 'financial_confidential'],
    ],
];
