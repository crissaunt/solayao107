-- PostgreSQL database dump generated via PHP
-- Generated at: 2026-05-18 13:43:58

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;

DROP SEQUENCE IF EXISTS public.activation_requests_request_id_seq CASCADE;
CREATE SEQUENCE public.activation_requests_request_id_seq;

DROP SEQUENCE IF EXISTS public.activity_logs_log_id_seq CASCADE;
CREATE SEQUENCE public.activity_logs_log_id_seq;

DROP SEQUENCE IF EXISTS public.admin_activity_logs_log_id_seq CASCADE;
CREATE SEQUENCE public.admin_activity_logs_log_id_seq;

DROP SEQUENCE IF EXISTS public.deletion_requests_request_id_seq CASCADE;
CREATE SEQUENCE public.deletion_requests_request_id_seq;

DROP SEQUENCE IF EXISTS public.edit_requests_request_id_seq CASCADE;
CREATE SEQUENCE public.edit_requests_request_id_seq;

DROP SEQUENCE IF EXISTS public.login_attempts_attempt_id_seq CASCADE;
CREATE SEQUENCE public.login_attempts_attempt_id_seq;

DROP SEQUENCE IF EXISTS public.password_reset_logs_log_id_seq CASCADE;
CREATE SEQUENCE public.password_reset_logs_log_id_seq;

DROP SEQUENCE IF EXISTS public.security_questions_question_id_seq CASCADE;
CREATE SEQUENCE public.security_questions_question_id_seq;

DROP SEQUENCE IF EXISTS public.system_activity_logs_log_id_seq CASCADE;
CREATE SEQUENCE public.system_activity_logs_log_id_seq;

DROP SEQUENCE IF EXISTS public.user_security_answers_answer_id_seq CASCADE;
CREATE SEQUENCE public.user_security_answers_answer_id_seq;

DROP SEQUENCE IF EXISTS public.users_user_id_seq CASCADE;
CREATE SEQUENCE public.users_user_id_seq;

DROP FUNCTION IF EXISTS public.set_default_user_permissions CASCADE;
CREATE OR REPLACE FUNCTION public.set_default_user_permissions()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
    BEGIN
        -- On INSERT or when permissions are explicitly set to NULL/empty/blank array
        IF NEW.permissions IS NULL OR NEW.permissions = '[]'::jsonb OR NEW.permissions = '"[]"'::jsonb OR NEW.permissions = '""'::jsonb THEN
            IF NEW.role_id = 1 THEN
                NEW.permissions := '["all", "manage_users", "view_logs", "manage_admins", "system_settings", "manage_questions"]'::jsonb;
            ELSIF NEW.role_id = 2 THEN
                NEW.permissions := '["manage_users", "view_logs"]'::jsonb;
            ELSE
                NEW.permissions := '[]'::jsonb;
            END IF;
        END IF;
        
        -- On UPDATE, if the role_id is changed and permissions is equal to either NULL, empty, or the defaults of the old role,
        -- we automatically update the permissions to the defaults of the new role.
        IF TG_OP = 'UPDATE' AND OLD.role_id IS DISTINCT FROM NEW.role_id THEN
            IF OLD.permissions IS NULL OR OLD.permissions = '[]'::jsonb OR OLD.permissions = '"[]"'::jsonb OR
               (OLD.role_id = 1 AND OLD.permissions = '["all", "manage_users", "view_logs", "manage_admins", "system_settings", "manage_questions"]'::jsonb) OR
               (OLD.role_id = 2 AND OLD.permissions = '["manage_users", "view_logs"]'::jsonb) THEN
                
                IF NEW.role_id = 1 THEN
                    NEW.permissions := '["all", "manage_users", "view_logs", "manage_admins", "system_settings", "manage_questions"]'::jsonb;
                ELSIF NEW.role_id = 2 THEN
                    NEW.permissions := '["manage_users", "view_logs"]'::jsonb;
                ELSE
                    NEW.permissions := '[]'::jsonb;
                END IF;
            END IF;
        END IF;
        
        RETURN NEW;
    END;
    $function$
;

DROP TABLE IF EXISTS public.activation_requests CASCADE;
CREATE TABLE public.activation_requests (
    request_id integer NOT NULL DEFAULT nextval('activation_requests_request_id_seq'::regclass),
    user_id integer NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    reviewed_by integer,
    review_notes text,
    PRIMARY KEY (request_id)
);

DROP TABLE IF EXISTS public.activity_logs CASCADE;
CREATE TABLE public.activity_logs (
    log_id integer NOT NULL DEFAULT nextval('activity_logs_log_id_seq'::regclass),
    table_name character varying(50) NOT NULL,
    record_id integer,
    action character varying(10) NOT NULL,
    old_data jsonb,
    new_data jsonb,
    performed_by integer,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id)
);

DROP TABLE IF EXISTS public.admin_activity_logs CASCADE;
CREATE TABLE public.admin_activity_logs (
    log_id integer NOT NULL DEFAULT nextval('admin_activity_logs_log_id_seq'::regclass),
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
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id)
);

DROP TABLE IF EXISTS public.deletion_requests CASCADE;
CREATE TABLE public.deletion_requests (
    request_id integer NOT NULL DEFAULT nextval('deletion_requests_request_id_seq'::regclass),
    user_id integer NOT NULL,
    requested_by integer NOT NULL,
    reason text,
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    reviewed_by integer,
    review_notes text,
    PRIMARY KEY (request_id)
);

DROP TABLE IF EXISTS public.edit_requests CASCADE;
CREATE TABLE public.edit_requests (
    request_id integer NOT NULL DEFAULT nextval('edit_requests_request_id_seq'::regclass),
    user_id integer,
    requested_by integer,
    requested_data jsonb,
    reason text NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    reviewed_by integer,
    review_notes text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id)
);

DROP TABLE IF EXISTS public.login_attempts CASCADE;
CREATE TABLE public.login_attempts (
    attempt_id integer NOT NULL DEFAULT nextval('login_attempts_attempt_id_seq'::regclass),
    user_id integer,
    username character varying(50),
    ip_address character varying(45),
    success boolean DEFAULT false,
    attempt_time timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id)
);

DROP TABLE IF EXISTS public.password_reset_logs CASCADE;
CREATE TABLE public.password_reset_logs (
    log_id integer NOT NULL DEFAULT nextval('password_reset_logs_log_id_seq'::regclass),
    user_id integer,
    email character varying(255),
    attempt_type character varying(50),
    ip_address character varying(45),
    user_agent text,
    attempt_time timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    success boolean,
    lockout_until timestamp without time zone,
    details text,
    PRIMARY KEY (log_id)
);

DROP TABLE IF EXISTS public.password_reset_sessions CASCADE;
CREATE TABLE public.password_reset_sessions (
    session_id character varying(64) NOT NULL,
    user_id integer,
    email character varying(255),
    otp_hash character varying(255),
    otp_expiry timestamp without time zone,
    otp_attempts integer DEFAULT 0,
    answer_attempts integer DEFAULT 0,
    current_step character varying(20) DEFAULT 'email'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id)
);

DROP TABLE IF EXISTS public.roles CASCADE;
CREATE TABLE public.roles (
    role_id integer NOT NULL,
    role_name character varying(50) NOT NULL,
    role_description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id)
);

DROP TABLE IF EXISTS public.security_questions CASCADE;
CREATE TABLE public.security_questions (
    question_id integer NOT NULL DEFAULT nextval('security_questions_question_id_seq'::regclass),
    question_text text NOT NULL,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (question_id)
);

DROP TABLE IF EXISTS public.system_activity_logs CASCADE;
CREATE TABLE public.system_activity_logs (
    log_id integer NOT NULL DEFAULT nextval('system_activity_logs_log_id_seq'::regclass),
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
    PRIMARY KEY (log_id)
);

DROP TABLE IF EXISTS public.user_security_answers CASCADE;
CREATE TABLE public.user_security_answers (
    answer_id integer NOT NULL DEFAULT nextval('user_security_answers_answer_id_seq'::regclass),
    user_id integer NOT NULL,
    question_id integer NOT NULL,
    answer_hash character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (answer_id)
);

DROP TABLE IF EXISTS public.users CASCADE;
CREATE TABLE public.users (
    user_id integer NOT NULL DEFAULT nextval('users_user_id_seq'::regclass),
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
    role_id integer DEFAULT 3,
    is_active boolean DEFAULT true,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    last_login timestamp without time zone,
    permissions jsonb DEFAULT '[]'::jsonb,
    created_by integer,
    updated_by integer,
    registration_status character varying(20) DEFAULT 'pending'::character varying,
    PRIMARY KEY (user_id)
);

-- Data for table: activation_requests
INSERT INTO public.activation_requests (request_id, user_id, status, created_at, updated_at, reviewed_by, review_notes) VALUES (1, 1016, 'approved', '2026-05-18 11:57:28.627066', '2026-05-18 11:59:14.064404', 1011, NULL);

-- Data for table: activity_logs
INSERT INTO public.activity_logs (log_id, table_name, record_id, action, old_data, new_data, performed_by, ip_address, user_agent, created_at) VALUES (1, 'users', 1005, 'INSERT', NULL, '{"age": 22, "sex": "male", "email": "asdsa@asda.com", "country": "bkjb", "role_id": 3, "user_id": "1005", "zipcode": "8724", "barangay": "kjbkjb", "birthday": "2003-11-04", "province": "kjbkj", "username": "asd24", "id_number": "5944-6411", "last_name": "asdasd", "first_name": "asdsad", "middle_name": "", "street_purok": "asd", "city_municipal": "kjjkb", "contact_number": "09927232141", "extension_name": ""}', 1005, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 16:22:24.036531');
INSERT INTO public.activity_logs (log_id, table_name, record_id, action, old_data, new_data, performed_by, ip_address, user_agent, created_at) VALUES (5, 'users', 1009, 'INSERT', NULL, '{"age": 22, "sex": "male", "email": "solayaoflorence24@gmail.com", "country": "hkj", "role_id": 3, "user_id": "1009", "zipcode": "8604", "barangay": "jjhkjh", "birthday": "2003-11-04", "province": "kjhkj", "username": "cris245", "id_number": "2002-0945", "last_name": "asdasd", "first_name": "asdasd", "middle_name": "", "street_purok": "asdas", "city_municipal": "jkhkjh", "contact_number": "09925459721", "extension_name": ""}', 1009, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-05-15 21:07:35.486912');
INSERT INTO public.activity_logs (log_id, table_name, record_id, action, old_data, new_data, performed_by, ip_address, user_agent, created_at) VALUES (6, 'users', 1012, 'INSERT', NULL, '{"age": 24, "sex": "male", "email": "solayaoflorence@gmail.com", "country": "lkjlkj", "role_id": 3, "user_id": "1012", "zipcode": "9872", "barangay": "lkkjlk", "birthday": "2002-02-04", "province": "lkj", "username": "qwerty123", "id_number": "0000-0945", "last_name": "lml", "first_name": "kio", "middle_name": "k", "street_purok": "ann", "city_municipal": "jlkj", "contact_number": "09925450222", "extension_name": ""}', 1012, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-16 23:38:21.431388');
INSERT INTO public.activity_logs (log_id, table_name, record_id, action, old_data, new_data, performed_by, ip_address, user_agent, created_at) VALUES (7, 'users', 1015, 'INSERT', NULL, '{"age": 23, "sex": "male", "email": "solayaoflorence@gmail.com", "country": "philippines", "role_id": 3, "user_id": "1015", "zipcode": "8604", "barangay": "buhang", "birthday": "2003-04-11", "province": "agusan del norte", "username": "cris11", "id_number": "2022-0945", "last_name": "solayao", "first_name": "florence", "middle_name": "L", "street_purok": "purok 8", "city_municipal": "magallanes", "contact_number": "09925451111", "extension_name": ""}', 1015, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 11:19:52.21188');

-- Data for table: admin_activity_logs
INSERT INTO public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) VALUES (1, 1011, NULL, NULL, 'INSERT', 'users', 1013, NULL, '{"email": "az13r.slvrr@gmail.com", "role_id": 3, "username": "alex24"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, '2026-05-17 00:09:29.944887');
INSERT INTO public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) VALUES (2, 1011, NULL, NULL, 'TOGGLE_STATUS', 'users', 1013, '{"is_active": false}', '{"is_active": true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, '2026-05-17 00:14:53.831706');
INSERT INTO public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) VALUES (3, 1011, NULL, NULL, 'TOGGLE_STATUS', 'users', 1013, '{"is_active": true}', '{"is_active": false}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, '2026-05-17 00:22:22.691189');
INSERT INTO public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) VALUES (4, 1011, NULL, NULL, 'INSERT', 'users', 1014, NULL, '{"sex": "", "email": "msteramigo24@gmail.com", "country": "j", "role_id": 1, "zipcode": "2123", "barangay": "jlk", "birthday": "2003-02-01", "province": "jlk", "username": "cris123", "id_number": "2022-0943", "last_name": "asd", "first_name": "asd", "middle_name": "", "street_purok": "jkjk", "city_municipal": "jlk", "extension_name": ""}', '::1', NULL, NULL, NULL, NULL, NULL, '2026-05-18 08:36:17.20921');
INSERT INTO public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) VALUES (5, 1014, NULL, NULL, 'TOGGLE_STATUS', 'users', 1012, '{"is_active": false}', '{"is_active": true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, '2026-05-18 11:01:42.642261');
INSERT INTO public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) VALUES (6, 1011, NULL, NULL, 'TOGGLE_STATUS', 'users', 1015, '{"is_active": false}', '{"is_active": true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, '2026-05-18 11:24:36.903728');
INSERT INTO public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) VALUES (7, 1011, NULL, NULL, 'UPDATE', 'users', 1015, NULL, '{"email": "solayaoflorence@gmail.com", "role_id": 3, "username": "cris11"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, '2026-05-18 11:30:54.779324');
INSERT INTO public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) VALUES (8, 1011, NULL, NULL, 'INSERT', 'users', 1016, NULL, '{"sex": "", "email": "msteramigo24@gmail.com", "country": "Philippines", "role_id": 2, "zipcode": "8762", "barangay": "kjh", "birthday": "2001-02-02", "province": "hkj", "username": "crisadmin", "id_number": "2134-4213", "last_name": "nbn", "first_name": "nbb", "middle_name": "", "street_purok": "asd", "city_municipal": "jhkj", "contact_number": "09929098212", "extension_name": ""}', '::1', NULL, NULL, NULL, NULL, NULL, '2026-05-18 11:57:07.352651');

-- Data for table: login_attempts
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (1, NULL, 'jhkh', '::1', false, '2026-05-16 23:08:49.464397');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (2, NULL, 'klj', '::1', false, '2026-05-16 23:08:54.35238');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (3, 1010, 'superadmin', '::1', false, '2026-05-16 23:17:21.768165');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (4, 1010, 'superadmin', '::1', false, '2026-05-16 23:17:30.688864');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (5, 1011, 'cris24', '::1', true, '2026-05-16 23:25:19.590048');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (6, 1012, 'qwerty123', '::1', false, '2026-05-16 23:38:37.798989');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (7, 1013, 'alex24', '::1', false, '2026-05-17 00:10:31.976893');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (8, 1013, 'alex24', '::1', false, '2026-05-17 00:10:46.724989');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (9, 1013, 'alex24', '::1', false, '2026-05-17 00:14:39.471263');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (10, 1013, 'alex24', '::1', true, '2026-05-17 00:15:26.628678');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (11, NULL, 'asd', '::1', false, '2026-05-17 00:19:51.808147');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (12, NULL, 'asd', '::1', false, '2026-05-17 00:19:55.333288');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (13, NULL, 'bvb', '::1', false, '2026-05-17 00:22:41.232159');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (14, NULL, 'fgg', '::1', false, '2026-05-17 00:23:09.020467');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (15, NULL, 'vbbv', '::1', false, '2026-05-17 00:23:14.710206');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (16, NULL, 'SUPER-001', '::1', false, '2026-05-17 20:04:56.200573');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (17, 1011, 'cris24', '::1', true, '2026-05-17 20:10:40.630141');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (18, 1011, 'cris24', '::1', false, '2026-05-17 20:11:57.278967');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (19, 1011, 'cris24', '::1', false, '2026-05-17 20:12:04.119497');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (20, NULL, 'crissaunt', '::1', false, '2026-05-17 20:12:27.837052');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (21, 1011, 'cris24', '::1', true, '2026-05-17 20:34:49.939044');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (22, 1011, 'cris24', '::1', true, '2026-05-18 08:33:57.622766');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (23, 1014, 'cris123', '::1', true, '2026-05-18 08:38:22.137316');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (24, 1015, 'cris11', '::1', false, '2026-05-18 11:20:07.058402');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (25, 1011, 'cris24', '::1', true, '2026-05-18 11:21:29.246423');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (26, NULL, 'nm', '::1', false, '2026-05-18 11:24:19.318993');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (27, 1015, 'cris11', '::1', true, '2026-05-18 11:25:09.18159');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (28, 1015, 'cris11', '::1', true, '2026-05-18 11:30:03.959827');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (29, 1015, 'cris11', '::1', false, '2026-05-18 11:31:08.322559');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (30, 1015, 'cris11', '::1', true, '2026-05-18 11:31:18.736656');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (31, 1016, 'crisadmin', '::1', false, '2026-05-18 11:57:28.615761');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (32, NULL, 'cris11', '::1', false, '2026-05-18 11:58:03.083262');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (33, NULL, 'cris11', '::1', false, '2026-05-18 11:58:17.67941');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (34, 1011, 'cris24', '::1', true, '2026-05-18 11:59:02.543835');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (35, 1016, 'crisadmin', '::1', true, '2026-05-18 11:59:28.06156');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (36, NULL, 'asd', '::1', false, '2026-05-18 12:02:57.762568');
INSERT INTO public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) VALUES (37, 1016, 'crisadmin', '::1', true, '2026-05-18 12:03:07.979834');

-- Data for table: password_reset_logs
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (1, 1005, 'asdsa@asda.com', 'otp_request', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-16 23:09:36.908168', true, NULL, 'OTP sent successfully');
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (2, 1005, 'asdsa@asda.com', 'otp_request', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-16 23:31:19.098079', true, NULL, 'OTP sent successfully');
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (3, 1013, 'az13r.slvrr@gmail.com', 'otp_request', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 00:20:09.317616', true, NULL, 'OTP sent successfully');
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (4, 1013, 'az13r.slvrr@gmail.com', 'otp_verify', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 00:20:40.368716', true, NULL, 'OTP verified successfully');
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (5, 1013, 'az13r.slvrr@gmail.com', 'answer_verify', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 00:21:08.762491', true, NULL, 'All security answers verified');
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (6, 1013, 'az13r.slvrr@gmail.com', 'security_answer', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 00:21:08.767129', true, NULL, NULL);
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (7, 1013, 'az13r.slvrr@gmail.com', 'password_update', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 00:21:20.512717', true, NULL, 'Password updated successfully');
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (8, 1013, 'az13r.slvrr@gmail.com', 'otp_request', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 00:23:24.899447', true, NULL, 'OTP sent successfully');
INSERT INTO public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) VALUES (9, 1015, 'solayaoflorence@gmail.com', 'otp_request', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 11:24:47.472296', true, NULL, 'OTP sent successfully');

-- Data for table: password_reset_sessions
INSERT INTO public.password_reset_sessions (session_id, user_id, email, otp_hash, otp_expiry, otp_attempts, answer_attempts, current_step, created_at, updated_at) VALUES ('84c0edda502c6afe490db77d0eb21fa7373d638238d674781fb5fff480f6bfdc', 1015, 'solayaoflorence@gmail.com', '$2y$12$v1Gh3sYjGFU1lpjskpdqnuy1B5raYF/zFIz/W0zJbq4sVV0qdi0Ra', '2026-05-18 11:34:47', 0, 0, 'email', '2026-05-18 11:24:47.469461', '2026-05-18 11:24:47.469461');

-- Data for table: roles
INSERT INTO public.roles (role_id, role_name, role_description, is_active, created_at, updated_at) VALUES (1, 'Super Admin', 'Full system access', true, '2026-05-15 15:48:08.470887', '2026-05-15 15:48:08.470887');
INSERT INTO public.roles (role_id, role_name, role_description, is_active, created_at, updated_at) VALUES (2, 'Admin', 'Administrative access', true, '2026-05-15 15:48:08.470887', '2026-05-15 15:48:08.470887');
INSERT INTO public.roles (role_id, role_name, role_description, is_active, created_at, updated_at) VALUES (3, 'User', 'Standard user access', true, '2026-05-15 15:48:08.470887', '2026-05-15 15:48:08.470887');

-- Data for table: security_questions
INSERT INTO public.security_questions (question_id, question_text, is_active, created_at) VALUES (1, 'What is your mother''s maiden name?', true, '2026-05-15 15:58:09.32408');
INSERT INTO public.security_questions (question_id, question_text, is_active, created_at) VALUES (2, 'What was the name of your first pet?', true, '2026-05-15 15:58:09.32408');
INSERT INTO public.security_questions (question_id, question_text, is_active, created_at) VALUES (3, 'What city were you born in?', true, '2026-05-15 15:58:09.32408');
INSERT INTO public.security_questions (question_id, question_text, is_active, created_at) VALUES (4, 'What is the name of your favorite movie?', true, '2026-05-15 15:58:09.32408');
INSERT INTO public.security_questions (question_id, question_text, is_active, created_at) VALUES (5, 'What was the name of your first elementary school?', true, '2026-05-15 15:58:09.32408');
INSERT INTO public.security_questions (question_id, question_text, is_active, created_at) VALUES (6, 'What is your favorite color?', true, '2026-05-15 15:58:09.32408');

-- Data for table: system_activity_logs
INSERT INTO public.system_activity_logs (log_id, user_id, admin_id, actor_type, action, category, description, table_affected, record_identifier, ip_address, user_agent, created_at) VALUES (1, 1013, NULL, 'user', 'LOGOUT', 'authentication', 'User alex24 logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 00:19:48.39961');
INSERT INTO public.system_activity_logs (log_id, user_id, admin_id, actor_type, action, category, description, table_affected, record_identifier, ip_address, user_agent, created_at) VALUES (2, 1015, NULL, 'user', 'LOGOUT', 'authentication', 'User cris11 logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 11:29:54.901369');
INSERT INTO public.system_activity_logs (log_id, user_id, admin_id, actor_type, action, category, description, table_affected, record_identifier, ip_address, user_agent, created_at) VALUES (3, 1015, NULL, 'user', 'LOGOUT', 'authentication', 'User cris11 logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 11:30:25.831805');
INSERT INTO public.system_activity_logs (log_id, user_id, admin_id, actor_type, action, category, description, table_affected, record_identifier, ip_address, user_agent, created_at) VALUES (4, 1015, NULL, 'user', 'LOGOUT', 'authentication', 'User cris11 logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 11:31:20.330917');

-- Data for table: user_security_answers
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (1, 1003, 1, '$2y$12$gB6jx5EPx5qnaYqf2wLv2uUWimx4BnYrwm.gHw2mtXJSwUGPZQ7J.', '2026-05-15 16:18:31.396556');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (2, 1004, 1, '$2y$12$n5fRBhQ3ynOSOcBJAEM8weH7i2y14Pm3UCikxYAuFdojIx.zCqIRm', '2026-05-15 16:18:32.083409');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (3, 1005, 3, '$2y$12$UQUcypMttVRjRyYMA4e2Ye1s.5F0mYojCZ9iGZ7YBw/YQjdYXWVuy', '2026-05-15 16:22:24.036531');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (4, 1005, 4, '$2y$12$gRhp2CraDXyC/LsBoh1zHO4GGA.7bEBsYhgR7mC2iE5rWx43CkP7u', '2026-05-15 16:22:24.036531');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (5, 1005, 6, '$2y$12$/r2EYdjeF7otZKQYuiP7m.3.is5JswufLQ4KtT.s3TU1NcH4irP.2', '2026-05-15 16:22:24.036531');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (6, 1009, 1, '$2y$12$o2zw0MNlC4/9Md05uCdkX.JJvArzwd/5nuij2vtu2pxkuUQEnHKy6', '2026-05-15 21:07:35.486912');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (7, 1009, 3, '$2y$12$vApze84.d2t0r3fnWi.UsOGN3fzAA4PObUoDhH4Fgpgc9RfE3fUBS', '2026-05-15 21:07:35.486912');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (8, 1009, 2, '$2y$12$g3WYS3eTsfI49ZnxO4fO6eKTQo63aB3e6CxocuyykiztNHIn4MFjS', '2026-05-15 21:07:35.486912');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (9, 1012, 3, '$2y$12$tZYX8kxGkhy6REoHpFvpkuNQCcIzw.KCjvB9iWONQZhEWAvM3PiuW', '2026-05-16 23:38:21.431388');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (10, 1012, 4, '$2y$12$igoXQ1BP9Om4aAaasq.AQ.NQp/CWVi2Df8h..Qk5rEKqcAX/7fA4.', '2026-05-16 23:38:21.431388');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (11, 1012, 6, '$2y$12$opdkkuGmAQfD9bGi8zmrR.fJZDZ3TrslweUt9jjTxjuyC2jPofsnS', '2026-05-16 23:38:21.431388');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (12, 1013, 3, '$2y$12$m8gPhzFqAJQg1ZLNRLID5e528S/btbgOEeifJJYEByxeD7X7AFIwS', '2026-05-17 00:19:42.106625');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (13, 1013, 6, '$2y$12$gtbzTOL94TPVS6lO0xvOCueaEvf4iGeX3dn4CLfiwYfB58YsAMsj6', '2026-05-17 00:19:42.106625');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (14, 1013, 4, '$2y$12$TrKvcHHuAJ0AreMIGuuKn.7KtofZ8iDZm8pR2CTmn9BPQ9WZY14fe', '2026-05-17 00:19:42.106625');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (15, 1011, 3, '$2y$12$Yq8SI.I/yVmUpZFuvMeNnO.Bo0ayqHrJt5cm5eIk7Qplstxg6ncFS', '2026-05-17 20:11:39.747676');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (16, 1011, 4, '$2y$12$E7t9pclOZRyivW.qz0dtKel5kXdKNL9jW0Jvg3of1n6JJKeq3rqE2', '2026-05-17 20:11:39.747676');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (17, 1011, 6, '$2y$12$dlwje7zU8XgC/XrMStUzCeyMlJa2HLPaGYfFa0xGVhTJxfDeH65HW', '2026-05-17 20:11:39.747676');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (18, 1014, 3, '$2y$12$/wCFq9ywL2MuIhJ3lTu1Ke/AcfzZEWj.iObqy5RZlnn3sHh4c3cSO', '2026-05-18 08:38:52.269831');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (19, 1014, 4, '$2y$12$I9VqAqdQttK/oM1ZcPxUI.hmryKvwCe87xTecyZGchv/RhgGd9Ebi', '2026-05-18 08:38:52.269831');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (20, 1014, 6, '$2y$12$XKIszMO9o6Y3yNY1SVFOb.NlX7uNPySMhxvI8KMYoXLvuhpVSD1xS', '2026-05-18 08:38:52.269831');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (21, 1015, 3, '$2y$12$oNnn5HFaTKW5YnjIDxmrw.KNZq7Maz47dgPkZesuS4VUvJvrFT7FG', '2026-05-18 11:19:52.21188');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (22, 1015, 4, '$2y$12$h7ctdudHmk2s3AURi1Wx7umG23616AjcCh2BQ2LIZqC2Sf6JvoJO.', '2026-05-18 11:19:52.21188');
INSERT INTO public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) VALUES (23, 1015, 6, '$2y$12$WTbT8t1Pb8miGbElfQw05.Q8vJX8Qs46cP4DLZa6tyjnglehP4X/a', '2026-05-18 11:19:52.21188');

-- Data for table: users
INSERT INTO public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by, registration_status) VALUES (1005, '5944-6411', 'asdsa@asda.com', 09927232141, 'asd24', 'asdsad', '', 'asdasd', '', '2003-11-04', 22, 'male', '$2y$12$G/gCMjkHqicnZb57a5wtMu7PVoD/7sKW5F.5J721k5kFLwnFucJIe', 'asd', 'kjbkjb', 'kjjkb', 'kjbkj', 'bkjb', 8724, '2026-05-15 16:22:24.036531', NULL, NULL, NULL, 3, false, '2026-05-18 11:08:56.376343', NULL, '[]', NULL, NULL, 'rejected');
INSERT INTO public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by, registration_status) VALUES (1016, '2134-4213', 'msteramigo24@gmail.com', 09929098212, 'crisadmin', 'nbb', '', 'nbn', '', '2001-02-02', 25, NULL, '$2y$12$LvSQo4zTDO0ed5mlTmMr2ebFnoWLRT2ZqU5qpKu84nWt3EgmXdoA6', 'asd', 'kjh', 'jhkj', 'hkj', 'Philippines', 8762, '2026-05-18 11:57:07.344596', NULL, NULL, NULL, 2, true, '2026-05-18 11:59:14.064404', '2026-05-18 12:03:07.989663', '["manage_users", "view_logs"]', 1011, 1011, 'approved');
INSERT INTO public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by, registration_status) VALUES (1003, '9999-9999', 'diag_test@example.com', 09123456789, 'diag_user', 'Diag', 'Test', 'User', '', '1990-01-01', 34, 'Male', '$2y$12$wiGay53e2OKjj687CkhPjeZS5MP2vMAld/SoxwpsC3sf4.Ku3JOX2', 'Purok 1', 'Barangay 1', 'City 1', 'Province 1', 'Philippines', 1234, '2026-05-15 16:18:31.396556', NULL, NULL, NULL, 3, true, '2026-05-15 16:18:31.396556', NULL, '[]', NULL, NULL, 'approved');
INSERT INTO public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by, registration_status) VALUES (1004, '9999-9999', 'diag_test@example.com', 09123456789, 'diag_user', 'Diag', 'Test', 'User', '', '1990-01-01', 34, 'Male', '$2y$12$mMUIeZ10zLNxsMBOX5WsR.ttrynwEkBTB9sUGhBEkfTJEHw4RYHN.', 'Purok 1', 'Barangay 1', 'City 1', 'Province 1', 'Philippines', 1234, '2026-05-15 16:18:32.083409', NULL, NULL, NULL, 3, true, '2026-05-15 16:18:32.083409', NULL, '[]', NULL, NULL, 'approved');
INSERT INTO public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by, registration_status) VALUES (1010, NULL, 'superadmin@solayao.com', NULL, 'superadmin', 'Super', NULL, 'Admin', NULL, NULL, NULL, NULL, '$2y$12$SzGSQa86VyKFo.Fel3ZwZ.0tZbrUIv9smwRqH7CZGRZmplTR7293G', NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-15 22:02:35.763572', NULL, NULL, NULL, 1, true, '2026-05-15 22:02:35.763572', '2026-05-15 22:06:42.629624', '["all", "manage_users", "view_logs", "manage_admins", "system_settings", "manage_questions"]', NULL, NULL, 'approved');
INSERT INTO public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by, registration_status) VALUES (1013, '1234-4321', 'az13r.slvrr@gmail.com', 09986765439, 'alex24', 'asdasd', NULL, 'asdasd', NULL, '2003-04-04', NULL, 'Male', '$2y$12$aUoilviT7znuhm5/LcgF8eyG5dUAuG0ZEOhx4nqGW6jvSHMFW0ONK', 'asd', 'asdas', 'asd', 'asdasdasd', 'Philippines', 1245, '2026-05-17 00:09:29.933996', NULL, NULL, '2026-05-17 00:21:20.495954', 3, false, '2026-05-17 00:22:22.68117', '2026-05-17 00:15:26.632654', '[]', NULL, NULL, 'pending');
INSERT INTO public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by, registration_status) VALUES (1015, '2022-0945', 'solayaoflorence@gmail.com', 09925451111, 'cris11', 'florence', 'L', 'solayao', '', '2003-04-11', 23, 'Male', '$2y$12$f46gXck/TyorpbLFldtZmOfLhfhXBIm1s2Qq0jJ9DmtowFt4nvwjK', 'purok 8', 'buhang', 'magallanes', 'agusan del norte', 'philippines', 8604, '2026-05-18 11:19:52.21188', NULL, NULL, NULL, 3, true, '2026-05-18 11:30:54.486393', '2026-05-18 11:31:18.738627', '[]', NULL, NULL, 'approved');
INSERT INTO public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by, registration_status) VALUES (1011, 'SUPER-001', 'cris24@example.com', 09000000000, 'cris24', 'Cris', NULL, 'Superadmin', NULL, '1990-01-01', 34, 'Male', '$2y$12$iS2Z8TliNB0wOp.Cx9g5cemWEKQRpwPDZ0GrrM9ig/nZe2Q05Skg2', 'Main St', 'Central', 'City', 'Province', 'Philippines', 1234, '2026-05-16 23:24:41.099402', NULL, NULL, NULL, 1, true, '2026-05-16 23:24:41.099402', '2026-05-18 11:59:02.553795', '["all", "manage_users", "view_logs", "manage_admins", "system_settings", "manage_questions"]', NULL, NULL, 'approved');

