-- Add 'custom' contract type for user-defined contracts with free-text content
ALTER TABLE contract_templates
  MODIFY COLUMN contract_type ENUM('license','development','saas','maintenance','nda','custom') NOT NULL;

ALTER TABLE contracts
  MODIFY COLUMN contract_type ENUM('license','development','saas','maintenance','nda','custom') NOT NULL;
