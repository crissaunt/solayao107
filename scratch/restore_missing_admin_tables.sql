-- scratch/restore_missing_admin_tables.sql
-- Restores tables dropped by the consolidation script that are still needed by the admin panel.

BEGIN;

-- 1. admin_activity_logs
CREATE TABLE IF NOT EXISTS public.admin_activity_logs (
    log_id SERIAL PRIMARY KEY,
    admin_id integer,
    username character varying(100),
    role character varying(50),
    action_type character varying(100) NOT NULL,
    table_name character varying(100),
    record_id character varying(100),
    old_data jsonb,
    new_data jsonb,
    ip_address inet,
    user_agent text,
    endpoint character varying(255),
    method character varying(10),
    status_code integer,
    execution_time_ms integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- 2. deletion_requests
CREATE TABLE IF NOT EXISTS public.deletion_requests (
    request_id SERIAL PRIMARY KEY,
    user_id integer NOT NULL,
    requested_by integer NOT NULL,
    reason text,
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    reviewed_by integer,
    review_notes text
);

-- 3. edit_requests
CREATE TABLE IF NOT EXISTS public.edit_requests (
    request_id SERIAL PRIMARY KEY,
    user_id integer,
    requested_by integer,
    requested_data jsonb,
    reason text NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    reviewed_by integer,
    review_notes text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

-- 4. login_attempts
CREATE TABLE IF NOT EXISTS public.login_attempts (
    attempt_id SERIAL PRIMARY KEY,
    user_id integer,
    username character varying(50),
    ip_address character varying(45),
    success boolean DEFAULT false,
    attempt_time timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- 5. password_reset_attempts
CREATE TABLE IF NOT EXISTS public.password_reset_attempts (
    attempt_id SERIAL PRIMARY KEY,
    email character varying(255),
    ip_address character varying(45),
    attempt_time timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    attempt_type character varying(50)
);

-- 6. password_reset_logs
CREATE TABLE IF NOT EXISTS public.password_reset_logs (
    log_id SERIAL PRIMARY KEY,
    user_id integer,
    email character varying(255),
    attempt_type character varying(50),
    ip_address character varying(45),
    user_agent text,
    attempt_time timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    success boolean,
    lockout_until timestamp without time zone,
    details text
);

-- 7. password_reset_sessions
CREATE TABLE IF NOT EXISTS public.password_reset_sessions (
    session_id character varying(64) PRIMARY KEY,
    user_id integer,
    email character varying(255),
    otp_hash character varying(255),
    otp_expiry timestamp without time zone,
    otp_attempts integer DEFAULT 0,
    answer_attempts integer DEFAULT 0,
    current_step character varying(20) DEFAULT 'email'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- 8. activation_requests
CREATE TABLE IF NOT EXISTS public.activation_requests (
    request_id SERIAL PRIMARY KEY,
    user_id integer NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    reviewed_by integer,
    review_notes text
);

-- 9. admin_login_attempts
CREATE TABLE IF NOT EXISTS public.admin_login_attempts (
    attempt_id SERIAL PRIMARY KEY,
    username character varying(100),
    ip_address inet,
    success boolean,
    failure_reason character varying(255),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

COMMIT;
