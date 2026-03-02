-- Notice flags and clients fix from migration 118 (failed due to multi-statement PDO issue)
ALTER TABLE invoices
    ADD COLUMN show_kleinunternehmer TINYINT(1) NULL AFTER custom_html,
    ADD COLUMN show_reverse_charge TINYINT(1) NULL AFTER show_kleinunternehmer,
    ADD COLUMN show_license_notice TINYINT(1) DEFAULT 0 AFTER show_reverse_charge;
