-- restore_full_schema.sql

BEGIN;

-- 1. Create activity_logs
CREATE TABLE IF NOT EXISTS public.activity_logs (
    log_id SERIAL PRIMARY KEY,
    table_name character varying(50) NOT NULL,
    record_id integer,
    action character varying(10) NOT NULL,
    old_data jsonb,
    new_data jsonb,
    performed_by integer,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- 2. Create security_questions
CREATE TABLE IF NOT EXISTS public.security_questions (
    question_id SERIAL PRIMARY KEY,
    question_text text NOT NULL,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- 3. Create user_security_answers
CREATE TABLE IF NOT EXISTS public.user_security_answers (
    answer_id SERIAL PRIMARY KEY,
    user_id integer NOT NULL,
    question_id integer NOT NULL,
    answer_hash character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- 4. Create system_activity_logs
CREATE TABLE IF NOT EXISTS public.system_activity_logs (
    log_id SERIAL PRIMARY KEY,
    user_id integer,
    admin_id integer,
    actor_type character varying(50),
    action character varying(255) NOT NULL,
    category character varying(100),
    description text,
    table_affected character varying(100),
    record_identifier character varying(255),
    ip_address inet,
    user_agent text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT system_activity_logs_actor_type_check CHECK (((actor_type)::text = ANY ((ARRAY['user'::character varying, 'admin'::character varying, 'super_admin'::character varying])::text[])))
);

-- 5. Insert default security questions if empty
INSERT INTO public.security_questions (question_text)
SELECT q FROM (VALUES 
    ('What is your mother''s maiden name?'),
    ('What was the name of your first pet?'),
    ('What city were you born in?'),
    ('What is the name of your favorite movie?'),
    ('What was the name of your first elementary school?'),
    ('What is your favorite color?')
) AS t(q)
WHERE NOT EXISTS (SELECT 1 FROM public.security_questions);

COMMIT;
