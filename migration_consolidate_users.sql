-- migration_consolidate_users.sql
-- Run this script to consolidate the users and admin_users tables.

BEGIN;

-- 1. Enhance users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions JSONB DEFAULT '[]';
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_by INTEGER REFERENCES users(user_id);
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_by INTEGER REFERENCES users(user_id);
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- 2. Migrate roles if necessary (ensure Admin and Super Admin roles exist)
-- Assuming role_id 1 = Super Admin, 2 = Admin, 3 = User
-- Based on previous analysis, these roles already exist in the roles table.

-- 3. Migrate admins from admin_users to users
-- Note: We map admin_users.role to users.role_id
INSERT INTO users (
    username, 
    email, 
    password, 
    first_name, 
    last_name, 
    role_id, 
    permissions, 
    is_active, 
    created_at, 
    last_login
)
SELECT 
    au.username, 
    au.email, 
    au.password_hash, 
    au.first_name, 
    au.last_name, 
    CASE 
        WHEN au.role = 'super_admin' THEN 1 
        WHEN au.role = 'admin' THEN 2 
        ELSE 3 
    END AS role_id,
    au.permissions::JSONB,
    au.is_active,
    au.created_at,
    au.last_login
FROM admin_users au
ON CONFLICT (username) DO UPDATE SET
    role_id = EXCLUDED.role_id,
    permissions = EXCLUDED.permissions,
    updated_at = NOW();

-- 4. Backup the old table (optional but recommended)
ALTER TABLE admin_users RENAME TO admin_users_backup;

COMMIT;
