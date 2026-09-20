-- Permission widget dashboard support global untuk role owner.
-- Dibuat oleh `php artisan shield:generate --all --ignore-existing-policies --panel=admin`.

INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'view_owner_overview', 'web', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM permissions
    WHERE name = 'view_owner_overview' AND guard_name = 'web'
);

INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'view_owner_open_outstanding', 'web', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM permissions
    WHERE name = 'view_owner_open_outstanding' AND guard_name = 'web'
);

INSERT IGNORE INTO role_has_permissions (permission_id, role_id)
SELECT permissions.id, roles.id
FROM permissions
JOIN roles ON roles.name = 'owner' AND roles.guard_name = 'web'
WHERE permissions.name IN ('view_owner_overview', 'view_owner_open_outstanding')
  AND permissions.guard_name = 'web';
