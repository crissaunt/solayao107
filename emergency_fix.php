<?php
// emergency_fix.php - Run this from your browser at http://localhost:8000/emergency_fix.php
require __DIR__ . '/php/db_connection.php';

echo "<pre>\n";

try {
    // Step 0: Ensure roles table exists
    echo "Step 0: Creating roles table if not exists...\n";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS public.roles (
            role_id integer NOT NULL PRIMARY KEY,
            role_name character varying(50) NOT NULL UNIQUE,
            role_description text,
            is_active boolean DEFAULT true,
            created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $conn->exec("
        INSERT INTO public.roles (role_id, role_name, role_description)
        VALUES 
            (1, 'Super Admin', 'Full system access'),
            (2, 'Admin', 'Administrative access'),
            (3, 'User', 'Standard user access')
        ON CONFLICT (role_id) DO NOTHING
    ");
    echo "  ✓ roles table OK\n";

    // Step 1: Create sequence
    echo "Step 1: Creating users_user_id_seq...\n";
    $conn->exec("
        CREATE SEQUENCE IF NOT EXISTS public.users_user_id_seq
            AS integer START WITH 1000 INCREMENT BY 1
            NO MINVALUE NO MAXVALUE CACHE 1
    ");
    echo "  ✓ Sequence OK\n";

    // Step 2: Create users table
    echo "Step 2: Creating users table...\n";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS public.users (
            user_id integer NOT NULL PRIMARY KEY DEFAULT nextval('public.users_user_id_seq'::regclass),
            id_number character varying(20),
            email character varying(100),
            contact_number character varying(20),
            username character varying(50),
            first_name character varying(50),
            middle_name character varying(50),
            last_name character varying(50),
            extension_name character varying(10),
            birthday date,
            age integer,
            sex character varying(10),
            password character varying(255),
            street_purok character varying(100),
            barangay character varying(100),
            city_municipal character varying(100),
            province character varying(100),
            country character varying(100),
            zipcode character varying(10),
            created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
            reset_token character varying(255),
            reset_token_expiry timestamp without time zone,
            last_reset_attempt timestamp without time zone,
            role_id integer DEFAULT 3 REFERENCES public.roles(role_id),
            is_active boolean DEFAULT true,
            updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
            last_login timestamp without time zone,
            permissions jsonb DEFAULT '[]'::jsonb,
            created_by integer,
            updated_by integer
        )
    ");
    echo "  ✓ users table created\n";

    // Step 3: Migrate from admin_users_backup if it exists
    echo "Step 3: Checking for admin_users_backup table...\n";
    $check = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name='admin_users_backup'")->fetchColumn();
    if ($check > 0) {
        echo "  Found admin_users_backup — migrating...\n";
        $conn->exec("
            INSERT INTO public.users (
                user_id, username, email, password, first_name, last_name,
                role_id, is_active, created_at, updated_at
            )
            SELECT 
                admin_id, username, email, password_hash, first_name, last_name,
                CASE 
                    WHEN role = 'super_admin' THEN 1 
                    WHEN role = 'admin' THEN 2 
                    ELSE 3 
                END,
                is_active, created_at, updated_at
            FROM public.admin_users_backup
            ON CONFLICT (user_id) DO NOTHING
        ");
        // Sync sequence
        $maxId = $conn->query("SELECT COALESCE(MAX(user_id), 999) FROM public.users")->fetchColumn();
        $conn->exec("SELECT setval('public.users_user_id_seq', $maxId)");
        echo "  ✓ Migration complete. Sequence set to $maxId\n";
    } else {
        echo "  No admin_users_backup found — skipping migration.\n";
        echo "  WARNING: You will need to create an admin account manually!\n";
    }

    // Step 4: Verify
    echo "\nStep 4: Verification...\n";
    $count = $conn->query("SELECT COUNT(*) FROM public.users")->fetchColumn();
    echo "  ✓ users table has $count row(s)\n";

    $tables = $conn->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nAll tables in public schema:\n";
    foreach ($tables as $t) {
        echo "  - $t\n";
    }

    echo "\n✅ Fix complete! Try logging in now.\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
