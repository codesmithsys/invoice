-- PostgreSQL setup for Invoice app
-- Run as superuser. Database "invoice" must already exist (created by Render).

CREATE TABLE IF NOT EXISTS invoices (
    id SERIAL PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL,
    date_of_issue DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    from_name VARCHAR(255) NOT NULL DEFAULT '',
    from_address VARCHAR(500) NOT NULL DEFAULT '',
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_vat_no VARCHAR(100) NOT NULL DEFAULT '',
    from_phone VARCHAR(100) NOT NULL DEFAULT '',
    from_account_number VARCHAR(100) NOT NULL DEFAULT '',
    from_swift_bic VARCHAR(100) NOT NULL DEFAULT '',
    to_name VARCHAR(255) NOT NULL DEFAULT '',
    to_address VARCHAR(500) NOT NULL DEFAULT '',
    to_email VARCHAR(255) NOT NULL DEFAULT '',
    to_vat_no VARCHAR(100) NOT NULL DEFAULT '',
    to_phone VARCHAR(100) NOT NULL DEFAULT '',
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    net_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_type VARCHAR(20) NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gross_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    notes TEXT,
    payment_method VARCHAR(255) NOT NULL DEFAULT '',
    qr_data VARCHAR(500) NOT NULL DEFAULT '',
    qr_description VARCHAR(255) NOT NULL DEFAULT '',
    qr_show SMALLINT NOT NULL DEFAULT 0,
    logo_path VARCHAR(500) DEFAULT NULL,
    footer_text VARCHAR(255) NOT NULL DEFAULT 'Thank you for your business',
    share_token VARCHAR(64) DEFAULT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id SERIAL PRIMARY KEY,
    invoice_id INT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sort_order INT NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice_id ON invoice_items(invoice_id);
CREATE INDEX IF NOT EXISTS idx_invoices_share_token ON invoices(share_token);
