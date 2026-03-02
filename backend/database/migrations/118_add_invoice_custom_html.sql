ALTER TABLE invoices ADD COLUMN custom_html MEDIUMTEXT NULL AFTER language;

-- Notice flags (NULL = auto-detect, 1 = force show, 0 = force hide)
ALTER TABLE invoices ADD COLUMN show_kleinunternehmer TINYINT(1) NULL AFTER custom_html;
ALTER TABLE invoices ADD COLUMN show_reverse_charge TINYINT(1) NULL AFTER show_kleinunternehmer;
ALTER TABLE invoices ADD COLUMN show_license_notice TINYINT(1) DEFAULT 0 AFTER show_reverse_charge;

-- Remove hardcoded 'Deutschland' default from clients table
ALTER TABLE clients ALTER COLUMN country DROP DEFAULT;
