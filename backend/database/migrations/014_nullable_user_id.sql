-- Allow kiosk/mobile walk-in requests that have no registered user
ALTER TABLE document_requests DROP FOREIGN KEY fk_dr_user;
ALTER TABLE document_requests MODIFY COLUMN user_id INT UNSIGNED NULL;
ALTER TABLE document_requests ADD CONSTRAINT fk_dr_user_nullable FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
