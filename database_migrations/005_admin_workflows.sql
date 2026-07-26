-- Administrative workflow defaults for the fully connected RHU dashboard.

INSERT INTO permissions (name, description) VALUES
('manage_users', 'Create, update, deactivate, and assign user roles'),
('manage_staff', 'Create, update, deactivate, and remove staff records'),
('manage_residents', 'Create and update resident profiles'),
('manage_clinical_records', 'Manage consultations, maternal, vaccination, and surveillance records'),
('manage_inventory', 'Manage medicines and stock transactions'),
('manage_vital_statistics', 'Manage birth and death registrations'),
('manage_certificates', 'Approve, reject, and issue resident certificates'),
('manage_events', 'Publish, update, cancel, and confirm RHU events'),
('manage_reports', 'Generate and submit DOH reports'),
('manage_system', 'Manage settings, maintenance, and backups'),
('view_audit_logs', 'Review system audit history')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name IN ('RHU_ADMIN', 'SUPER_ADMIN');

CREATE INDEX IF NOT EXISTS idx_portal_event_scheduled_date ON portal_events (scheduled_date);
CREATE INDEX IF NOT EXISTS idx_notification_unread ON portal_notifications (is_read, created_at);

ALTER TABLE consultations
    ADD COLUMN IF NOT EXISTS consultation_status VARCHAR(30) NOT NULL DEFAULT 'Scheduled' AFTER consultation_notes;
