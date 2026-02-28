ALTER TABLE contracts
  ADD COLUMN share_token VARCHAR(64) DEFAULT NULL AFTER notes,
  ADD COLUMN share_password VARCHAR(255) DEFAULT NULL AFTER share_token,
  ADD COLUMN share_expires_at DATETIME DEFAULT NULL AFTER share_password,
  ADD COLUMN share_view_count INT DEFAULT 0 AFTER share_expires_at,
  ADD UNIQUE INDEX idx_contracts_share_token (share_token);
