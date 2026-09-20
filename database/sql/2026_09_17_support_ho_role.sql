-- Role support head office: permission sama dengan support,
-- tetapi cakupan team diatur oleh kode melalui role `support_ho`.
-- Jalankan pada database aplikasi setelah backup bila diperlukan.

INSERT INTO roles (name, guard_name, created_at, updated_at)
SELECT 'support_ho', 'web', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM roles
    WHERE name = 'support_ho' AND guard_name = 'web'
);

INSERT IGNORE INTO role_has_permissions (permission_id, role_id)
SELECT rhp.permission_id, target.id
FROM role_has_permissions AS rhp
JOIN roles AS source
    ON source.id = rhp.role_id
   AND source.name = 'support'
   AND source.guard_name = 'web'
JOIN roles AS target
    ON target.name = 'support_ho'
   AND target.guard_name = 'web';
