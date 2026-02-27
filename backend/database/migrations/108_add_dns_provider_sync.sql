-- Migration: 108
-- Description: Add external ID columns for DNS provider sync (Cloudflare, etc.)

ALTER TABLE dns_domains ADD COLUMN external_zone_id VARCHAR(100) NULL AFTER provider_config;

ALTER TABLE dns_records ADD COLUMN external_id VARCHAR(100) NULL AFTER domain_id;
ALTER TABLE dns_records ADD INDEX idx_dns_records_external (external_id);
