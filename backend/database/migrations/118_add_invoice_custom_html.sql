ALTER TABLE invoices ADD COLUMN custom_html MEDIUMTEXT NULL AFTER language;

-- Remove hardcoded 'Deutschland' default from clients table
ALTER TABLE clients ALTER COLUMN country DROP DEFAULT;
