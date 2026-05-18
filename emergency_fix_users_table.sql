-- EMERGENCY FIX: Recreate the 'users' table
-- Run this in pgAdmin Query Tool or psql

-- Step 0: Ensure 'roles' table exists first
CREATE TABLE IF NOT EXISTS public.roles (
    role_id integer NOT NULL PRIMARY KEY,
    role_name character varying(50) NOT NULL UNIQUE,
    role_description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO public.roles (role_id, role_name, role_description)
VALUES 
    (1, 'Super Admin', 'Full system access'),
    (2, 'Admin', 'Administrative access'),
    (3, 'User', 'Standard user access')
ON CONFLICT (role_id) DO NOTHING;

-- Step 1: Create sequence for user_id
CREATE SEQUENCE IF NOT EXISTS public.users_user_id_seq
    AS integer
    START WITH 1000
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Step 2: Create the 'users' table
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
);

-- Step 3: Migrate admin accounts from backup table (if it exists)
DO $$
BEGIN
    IF EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = 'public' AND table_name = 'admin_users_backup'
    ) THEN
        INSERT INTO public.users (
            user_id, username, email, password, first_name, last_name,
            role_id, is_active, created_at, updated_at
        )
        SELECT 
            admin_id,
            username,
            email,
            password_hash,
            first_name,
            last_name,
            CASE 
                WHEN role = 'super_admin' THEN 1 
                WHEN role = 'admin' THEN 2 
                ELSE 3 
            END,
            is_active,
            created_at,
            updated_at
        FROM public.admin_users_backup
        ON CONFLICT (user_id) DO NOTHING;

        -- Sync the sequence so new inserts don't conflict
        PERFORM setval(
            'public.users_user_id_seq',
            GREATEST((SELECT MAX(user_id) FROM public.users), 999)
        );

        RAISE NOTICE 'Migrated admin accounts from admin_users_backup to users table.';
    ELSE
        RAISE NOTICE 'admin_users_backup not found — no migration needed.';
    END IF;
END $$;

-- Step 4: Verify
SELECT 
    'users table exists' AS status,
    COUNT(*) AS row_count 
FROM public.users;
