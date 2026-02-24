--
-- PostgreSQL database dump
--

\restrict 3M8aAMFiRmrTNUAmmAb1qeIEIgjB5xZWwHaCqPtIJ7a65lCeAt13Qigu5rNNabh

-- Dumped from database version 17.6
-- Dumped by pg_dump version 17.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: check_admin_permission(integer, character varying, character varying); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.check_admin_permission(p_admin_id integer, p_module character varying, p_action character varying) RETURNS boolean
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_role VARCHAR;
    v_permissions JSONB;
BEGIN
    SELECT role, permissions INTO v_role, v_permissions
    FROM admin_users
    WHERE admin_id = p_admin_id AND is_active = true;
    
    -- Super admin has all permissions
    IF v_role = 'super_admin' THEN
        RETURN true;
    END IF;
    
    -- Check specific permission
    RETURN (
        v_permissions->'modules'->p_module ? p_action
        OR
        v_permissions->'features' ? p_action
    );
END;
$$;


ALTER FUNCTION public.check_admin_permission(p_admin_id integer, p_module character varying, p_action character varying) OWNER TO postgres;

--
-- Name: create_admin(character varying, character varying, character varying, character varying, character varying, character varying, jsonb, integer); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.create_admin(p_username character varying, p_email character varying, p_password_hash character varying, p_first_name character varying, p_last_name character varying, p_role character varying, p_permissions jsonb, p_created_by integer) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_admin_id INTEGER;
BEGIN
    -- Check if creator is super_admin
    IF NOT EXISTS (
        SELECT 1 FROM admin_users 
        WHERE admin_id = p_created_by 
        AND role = 'super_admin' 
        AND is_active = true
    ) THEN
        RAISE EXCEPTION 'Only super_admin can create admin users';
    END IF;
    
    INSERT INTO admin_users (
        username,
        email,
        password_hash,
        first_name,
        last_name,
        role,
        permissions,
        is_active,
        created_by
    ) VALUES (
        p_username,
        p_email,
        p_password_hash,
        p_first_name,
        p_last_name,
        p_role,
        p_permissions,
        true,
        p_created_by
    ) RETURNING admin_id INTO v_admin_id;
    
    -- Log the activity
    INSERT INTO system_activity_logs (
        admin_id,
        actor_type,
        action,
        category,
        description,
        table_affected,
        record_identifier
    ) VALUES (
        p_created_by,
        'super_admin',
        'CREATE_ADMIN',
        'ADMIN_MANAGEMENT',
        'Created new admin: ' || p_username,
        'admin_users',
        v_admin_id::VARCHAR
    );
    
    RETURN v_admin_id;
END;
$$;


ALTER FUNCTION public.create_admin(p_username character varying, p_email character varying, p_password_hash character varying, p_first_name character varying, p_last_name character varying, p_role character varying, p_permissions jsonb, p_created_by integer) OWNER TO postgres;

--
-- Name: log_activity(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.log_activity() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF (TG_OP = 'INSERT') THEN
        INSERT INTO activity_logs (table_name, record_id, action, new_data, performed_by)
        VALUES (TG_TABLE_NAME, NEW.user_id, 'INSERT', row_to_json(NEW), NEW.user_id);
        RETURN NEW;
    ELSIF (TG_OP = 'UPDATE') THEN
        INSERT INTO activity_logs (table_name, record_id, action, old_data, new_data, performed_by)
        VALUES (TG_TABLE_NAME, NEW.user_id, 'UPDATE', row_to_json(OLD), row_to_json(NEW), NEW.user_id);
        RETURN NEW;
    ELSIF (TG_OP = 'DELETE') THEN
        INSERT INTO activity_logs (table_name, record_id, action, old_data, performed_by)
        VALUES (TG_TABLE_NAME, OLD.user_id, 'DELETE', row_to_json(OLD), OLD.user_id);
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$;


ALTER FUNCTION public.log_activity() OWNER TO postgres;

--
-- Name: log_admin_activity(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.log_admin_activity() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_old_data JSONB;
    v_new_data JSONB;
    v_action_type VARCHAR(100);
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_action_type = 'CREATE';
        v_new_data = to_jsonb(NEW);
    ELSIF TG_OP = 'UPDATE' THEN
        v_action_type = 'UPDATE';
        v_old_data = to_jsonb(OLD);
        v_new_data = to_jsonb(NEW);
    ELSIF TG_OP = 'DELETE' THEN
        v_action_type = 'DELETE';
        v_old_data = to_jsonb(OLD);
    END IF;
    
    INSERT INTO admin_activity_logs (
        admin_id,
        username,
        role,
        action_type,
        table_name,
        record_id,
        old_data,
        new_data,
        ip_address,
        created_at
    ) VALUES (
        current_setting('app.current_admin_id', TRUE)::INTEGER,
        current_setting('app.current_admin_username', TRUE),
        current_setting('app.current_admin_role', TRUE),
        v_action_type,
        TG_TABLE_NAME,
        CASE 
            WHEN TG_OP = 'DELETE' THEN OLD.admin_id::VARCHAR
            ELSE NEW.admin_id::VARCHAR
        END,
        v_old_data,
        v_new_data,
        inet_client_addr(),
        CURRENT_TIMESTAMP
    );
    
    RETURN COALESCE(NEW, OLD);
END;
$$;


ALTER FUNCTION public.log_admin_activity() OWNER TO postgres;

--
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_updated_at_column() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.activity_logs (
    log_id integer NOT NULL,
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


ALTER TABLE public.activity_logs OWNER TO postgres;

--
-- Name: activity_logs_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.activity_logs_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.activity_logs_log_id_seq OWNER TO postgres;

--
-- Name: activity_logs_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.activity_logs_log_id_seq OWNED BY public.activity_logs.log_id;


--
-- Name: admin_activity_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.admin_activity_logs (
    log_id integer NOT NULL,
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


ALTER TABLE public.admin_activity_logs OWNER TO postgres;

--
-- Name: admin_activity_logs_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.admin_activity_logs_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.admin_activity_logs_log_id_seq OWNER TO postgres;

--
-- Name: admin_activity_logs_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.admin_activity_logs_log_id_seq OWNED BY public.admin_activity_logs.log_id;


--
-- Name: admin_users_backup; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.admin_users_backup (
    admin_id integer NOT NULL,
    user_id integer,
    username character varying(100) NOT NULL,
    email character varying(255) NOT NULL,
    password_hash character varying(255) NOT NULL,
    first_name character varying(100),
    last_name character varying(100),
    role character varying(50) NOT NULL,
    permissions jsonb DEFAULT '[]'::jsonb,
    is_active boolean DEFAULT true,
    last_login timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_by integer,
    updated_by integer,
    CONSTRAINT admin_users_role_check CHECK (((role)::text = ANY ((ARRAY['super_admin'::character varying, 'admin'::character varying])::text[])))
);


ALTER TABLE public.admin_users_backup OWNER TO postgres;

--
-- Name: COLUMN admin_users_backup.permissions; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admin_users_backup.permissions IS 'JSON structure example:
{
    "modules": {
        "users": ["view", "create", "update", "delete"],
        "security_questions": ["view", "create", "update"],
        "reports": ["view", "export"],
        "admin_management": ["view"]  // Only super_admin has full access
    },
    "features": ["reset_password", "lock_account", "view_logs"],
    "limits": {
        "max_login_attempts": 5,
        "session_timeout": 3600
    }
}';


--
-- Name: admin_dashboard; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.admin_dashboard AS
 SELECT a.admin_id,
    a.username,
    a.email,
    a.first_name,
    a.last_name,
    a.role,
    a.is_active,
    a.last_login,
    a.created_at,
    count(DISTINCT al.log_id) AS total_activities,
    max(al.created_at) AS last_activity,
    ( SELECT count(*) AS count
           FROM public.admin_activity_logs
          WHERE ((admin_activity_logs.admin_id = a.admin_id) AND (admin_activity_logs.created_at >= CURRENT_DATE))) AS activities_today
   FROM (public.admin_users_backup a
     LEFT JOIN public.admin_activity_logs al ON ((a.admin_id = al.admin_id)))
  GROUP BY a.admin_id, a.username, a.email, a.first_name, a.last_name, a.role, a.is_active, a.last_login, a.created_at;


ALTER VIEW public.admin_dashboard OWNER TO postgres;

--
-- Name: admin_login_attempts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.admin_login_attempts (
    attempt_id integer NOT NULL,
    username character varying(100),
    ip_address inet,
    success boolean,
    failure_reason character varying(255),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.admin_login_attempts OWNER TO postgres;

--
-- Name: admin_login_attempts_attempt_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.admin_login_attempts_attempt_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.admin_login_attempts_attempt_id_seq OWNER TO postgres;

--
-- Name: admin_login_attempts_attempt_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.admin_login_attempts_attempt_id_seq OWNED BY public.admin_login_attempts.attempt_id;


--
-- Name: admin_users_admin_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.admin_users_admin_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.admin_users_admin_id_seq OWNER TO postgres;

--
-- Name: admin_users_admin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.admin_users_admin_id_seq OWNED BY public.admin_users_backup.admin_id;


--
-- Name: deletion_requests; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.deletion_requests (
    request_id integer NOT NULL,
    user_id integer NOT NULL,
    requested_by integer NOT NULL,
    reason text,
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    reviewed_by integer,
    review_notes text
);


ALTER TABLE public.deletion_requests OWNER TO postgres;

--
-- Name: deletion_requests_request_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.deletion_requests_request_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.deletion_requests_request_id_seq OWNER TO postgres;

--
-- Name: deletion_requests_request_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.deletion_requests_request_id_seq OWNED BY public.deletion_requests.request_id;


--
-- Name: edit_requests; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.edit_requests (
    request_id integer NOT NULL,
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


ALTER TABLE public.edit_requests OWNER TO postgres;

--
-- Name: edit_requests_request_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.edit_requests_request_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.edit_requests_request_id_seq OWNER TO postgres;

--
-- Name: edit_requests_request_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.edit_requests_request_id_seq OWNED BY public.edit_requests.request_id;


--
-- Name: login_attempts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.login_attempts (
    attempt_id integer NOT NULL,
    user_id integer,
    username character varying(50),
    ip_address character varying(45),
    success boolean DEFAULT false,
    attempt_time timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.login_attempts OWNER TO postgres;

--
-- Name: login_attempts_attempt_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.login_attempts_attempt_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.login_attempts_attempt_id_seq OWNER TO postgres;

--
-- Name: login_attempts_attempt_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.login_attempts_attempt_id_seq OWNED BY public.login_attempts.attempt_id;


--
-- Name: password_reset_attempts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_attempts (
    attempt_id integer NOT NULL,
    email character varying(255),
    ip_address character varying(45),
    attempt_time timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    attempt_type character varying(50)
);


ALTER TABLE public.password_reset_attempts OWNER TO postgres;

--
-- Name: password_reset_attempts_attempt_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.password_reset_attempts_attempt_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.password_reset_attempts_attempt_id_seq OWNER TO postgres;

--
-- Name: password_reset_attempts_attempt_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.password_reset_attempts_attempt_id_seq OWNED BY public.password_reset_attempts.attempt_id;


--
-- Name: password_reset_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_logs (
    log_id integer NOT NULL,
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


ALTER TABLE public.password_reset_logs OWNER TO postgres;

--
-- Name: password_reset_logs_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.password_reset_logs_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.password_reset_logs_log_id_seq OWNER TO postgres;

--
-- Name: password_reset_logs_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.password_reset_logs_log_id_seq OWNED BY public.password_reset_logs.log_id;


--
-- Name: password_reset_sessions; Type: TABLE; Schema: public; Owner: postgres
--

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
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.password_reset_sessions OWNER TO postgres;

--
-- Name: permissions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.permissions (
    permission_id integer NOT NULL,
    permission_name character varying(100) NOT NULL,
    permission_description text,
    resource character varying(50) NOT NULL,
    action character varying(20) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.permissions OWNER TO postgres;

--
-- Name: permissions_permission_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.permissions_permission_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissions_permission_id_seq OWNER TO postgres;

--
-- Name: permissions_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.permissions_permission_id_seq OWNED BY public.permissions.permission_id;


--
-- Name: role_permissions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.role_permissions (
    role_permission_id integer NOT NULL,
    role_id integer NOT NULL,
    permission_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.role_permissions OWNER TO postgres;

--
-- Name: role_permissions_role_permission_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.role_permissions_role_permission_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.role_permissions_role_permission_id_seq OWNER TO postgres;

--
-- Name: role_permissions_role_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.role_permissions_role_permission_id_seq OWNED BY public.role_permissions.role_permission_id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.roles (
    role_id integer NOT NULL,
    role_name character varying(50) NOT NULL,
    role_description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.roles OWNER TO postgres;

--
-- Name: roles_role_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.roles_role_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_role_id_seq OWNER TO postgres;

--
-- Name: roles_role_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.roles_role_id_seq OWNED BY public.roles.role_id;


--
-- Name: security_questions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.security_questions (
    question_id integer NOT NULL,
    question_text character varying(255) NOT NULL,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.security_questions OWNER TO postgres;

--
-- Name: security_questions_question_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.security_questions_question_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.security_questions_question_id_seq OWNER TO postgres;

--
-- Name: security_questions_question_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.security_questions_question_id_seq OWNED BY public.security_questions.question_id;


--
-- Name: system_activity_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.system_activity_logs (
    log_id integer NOT NULL,
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


ALTER TABLE public.system_activity_logs OWNER TO postgres;

--
-- Name: system_activity_logs_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.system_activity_logs_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.system_activity_logs_log_id_seq OWNER TO postgres;

--
-- Name: system_activity_logs_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.system_activity_logs_log_id_seq OWNED BY public.system_activity_logs.log_id;


--
-- Name: system_activity_summary; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.system_activity_summary AS
 SELECT date(created_at) AS activity_date,
    actor_type,
    action,
    category,
    count(*) AS action_count
   FROM public.system_activity_logs
  GROUP BY (date(created_at)), actor_type, action, category
  ORDER BY (date(created_at)) DESC, (count(*)) DESC;


ALTER VIEW public.system_activity_summary OWNER TO postgres;

--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    user_id integer NOT NULL,
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
    updated_by integer
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: unified_ip_activities; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.unified_ip_activities AS
 SELECT 'user_login'::text AS source_type,
    login_attempts.user_id,
    login_attempts.username,
    login_attempts.attempt_time AS activity_time,
    login_attempts.ip_address,
    login_attempts.success,
    'login'::text AS category
   FROM public.login_attempts
UNION ALL
 SELECT 'admin_login'::text AS source_type,
    NULL::integer AS user_id,
    admin_login_attempts.username,
    admin_login_attempts.created_at AS activity_time,
    (admin_login_attempts.ip_address)::character varying AS ip_address,
    admin_login_attempts.success,
    'admin_login'::text AS category
   FROM public.admin_login_attempts
UNION ALL
 SELECT 'system_activity'::text AS source_type,
    system_activity_logs.user_id,
        CASE
            WHEN ((system_activity_logs.actor_type)::text = 'user'::text) THEN ( SELECT users.username
               FROM public.users
              WHERE (users.user_id = system_activity_logs.user_id))
            ELSE ( SELECT admin_users_backup.username
               FROM public.admin_users_backup
              WHERE (admin_users_backup.admin_id = system_activity_logs.admin_id))
        END AS username,
    system_activity_logs.created_at AS activity_time,
    (system_activity_logs.ip_address)::character varying AS ip_address,
    NULL::boolean AS success,
    system_activity_logs.category
   FROM public.system_activity_logs
  WHERE (system_activity_logs.ip_address IS NOT NULL)
UNION ALL
 SELECT 'user_activity'::text AS source_type,
    activity_logs.performed_by AS user_id,
    ( SELECT users.username
           FROM public.users
          WHERE (users.user_id = activity_logs.performed_by)) AS username,
    activity_logs.created_at AS activity_time,
    activity_logs.ip_address,
    NULL::boolean AS success,
    'activity'::text AS category
   FROM public.activity_logs
  WHERE (activity_logs.ip_address IS NOT NULL);


ALTER VIEW public.unified_ip_activities OWNER TO postgres;

--
-- Name: user_security_answers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.user_security_answers (
    answer_id integer NOT NULL,
    user_id integer NOT NULL,
    question_id integer NOT NULL,
    answer_hash character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.user_security_answers OWNER TO postgres;

--
-- Name: user_security_answers_answer_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.user_security_answers_answer_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_security_answers_answer_id_seq OWNER TO postgres;

--
-- Name: user_security_answers_answer_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.user_security_answers_answer_id_seq OWNED BY public.user_security_answers.answer_id;


--
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_user_id_seq OWNER TO postgres;

--
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_user_id_seq OWNED BY public.users.user_id;


--
-- Name: activity_logs log_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_logs ALTER COLUMN log_id SET DEFAULT nextval('public.activity_logs_log_id_seq'::regclass);


--
-- Name: admin_activity_logs log_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_activity_logs ALTER COLUMN log_id SET DEFAULT nextval('public.admin_activity_logs_log_id_seq'::regclass);


--
-- Name: admin_login_attempts attempt_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_login_attempts ALTER COLUMN attempt_id SET DEFAULT nextval('public.admin_login_attempts_attempt_id_seq'::regclass);


--
-- Name: admin_users_backup admin_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users_backup ALTER COLUMN admin_id SET DEFAULT nextval('public.admin_users_admin_id_seq'::regclass);


--
-- Name: deletion_requests request_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deletion_requests ALTER COLUMN request_id SET DEFAULT nextval('public.deletion_requests_request_id_seq'::regclass);


--
-- Name: edit_requests request_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.edit_requests ALTER COLUMN request_id SET DEFAULT nextval('public.edit_requests_request_id_seq'::regclass);


--
-- Name: login_attempts attempt_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.login_attempts ALTER COLUMN attempt_id SET DEFAULT nextval('public.login_attempts_attempt_id_seq'::regclass);


--
-- Name: password_reset_attempts attempt_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_attempts ALTER COLUMN attempt_id SET DEFAULT nextval('public.password_reset_attempts_attempt_id_seq'::regclass);


--
-- Name: password_reset_logs log_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_logs ALTER COLUMN log_id SET DEFAULT nextval('public.password_reset_logs_log_id_seq'::regclass);


--
-- Name: permissions permission_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.permissions ALTER COLUMN permission_id SET DEFAULT nextval('public.permissions_permission_id_seq'::regclass);


--
-- Name: role_permissions role_permission_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_permissions ALTER COLUMN role_permission_id SET DEFAULT nextval('public.role_permissions_role_permission_id_seq'::regclass);


--
-- Name: roles role_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles ALTER COLUMN role_id SET DEFAULT nextval('public.roles_role_id_seq'::regclass);


--
-- Name: security_questions question_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.security_questions ALTER COLUMN question_id SET DEFAULT nextval('public.security_questions_question_id_seq'::regclass);


--
-- Name: system_activity_logs log_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.system_activity_logs ALTER COLUMN log_id SET DEFAULT nextval('public.system_activity_logs_log_id_seq'::regclass);


--
-- Name: user_security_answers answer_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_security_answers ALTER COLUMN answer_id SET DEFAULT nextval('public.user_security_answers_answer_id_seq'::regclass);


--
-- Name: users user_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN user_id SET DEFAULT nextval('public.users_user_id_seq'::regclass);


--
-- Data for Name: activity_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.activity_logs (log_id, table_name, record_id, action, old_data, new_data, performed_by, ip_address, user_agent, created_at) FROM stdin;
3	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:16:17.308736
4	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:16:18.252794
5	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:17:54.29004
6	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:18:02.468538
7	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:19:15.540653
8	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:19:49.432229
9	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:19:50.453319
10	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:21:24.27592
11	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:21:25.49061
12	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:23:28.06664
13	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:25:23.353579
14	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:25:24.495195
15	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:25:44.148596
16	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:25:47.761537
17	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:25:53.081464
18	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:25:54.507173
19	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:25:56.220374
20	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:25:57.385409
21	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:26:02.492219
22	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:26:33.475106
23	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:26:34.176129
24	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:26:35.091055
25	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:26:45.930249
26	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:26:57.888149
27	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 23:31:55.625393
327	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:33:01.27448
335	users	107	INSERT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:00:42.791794
343	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 22:06:41.096447
351	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:03:36.041568
359	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:06:40.317857
367	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:09:36.543114
375	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:11:04.364958
383	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:29.337554
391	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:46:23.53276
399	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:50:46.393498
407	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:18.955437
415	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:44.377541
423	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:50.155189
431	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:54:01.568384
439	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:01:47.340885
447	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:07:41.941
455	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:13:12.282667
463	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:19:37.929004
471	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:45:46.542122
328	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:33:06.233895
336	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:00:55.155977
344	users	108	INSERT	\N	\N	100	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 22:07:46.141288
352	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:03:36.84352
360	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:06:40.676482
368	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:09:36.862143
376	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:11:18.877546
384	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:32.61559
392	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:46:47.509778
400	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:51:33.818469
408	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:19.801419
416	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:45.314303
424	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:50.517742
432	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:54:02.076093
440	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:01:47.407493
448	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:10:40.959671
456	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:13:13.848453
464	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:19:49.114914
472	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:46:22.896677
478	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:54:40.268455
490	dashboard	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 20:46:32.033927
496	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:18:58.908167
502	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:20:13.817553
508	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:21:15.445142
513	admin	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:25:01.118819
73	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:10:38.574148
74	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:10:52.300317
518	admin	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:39:50.214311
523	dashboard	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 22:37:24.333132
540	dashboard	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:09:52.180915
545	users	\N	VIEW_LIST	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:11:01.851578
550	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:14.963161
555	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:24.672595
559	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-21 23:30:53.940339
563	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-21 23:34:40.844896
567	dashboard	\N	VIEW	\N	\N	105	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:21:10.592889
574	users	\N	VIEW_LIST	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:38:35.952361
577	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:38:57.32421
329	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 21:59:02.688812
337	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:01:16.544312
345	users	109	INSERT	\N	{"email": "florencecris1.solayao2@gmail.com", "role_id": 3, "user_id": "109", "username": "crissaunt123", "last_name": "asdasd", "first_name": "asdasd"}	109	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 11:40:21.061848
353	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:03:38.739592
361	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:07:36.353745
369	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:10:47.106048
377	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:22:14.968228
97	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 11:49:46.972116
385	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:43.562827
393	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:46:48.109544
401	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:51:35.652945
101	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:50:55.259454
409	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:20.782885
417	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:46.320683
425	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:57.192104
105	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:56:12.828344
106	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:56:40.546602
107	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:57:18.495402
433	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:54:02.917225
441	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:01:48.145277
110	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:10:00.69071
111	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:24:23.45382
112	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:38:51.907701
449	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:10:42.338817
457	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:16:07.097917
465	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:20:18.547159
473	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-16 19:51:01.876598
479	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:56:49.785246
491	dashboard	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:00:01.626566
497	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:19:53.608044
503	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:20:27.077732
509	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:21:16.141701
514	logs	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:25:03.962079
519	logs	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:39:51.139505
524	users	\N	LOGOUT	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 22:37:33.235246
541	users	\N	VIEW_LIST	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:10:36.851599
546	dashboard	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:11:02.590231
130	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:41:32.054822
551	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:18.162786
132	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:44:28.57549
330	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 21:59:34.725841
338	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:01:27.712048
135	users	\N	LOGOUT	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:44:35.210524
346	users	\N	LOGIN	\N	\N	109	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 11:40:45.689566
137	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:23:05.834849
138	users	\N	LOGOUT	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:23:07.792913
139	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:25:13.283378
140	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:29:45.177694
141	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:06.529345
142	users	\N	LOGOUT	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:10.93296
354	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:03:40.377767
362	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:09:00.415682
370	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:10:47.810752
378	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:25:22.878873
386	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:44.392749
394	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-16 12:49:20.699291
402	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:51:37.417853
410	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:21.135194
418	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:46.806729
426	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:57.553465
434	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:54:04.488765
442	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:07:11.848924
450	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:10:49.029538
458	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:16:08.007085
466	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:20:19.312949
474	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:51:05.431592
480	dashboard	\N	VIEW	\N	\N	100	::1	PostmanRuntime/7.51.1	2026-02-16 20:01:22.393235
486	dashboard	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 20:42:45.530891
492	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:00:28.106458
498	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:19:54.503048
504	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:20:27.962823
510	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:24:34.277304
515	logs	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:25:14.541437
520	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:39:51.704548
542	logs	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:10:48.349578
547	users	\N	LOGOUT	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:11:05.286267
552	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:20.725654
556	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:25.216504
560	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:31:05.428608
564	dashboard	\N	VIEW	\N	\N	105	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-21 23:34:40.97294
568	users	\N	LOGOUT	\N	\N	105	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:28:53.445407
331	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:00:00.885152
339	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:01:44.477806
347	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 11:42:11.203723
355	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:06:37.594922
363	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:09:03.678812
371	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:10:48.464718
379	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:18.710705
387	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:45.22813
395	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-16 12:49:26.272456
403	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:51:38.186955
411	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:21.665858
419	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:47.872906
427	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:58.067151
435	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:54:05.012726
443	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:07:20.384036
451	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:10:49.574724
459	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:19:29.575805
467	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:20:27.194715
475	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:52:02.141311
481	users	\N	LOGOUT	\N	\N	100	::1	PostmanRuntime/7.51.1	2026-02-16 20:05:05.365947
487	admin	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 20:42:49.269184
493	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:18:39.319734
499	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:20:07.592301
505	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:20:28.66733
511	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:24:39.436214
516	logs	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:25:15.796397
521	dashboard	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:39:52.103015
543	logs	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:10:52.307871
548	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:08.573806
553	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:22.325339
561	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:33:40.336316
565	users	\N	LOGOUT	\N	\N	105	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-21 23:34:56.505753
572	dashboard	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:22:53.764723
575	users	\N	VIEW_LIST	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:38:48.388366
578	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:38:59.973294
581	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:40:26.774544
583	users	\N	VIEW_LIST	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:40:36.048324
332	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:00:06.443066
340	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:01:52.103825
348	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:03:30.781351
356	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:06:38.123185
364	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:09:34.194546
372	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:10:49.011899
380	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:21.618961
388	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:46.078308
396	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:49:28.960415
404	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:51:39.529519
412	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:42.286268
420	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:48.217785
428	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:58.64002
436	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:01:12.401958
444	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:07:23.708669
452	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:10:49.865576
460	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:19:30.353398
468	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:20:29.622077
476	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:52:20.672461
482	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 20:13:23.008271
488	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 20:43:53.55405
494	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:18:47.387976
500	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:20:08.504111
506	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:21:12.06108
512	admin	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:24:52.189606
517	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:37:17.814501
522	admin	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:39:53.965172
539	dashboard	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:05:03.465803
544	logs	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:10:54.940405
549	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:10.161969
554	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:15:24.127284
558	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:30:35.137659
562	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:34:21.258182
566	users	\N	LOGIN	\N	\N	105	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:21:10.362442
573	users	\N	VIEW_LIST	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:22:55.72282
576	users	\N	LOGOUT	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:38:52.477327
580	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:40:24.419506
582	dashboard	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:40:34.339744
584	logs	\N	VIEW	\N	\N	107	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:40:42.385937
333	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:00:10.646454
341	users	107	UPDATE	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:02:11.603158
349	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:03:32.582011
357	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:06:38.722696
365	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:09:35.436302
373	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:10:49.696607
381	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:23.023212
389	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:45:56.988005
397	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:49:29.838812
405	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:17.397863
413	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:42.912267
421	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:48.716637
429	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:54:00.365099
437	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:01:13.755463
445	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:07:28.409252
453	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:12:08.470254
461	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:19:34.38225
469	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:46:44.741111
477	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:52:49.986583
483	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 20:15:17.561038
284	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:06:29.20395
285	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:08:52.749028
286	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:08:55.210647
287	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:08:55.934628
288	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:08:56.506331
289	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:08:57.454987
290	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:09:11.015364
489	dashboard	\N	VIEW	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 20:46:29.084616
495	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:18:58.311573
293	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:13:45.327993
294	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:15:20.803597
295	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:21:29.467527
296	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:21:30.598416
297	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:21:31.455908
298	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:22:40.733285
299	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:23:11.112298
300	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:23:11.808099
301	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:23:12.448165
302	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:23:13.016739
303	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:24:38.335822
501	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:20:08.701527
507	users	\N	VIEW_LIST	\N	\N	1	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:21:12.822193
306	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:25:07.130028
307	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:28:59.722864
308	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:29:01.936685
309	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:31:06.695492
310	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:31:17.96134
311	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:31:18.546966
312	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:31:18.982143
313	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:31:20.192031
314	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:31:21.052053
315	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:17.743728
316	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:18.725473
317	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:19.248212
318	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:19.702453
319	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:20.373356
320	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:20.798261
321	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:21.404164
322	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:22.506967
323	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:23.941607
324	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:25.486865
325	users	101	DELETE	{"age": null, "sex": null, "email": "admin@solayao.com", "country": null, "role_id": 2, "user_id": 101, "zipcode": null, "barangay": null, "birthday": null, "password": "admin123", "province": null, "username": "admin", "id_number": "A-2024-001", "is_active": true, "last_name": "User", "created_at": "2026-02-14 23:08:45.223156", "first_name": "Admin", "last_login": "2026-02-15 17:24:43.001302", "updated_at": "2026-02-15 17:24:43.001302", "middle_name": null, "reset_token": null, "street_purok": null, "city_municipal": null, "contact_number": null, "extension_name": null, "last_reset_attempt": null, "reset_token_expiry": null}	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:39.439084
326	users	106	DELETE	{"age": 25, "sex": "female", "email": "sad@sample.com", "country": "asd", "role_id": 3, "user_id": 106, "zipcode": "5645", "barangay": "sad", "birthday": "2001-02-02", "password": "$2y$12$gCsVVTu9lohzWeCdILr5UOoOAjQbCY3OXnFGw3zLP4.6TMKSDdAWe", "province": "dsad", "username": "sdsf24", "id_number": "5346-8888", "is_active": true, "last_name": "ASDASD", "created_at": "2026-02-14 23:48:54.17741", "first_name": "SADSAD", "last_login": null, "updated_at": "2026-02-14 23:48:54.17741", "middle_name": "", "reset_token": null, "street_purok": "asd", "city_municipal": "asdsa", "contact_number": "09925459077", "extension_name": "", "last_reset_attempt": null, "reset_token_expiry": null}	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 17:32:46.782144
334	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:00:12.929579
342	users	\N	LOGOUT	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 22:02:26.075517
350	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:03:34.4662
358	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:06:39.591647
366	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:09:35.770433
374	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:10:55.689559
382	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:36:25.457125
390	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:45:58.178219
398	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:50:44.490019
406	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:17.998665
414	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:43.716981
422	users	\N	VIEW_LIST	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:53:49.445457
430	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:54:00.774027
438	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:01:14.330027
446	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:07:41.219524
454	logs	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:12:09.68717
462	admin	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 13:19:35.827067
470	dashboard	\N	VIEW	\N	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 19:45:35.142054
579	users	127	DELETE	{"age": 23, "sex": "male", "email": "as24dsad@asda.asd", "country": "Philippines", "role_id": 3, "user_id": 127, "zipcode": "1245", "barangay": "sad", "birthday": "2003-02-02", "password": "$2y$12$l4kriS9Y.avqrDZkaRZDbuCrIdOSk2v4Va.9XoOy.o4hhhG9VMMB2", "province": "asdasdasd", "username": "okjasdlk24", "id_number": "1236-6343", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-21 23:30:08.585231", "created_by": null, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-21 23:30:08.585231", "updated_by": null, "middle_name": "", "permissions": "[]", "reset_token": null, "street_purok": "asd", "city_municipal": "asd", "contact_number": "09245452323", "extension_name": "", "last_reset_attempt": null, "reset_token_expiry": null}	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:40:24.320363
585	users	9	DELETE	{"age": 22, "sex": "female", "email": "asdsad1@asda.asd", "country": "Philippines", "role_id": 3, "user_id": 9, "zipcode": "1245", "barangay": "aasdasd", "birthday": "2003-02-02", "password": "$2y$12$jmdWcAzWJhJxtIMIeSFtJOkn2R3DOPjkNh6HU5uoPiWozrJe57nFq", "province": "asdasdasd", "username": "asdasd24", "id_number": "5944-6418", "is_active": true, "last_name": "asdasd", "created_at": "2026-01-24 13:03:05.338617", "created_by": null, "first_name": "asdasd", "last_login": "2026-02-22 00:59:14.020881", "updated_at": "2026-02-22 00:59:14.020881", "updated_by": null, "middle_name": "", "permissions": "[]", "reset_token": null, "street_purok": "asd", "city_municipal": "asd", "contact_number": "09925450728", "extension_name": "", "last_reset_attempt": null, "reset_token_expiry": null}	\N	100	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 02:31:00.346252
586	users	128	INSERT	\N	{"email": "florence2424@gmail.com", "role_id": 3, "user_id": "128", "username": "florence24", "last_name": "solayao", "first_name": "florence"}	128	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-24 00:33:56.867077
587	users	\N	LOGOUT	\N	\N	128	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-24 00:41:39.646815
\.


--
-- Data for Name: admin_activity_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.admin_activity_logs (log_id, admin_id, username, role, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, endpoint, method, status_code, execution_time_ms, created_at) FROM stdin;
1	\N	\N	\N	CREATE	admin_users	1	\N	{"role": "super_admin", "email": "superadmin@yourdomain.com", "user_id": null, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-14T21:17:21.744849", "updated_by": null, "permissions": ["all"], "password_hash": "$2a$10$YourHashedPasswordHere"}	::1	\N	\N	\N	\N	\N	2026-02-14 21:17:21.744849
2	\N	\N	\N	CREATE	admin_users	2	\N	{"role": "admin", "email": "john@example.com", "user_id": null, "admin_id": 2, "username": "john_admin", "is_active": true, "last_name": "Doe", "created_at": "2026-02-14T21:17:51.006783", "created_by": 1, "first_name": "John", "last_login": null, "updated_at": "2026-02-14T21:17:51.006783", "updated_by": null, "permissions": {"modules": {"users": ["view", "create", "update"], "reports": ["view"]}}, "password_hash": "$2a$10$HashedPassword123"}	::1	\N	\N	\N	\N	\N	2026-02-14 21:17:51.006783
3	\N	\N	\N	CREATE	admin_users	6	\N	{"role": "admin", "email": "asdsad@asda.asd", "user_id": null, "admin_id": 6, "username": "crissaunt24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:33:29.996088", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T13:33:29.996088", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$9fCkt5pxk0sHW7i/4SPAG.k0aw/yixUJagDev4nmLfOqPl8zLJc/a"}	::1	\N	\N	\N	\N	\N	2026-02-16 13:33:29.996088
4	\N	\N	\N	CREATE	admin_users	11	\N	{"role": "admin", "email": "asdsad24@asda.asd", "user_id": null, "admin_id": 11, "username": "crissaunt123", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:34:00.910862", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T13:34:00.910862", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$4niAaL7VhW0BHaHdtsuehe8od1Mr0XpJV2uqztflqh2.hqOkIo41a"}	::1	\N	\N	\N	\N	\N	2026-02-16 13:34:00.910862
5	\N	\N	\N	CREATE	admin_users	16	\N	{"role": "admin", "email": "asdsa11d@asda.asd", "user_id": null, "admin_id": 16, "username": "admin24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:39:24.17582", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T13:39:24.17582", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$V92xAeKUKaLq4uogv9bwbuO3XGS1hsef2T50uwo/HbLM0KhKwCbxK"}	::1	\N	\N	\N	\N	\N	2026-02-16 13:39:24.17582
6	1	\N	\N	INSERT	admin_users	5	\N	{"role": "admin", "email": "asdsa11d@asda.asd", "username": "admin24"}	::1	\N	\N	\N	\N	\N	2026-02-16 13:39:24.189273
7	\N	\N	\N	UPDATE	admin_users	2	{"role": "admin", "email": "john@example.com", "user_id": null, "admin_id": 2, "username": "john_admin", "is_active": true, "last_name": "Doe", "created_at": "2026-02-14T21:17:51.006783", "created_by": 1, "first_name": "John", "last_login": null, "updated_at": "2026-02-14T21:17:51.006783", "updated_by": null, "permissions": {"modules": {"users": ["view", "create", "update"], "reports": ["view"]}}, "password_hash": "$2a$10$HashedPassword123"}	{"role": "admin", "email": "john@example.com", "user_id": null, "admin_id": 2, "username": "john_admin", "is_active": true, "last_name": "Doe", "created_at": "2026-02-14T21:17:51.006783", "created_by": 1, "first_name": "John", "last_login": null, "updated_at": "2026-02-16T13:41:47.195955", "updated_by": 1, "permissions": ["manage_users", "view_logs"], "password_hash": "$2a$10$HashedPassword123"}	::1	\N	\N	\N	\N	\N	2026-02-16 13:41:47.195955
8	1	\N	\N	UPDATE	admin_users	2	{"role": "admin", "email": "john@example.com", "user_id": null, "admin_id": 2, "username": "john_admin", "is_active": true, "last_name": "Doe", "created_at": "2026-02-14 21:17:51.006783", "created_by": 1, "first_name": "John", "last_login": null, "updated_at": "2026-02-14 21:17:51.006783", "updated_by": null, "permissions": "{\\"modules\\": {\\"users\\": [\\"view\\", \\"create\\", \\"update\\"], \\"reports\\": [\\"view\\"]}}", "password_hash": "$2a$10$HashedPassword123"}	{"email": "john@example.com", "admin_id": "2", "username": "john_admin", "is_active": "on", "last_name": "Doe", "admin_role": "admin", "edit_admin": "", "first_name": "John", "new_password": ""}	::1	\N	\N	\N	\N	\N	2026-02-16 13:41:47.208005
9	\N	\N	\N	CREATE	admin_users	29	\N	{"role": "admin", "email": "asdsad2412@asda.asd", "user_id": null, "admin_id": 29, "username": "cris", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:52:00.441021", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T19:52:00.441021", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$q1iNmgp9gaDd482iBH0AkeRKGaMyGnX5anGsVoFuqXubceyWXd8WS"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:52:00.441021
10	1	\N	\N	INSERT	admin_users	9	\N	{"role": "admin", "email": "asdsad2412@asda.asd", "username": "cris"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:52:00.453662
11	\N	\N	\N	UPDATE	admin_users	11	{"role": "admin", "email": "asdsad24@asda.asd", "user_id": null, "admin_id": 11, "username": "crissaunt123", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:34:00.910862", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T13:34:00.910862", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$4niAaL7VhW0BHaHdtsuehe8od1Mr0XpJV2uqztflqh2.hqOkIo41a"}	{"role": "admin", "email": "asdsad24@asda.asd", "user_id": null, "admin_id": 11, "username": "crissaunt123", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:34:00.910862", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T19:52:47.668909", "updated_by": 1, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$9W/XLnGfve02L8Zzef2cz.fJmzFw29BHZcdXeAg4lLiBv4tXQj0uq"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:52:47.668909
12	1	\N	\N	UPDATE	admin_users	11	{"role": "admin", "email": "asdsad24@asda.asd", "user_id": null, "admin_id": 11, "username": "crissaunt123", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16 13:34:00.910862", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16 13:34:00.910862", "updated_by": null, "permissions": "[\\"manage_users\\", \\"view_logs\\"]", "password_hash": "$2y$12$4niAaL7VhW0BHaHdtsuehe8od1Mr0XpJV2uqztflqh2.hqOkIo41a"}	{"email": "asdsad24@asda.asd", "admin_id": "11", "username": "crissaunt123", "is_active": "on", "last_name": "asdasd", "admin_role": "admin", "edit_admin": "", "first_name": "asdasd", "new_password": "cris"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:52:47.676343
53	107	\N	\N	TOGGLE_STATUS	users	3	{"is_active": true}	{"is_active": false}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-22 03:28:46.782118
86	107	\N	\N	TOGGLE_STATUS	users	128	{"is_active": true}	{"is_active": false}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:01:12.968021
90	107	\N	\N	TOGGLE_STATUS	users	10	{"is_active": true}	{"is_active": false}	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	\N	\N	\N	\N	2026-02-24 02:14:04.658231
13	\N	\N	\N	UPDATE	admin_users	1	{"role": "super_admin", "email": "superadmin@yourdomain.com", "user_id": null, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-14T21:17:21.744849", "updated_by": null, "permissions": ["all"], "password_hash": "$2a$10$YourHashedPasswordHere"}	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T19:54:40.199341", "updated_by": null, "permissions": ["all"], "password_hash": "$2a$10$YourHashedPasswordHere"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:54:40.199341
14	\N	\N	\N	CREATE	admin_users	33	\N	{"role": "admin", "email": "instrcutor@asda.asd", "user_id": null, "admin_id": 33, "username": "instructor", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:55:01.537435", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T19:55:01.537435", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$3mJ4ApnSgtVyPOOJXQ1Lzu5QVCnHuIgtTdKSxHLWg7lQeclGS0kFi"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:55:01.537435
15	1	\N	\N	INSERT	admin_users	14	\N	{"role": "admin", "email": "instrcutor@asda.asd", "username": "instructor"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:55:01.55494
16	\N	\N	\N	CREATE	admin_users	34	\N	{"role": "admin", "email": "admin0909@asda.asd", "user_id": null, "admin_id": 34, "username": "admin0909", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:55:36.506057", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T19:55:36.506057", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$p67DVlrt9KbZebSoeUz36Oa9tGXQ4A4mZUGynkm0883KZf5U6vx/a"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:55:36.506057
17	1	\N	\N	INSERT	admin_users	16	\N	{"role": "admin", "email": "admin0909@asda.asd", "username": "admin0909"}	::1	\N	\N	\N	\N	\N	2026-02-16 19:55:36.511511
18	\N	\N	\N	UPDATE	admin_users	1	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T19:54:40.199341", "updated_by": null, "permissions": ["all"], "password_hash": "$2a$10$YourHashedPasswordHere"}	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:01:22.306696", "updated_by": null, "permissions": ["all"], "password_hash": "$2a$10$YourHashedPasswordHere"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:01:22.306696
19	\N	\N	\N	CREATE	admin_users	35	\N	{"role": "admin", "email": "postman@man.com", "user_id": null, "admin_id": 35, "username": "cris_admin", "is_active": true, "last_name": "tester", "created_at": "2026-02-16T20:03:34.96575", "created_by": 1, "first_name": "postman", "last_login": null, "updated_at": "2026-02-16T20:03:34.96575", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$brGh70C0DXaVpCsD3B0MNew5KgubcMnHSoYg2D4AzOTp9twx2CwwC"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:03:34.96575
20	1	\N	\N	INSERT	admin_users	19	\N	{"role": "admin", "email": "postman@man.com", "username": "cris_admin"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:03:34.969777
21	\N	\N	\N	UPDATE	admin_users	1	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:01:22.306696", "updated_by": null, "permissions": ["all"], "password_hash": "$2a$10$YourHashedPasswordHere"}	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:13:22.930003", "updated_by": null, "permissions": ["all"], "password_hash": "$2a$10$YourHashedPasswordHere"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:13:22.930003
22	\N	\N	\N	CREATE	admin_users	36	\N	{"role": "super_admin", "email": "superadmin_new@solayao.com", "user_id": 112, "admin_id": 36, "username": "superadmin2", "is_active": true, "last_name": "Admin", "created_at": "2026-02-16T20:25:31.420737", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:25:31.420737", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:25:31.420737
23	\N	\N	\N	CREATE	admin_users	37	\N	{"role": "admin", "email": "regular1@example.com", "user_id": 113, "admin_id": 37, "username": "regularadmin1", "is_active": true, "last_name": "Admin", "created_at": "2026-02-16T20:29:40.908667", "created_by": null, "first_name": "Regular", "last_login": null, "updated_at": "2026-02-16T20:29:40.908667", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:29:40.908667
24	\N	\N	\N	UPDATE	admin_users	1	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:13:22.930003", "updated_by": null, "permissions": ["all"], "password_hash": "$2a$10$YourHashedPasswordHere"}	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:38:46.628514", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:38:46.628514
54	107	\N	\N	TOGGLE_STATUS	users	3	{"is_active": false}	{"is_active": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-22 03:28:50.13255
87	107	\N	\N	TOGGLE_STATUS	users	8	{"is_active": true}	{"is_active": false}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:13:27.646415
91	107	\N	\N	UPDATE	users	3	\N	{"email": "asdasd24@asd.com", "role_id": "3", "user_id": "3", "birthday": "2003-02-02", "username": "sfasf24", "edit_user": "", "id_number": "1245-6542", "is_active": "on", "last_name": "Asdasd", "first_name": "Asdasd", "edit_reason": "", "new_password": "", "contact_number": "09925450720"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:27:42.23048
25	\N	\N	\N	UPDATE	admin_users	6	{"role": "admin", "email": "asdsad@asda.asd", "user_id": null, "admin_id": 6, "username": "crissaunt24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:33:29.996088", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T13:33:29.996088", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$9fCkt5pxk0sHW7i/4SPAG.k0aw/yixUJagDev4nmLfOqPl8zLJc/a"}	{"role": "admin", "email": "asdsad@asda.asd", "user_id": null, "admin_id": 6, "username": "crissaunt24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:33:29.996088", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:38:46.648658", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:38:46.648658
26	\N	\N	\N	UPDATE	admin_users	1	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:38:46.628514", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8"}	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:40:22.787027", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:40:22.787027
27	\N	\N	\N	UPDATE	admin_users	16	{"role": "admin", "email": "asdsa11d@asda.asd", "user_id": null, "admin_id": 16, "username": "admin24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:39:24.17582", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T13:39:24.17582", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$V92xAeKUKaLq4uogv9bwbuO3XGS1hsef2T50uwo/HbLM0KhKwCbxK"}	{"role": "admin", "email": "asdsa11d@asda.asd", "user_id": null, "admin_id": 16, "username": "admin24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:39:24.17582", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
28	\N	\N	\N	UPDATE	admin_users	2	{"role": "admin", "email": "john@example.com", "user_id": null, "admin_id": 2, "username": "john_admin", "is_active": true, "last_name": "Doe", "created_at": "2026-02-14T21:17:51.006783", "created_by": 1, "first_name": "John", "last_login": null, "updated_at": "2026-02-16T13:41:47.195955", "updated_by": 1, "permissions": ["manage_users", "view_logs"], "password_hash": "$2a$10$HashedPassword123"}	{"role": "admin", "email": "john@example.com", "user_id": null, "admin_id": 2, "username": "john_admin", "is_active": true, "last_name": "Doe", "created_at": "2026-02-14T21:17:51.006783", "created_by": 1, "first_name": "John", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": 1, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
29	\N	\N	\N	UPDATE	admin_users	29	{"role": "admin", "email": "asdsad2412@asda.asd", "user_id": null, "admin_id": 29, "username": "cris", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:52:00.441021", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T19:52:00.441021", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$q1iNmgp9gaDd482iBH0AkeRKGaMyGnX5anGsVoFuqXubceyWXd8WS"}	{"role": "admin", "email": "asdsad2412@asda.asd", "user_id": null, "admin_id": 29, "username": "cris", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:52:00.441021", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
30	\N	\N	\N	UPDATE	admin_users	11	{"role": "admin", "email": "asdsad24@asda.asd", "user_id": null, "admin_id": 11, "username": "crissaunt123", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:34:00.910862", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T19:52:47.668909", "updated_by": 1, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$9W/XLnGfve02L8Zzef2cz.fJmzFw29BHZcdXeAg4lLiBv4tXQj0uq"}	{"role": "admin", "email": "asdsad24@asda.asd", "user_id": null, "admin_id": 11, "username": "crissaunt123", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:34:00.910862", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": 1, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
31	\N	\N	\N	UPDATE	admin_users	33	{"role": "admin", "email": "instrcutor@asda.asd", "user_id": null, "admin_id": 33, "username": "instructor", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:55:01.537435", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T19:55:01.537435", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$3mJ4ApnSgtVyPOOJXQ1Lzu5QVCnHuIgtTdKSxHLWg7lQeclGS0kFi"}	{"role": "admin", "email": "instrcutor@asda.asd", "user_id": null, "admin_id": 33, "username": "instructor", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:55:01.537435", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
55	107	\N	\N	TOGGLE_STATUS	users	10	{"is_active": true}	{"is_active": false}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-22 03:29:22.425113
88	107	\N	\N	TOGGLE_STATUS	users	8	{"is_active": false}	{"is_active": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:13:30.026074
32	\N	\N	\N	UPDATE	admin_users	34	{"role": "admin", "email": "admin0909@asda.asd", "user_id": null, "admin_id": 34, "username": "admin0909", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:55:36.506057", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T19:55:36.506057", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$p67DVlrt9KbZebSoeUz36Oa9tGXQ4A4mZUGynkm0883KZf5U6vx/a"}	{"role": "admin", "email": "admin0909@asda.asd", "user_id": null, "admin_id": 34, "username": "admin0909", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T19:55:36.506057", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
33	\N	\N	\N	UPDATE	admin_users	6	{"role": "admin", "email": "asdsad@asda.asd", "user_id": null, "admin_id": 6, "username": "crissaunt24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:33:29.996088", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:38:46.648658", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8"}	{"role": "admin", "email": "asdsad@asda.asd", "user_id": null, "admin_id": 6, "username": "crissaunt24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:33:29.996088", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
34	\N	\N	\N	UPDATE	admin_users	35	{"role": "admin", "email": "postman@man.com", "user_id": null, "admin_id": 35, "username": "cris_admin", "is_active": true, "last_name": "tester", "created_at": "2026-02-16T20:03:34.96575", "created_by": 1, "first_name": "postman", "last_login": null, "updated_at": "2026-02-16T20:03:34.96575", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$brGh70C0DXaVpCsD3B0MNew5KgubcMnHSoYg2D4AzOTp9twx2CwwC"}	{"role": "admin", "email": "postman@man.com", "user_id": null, "admin_id": 35, "username": "cris_admin", "is_active": true, "last_name": "tester", "created_at": "2026-02-16T20:03:34.96575", "created_by": 1, "first_name": "postman", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
35	\N	\N	\N	UPDATE	admin_users	1	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:40:22.787027", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
36	\N	\N	\N	UPDATE	admin_users	36	{"role": "super_admin", "email": "superadmin_new@solayao.com", "user_id": 112, "admin_id": 36, "username": "superadmin2", "is_active": true, "last_name": "Admin", "created_at": "2026-02-16T20:25:31.420737", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:25:31.420737", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8"}	{"role": "super_admin", "email": "superadmin_new@solayao.com", "user_id": 112, "admin_id": 36, "username": "superadmin2", "is_active": true, "last_name": "Admin", "created_at": "2026-02-16T20:25:31.420737", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
37	\N	\N	\N	UPDATE	admin_users	37	{"role": "admin", "email": "regular1@example.com", "user_id": 113, "admin_id": 37, "username": "regularadmin1", "is_active": true, "last_name": "Admin", "created_at": "2026-02-16T20:29:40.908667", "created_by": null, "first_name": "Regular", "last_login": null, "updated_at": "2026-02-16T20:29:40.908667", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8"}	{"role": "admin", "email": "regular1@example.com", "user_id": 113, "admin_id": 37, "username": "regularadmin1", "is_active": true, "last_name": "Admin", "created_at": "2026-02-16T20:29:40.908667", "created_by": null, "first_name": "Regular", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:41:22.051024
38	\N	\N	\N	UPDATE	admin_users	6	{"role": "admin", "email": "asdsad@asda.asd", "user_id": null, "admin_id": 6, "username": "crissaunt24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:33:29.996088", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	{"role": "admin", "email": "asdsad@asda.asd", "user_id": null, "admin_id": 6, "username": "crissaunt24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:33:29.996088", "created_by": 1, "first_name": "asdasd", "last_login": "2026-02-16T20:42:25.545712", "updated_at": "2026-02-16T20:42:25.545712", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:42:25.545712
89	107	\N	\N	TOGGLE_STATUS	users	10	{"is_active": false}	{"is_active": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:13:34.494578
92	107	\N	\N	UPDATE	users	8	\N	{"email": "asdsad24@asda.asd", "role_id": "3", "user_id": "8", "birthday": "2003-02-02", "username": "dasd24", "edit_user": "", "id_number": "5944-6411", "is_active": "on", "last_name": "asdasd", "first_name": "asdasd", "edit_reason": "zxc", "new_password": "", "contact_number": "09945450785"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:28:08.140097
39	\N	\N	\N	UPDATE	admin_users	1	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": "2026-02-16T20:42:45.353552", "updated_at": "2026-02-16T20:42:45.353552", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:42:45.353552
40	\N	\N	\N	CREATE	admin_users	38	\N	{"role": "super_admin", "email": "test@example.com", "user_id": 1, "admin_id": 38, "username": "testuser123", "is_active": true, "last_name": "Doe", "created_at": "2026-02-16T20:42:51.048693", "created_by": null, "first_name": "John", "last_login": null, "updated_at": "2026-02-16T20:42:51.048693", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$/.wJrZRGK/JGnDjzDn8ztuBdmnFQRCXcsMmzrX0EYyj5yXJeK7hqS"}	::1	\N	\N	\N	\N	\N	2026-02-16 20:42:51.048693
41	\N	\N	\N	UPDATE	admin_users	1	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": "2026-02-16T20:42:45.353552", "updated_at": "2026-02-16T20:42:45.353552", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	{"role": "super_admin", "email": "superadmin@solayao.com", "user_id": 100, "admin_id": 1, "username": "superadmin", "is_active": true, "last_name": "Admin", "created_at": "2026-02-14T21:17:21.744849", "created_by": null, "first_name": "Super", "last_login": "2026-02-16T22:37:23.798567", "updated_at": "2026-02-16T22:37:23.798567", "updated_by": null, "permissions": ["all"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 22:37:23.798567
42	\N	\N	\N	UPDATE	admin_users	16	{"role": "admin", "email": "asdsa11d@asda.asd", "user_id": null, "admin_id": 16, "username": "admin24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:39:24.17582", "created_by": 1, "first_name": "asdasd", "last_login": null, "updated_at": "2026-02-16T20:41:22.051024", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	{"role": "admin", "email": "asdsa11d@asda.asd", "user_id": null, "admin_id": 16, "username": "admin24", "is_active": true, "last_name": "asdasd", "created_at": "2026-02-16T13:39:24.17582", "created_by": 1, "first_name": "asdasd", "last_login": "2026-02-16T22:37:42.044298", "updated_at": "2026-02-16T22:37:42.044298", "updated_by": null, "permissions": ["manage_users", "view_logs"], "password_hash": "$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q"}	::1	\N	\N	\N	\N	\N	2026-02-16 22:37:42.044298
50	100	\N	\N	TOGGLE_STATUS	users	113	{"is_active": true}	{"is_active": "false"}	::1	\N	\N	\N	\N	\N	2026-02-22 03:11:35.326745
51	100	\N	\N	TOGGLE_STATUS	users	113	{"is_active": 0}	{"is_active": 1}	::1	\N	\N	\N	\N	\N	2026-02-22 03:15:55.636126
52	100	\N	\N	TOGGLE_STATUS	users	107	{"is_active": 0}	{"is_active": 1}	::1	\N	\N	\N	\N	\N	2026-02-22 03:15:59.495955
93	107	\N	\N	UPDATE	users	10	\N	{"email": "asdsad@53asda.asd", "country": "Philippines", "role_id": "3", "user_id": "10", "zipcode": "1245", "barangay": "asdasd", "birthday": "2001-02-02", "province": "asdasdasd", "username": "asdas24", "edit_user": "", "id_number": "4142-1245", "last_name": "asdasd", "first_name": "asdasd", "edit_reason": "zxcxz", "new_password": "", "street_purok": "asd", "city_municipal": "asd", "contact_number": "09925450766"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:34:12.605729
94	100	\N	\N	UPDATE_APPROVED	users	8	\N	{"sex": "Female", "email": "asdsad24@asda.asd", "country": "Philippines", "role_id": 3, "zipcode": "1230", "barangay": "", "birthday": "2003-02-02", "province": "Z", "username": "dasd24", "id_number": "5944-6411", "is_active": 1, "last_name": "asdasd", "first_name": "asdasd", "middle_name": "", "street_purok": "", "city_municipal": "Zx", "contact_number": "09945450785", "extension_name": ""}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:36:57.278127
95	100	\N	\N	DELETE	users	3	{"age": 23, "sex": null, "email": "asdasd24@asd.com", "country": "Philippines", "role_id": 3, "user_id": 3, "zipcode": null, "barangay": null, "birthday": "2003-02-02", "password": "$2y$12$zwRmjpvPODgrggqh8SbaQ.71P9R9h0dH8/I0qoUl4wXwtFepYzrP6", "province": null, "username": "sfasf24", "id_number": "1245-6542", "is_active": true, "last_name": "Asdasd", "created_at": "2026-01-24 12:01:14.059878", "created_by": null, "first_name": "Asdasd", "last_login": null, "updated_at": "2026-02-24 02:27:42.228212", "updated_by": null, "middle_name": null, "permissions": "[]", "reset_token": null, "street_purok": null, "city_municipal": null, "contact_number": "09925450720", "extension_name": null, "last_reset_attempt": null, "reset_token_expiry": null}	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	\N	\N	\N	\N	2026-02-24 02:37:01.68145
\.


--
-- Data for Name: admin_login_attempts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.admin_login_attempts (attempt_id, username, ip_address, success, failure_reason, created_at) FROM stdin;
1	superadmin	::1	f	\N	2026-02-16 20:33:03.988966
2	superadmin2	::1	f	\N	2026-02-16 20:37:11.231464
3	crissaunt24	::1	f	\N	2026-02-16 20:37:28.667117
4	superadmin	::1	f	\N	2026-02-16 20:37:43.968509
5	john_admin	::1	f	\N	2026-02-16 20:39:04.767748
6	superadmin	::1	f	\N	2026-02-16 20:41:55.101338
7	crissaunt24	::1	f	\N	2026-02-16 20:42:18.663802
8	crissaunt24	::1	t	\N	2026-02-16 20:42:25.557479
9	superadmin	::1	t	\N	2026-02-16 20:42:45.359213
10	superadmin	::1	f	\N	2026-02-16 22:35:45.485627
11	superadmin	::1	f	\N	2026-02-16 22:35:51.679481
12	superadmin	::1	f	\N	2026-02-16 22:36:13.879777
13	superadmin	::1	f	\N	2026-02-16 22:36:18.759033
14	superadmin2	::1	f	\N	2026-02-16 22:36:53.075475
15	superadmin1	::1	f	\N	2026-02-16 22:36:58.913635
16	superadmin1	::1	f	\N	2026-02-16 22:37:19.120322
17	superadmin	::1	t	\N	2026-02-16 22:37:23.856526
18	admin	::1	f	\N	2026-02-16 22:37:37.114361
19	admin24	::1	t	\N	2026-02-16 22:37:42.055491
\.


--
-- Data for Name: admin_users_backup; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.admin_users_backup (admin_id, user_id, username, email, password_hash, first_name, last_name, role, permissions, is_active, last_login, created_at, updated_at, created_by, updated_by) FROM stdin;
2	\N	john_admin	john@example.com	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	John	Doe	admin	["manage_users", "view_logs"]	t	\N	2026-02-14 21:17:51.006783	2026-02-16 20:41:22.051024	1	1
29	\N	cris	asdsad2412@asda.asd	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	asdasd	asdasd	admin	["manage_users", "view_logs"]	t	\N	2026-02-16 19:52:00.441021	2026-02-16 20:41:22.051024	1	\N
11	\N	crissaunt123	asdsad24@asda.asd	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	asdasd	asdasd	admin	["manage_users", "view_logs"]	t	\N	2026-02-16 13:34:00.910862	2026-02-16 20:41:22.051024	1	1
33	\N	instructor	instrcutor@asda.asd	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	asdasd	asdasd	admin	["manage_users", "view_logs"]	t	\N	2026-02-16 19:55:01.537435	2026-02-16 20:41:22.051024	1	\N
34	\N	admin0909	admin0909@asda.asd	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	asdasd	asdasd	admin	["manage_users", "view_logs"]	t	\N	2026-02-16 19:55:36.506057	2026-02-16 20:41:22.051024	1	\N
35	\N	cris_admin	postman@man.com	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	postman	tester	admin	["manage_users", "view_logs"]	t	\N	2026-02-16 20:03:34.96575	2026-02-16 20:41:22.051024	1	\N
36	112	superadmin2	superadmin_new@solayao.com	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	Super	Admin	super_admin	["all"]	t	\N	2026-02-16 20:25:31.420737	2026-02-16 20:41:22.051024	\N	\N
37	113	regularadmin1	regular1@example.com	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	Regular	Admin	admin	["manage_users", "view_logs"]	t	\N	2026-02-16 20:29:40.908667	2026-02-16 20:41:22.051024	\N	\N
6	\N	crissaunt24	asdsad@asda.asd	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	asdasd	asdasd	admin	["manage_users", "view_logs"]	t	2026-02-16 20:42:25.545712	2026-02-16 13:33:29.996088	2026-02-16 20:42:25.545712	1	\N
38	1	testuser123	test@example.com	$2y$12$/.wJrZRGK/JGnDjzDn8ztuBdmnFQRCXcsMmzrX0EYyj5yXJeK7hqS	John	Doe	super_admin	["all"]	t	\N	2026-02-16 20:42:51.048693	2026-02-16 20:42:51.048693	\N	\N
1	100	superadmin	superadmin@solayao.com	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	Super	Admin	super_admin	["all"]	t	2026-02-16 22:37:23.798567	2026-02-14 21:17:21.744849	2026-02-16 22:37:23.798567	\N	\N
16	\N	admin24	asdsa11d@asda.asd	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	asdasd	asdasd	admin	["manage_users", "view_logs"]	t	2026-02-16 22:37:42.044298	2026-02-16 13:39:24.17582	2026-02-16 22:37:42.044298	1	\N
\.


--
-- Data for Name: deletion_requests; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.deletion_requests (request_id, user_id, requested_by, reason, status, created_at, updated_at, reviewed_by, review_notes) FROM stdin;
4	2	107	lkl	pending	2026-02-24 06:59:01.610332	2026-02-24 06:59:01.610332	\N	\N
\.


--
-- Data for Name: edit_requests; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.edit_requests (request_id, user_id, requested_by, requested_data, reason, status, reviewed_by, review_notes, created_at, updated_at) FROM stdin;
1	8	107	{"sex": "Female", "email": "asdsad24@asda.asd", "country": "Philippines", "role_id": 3, "zipcode": "1230", "barangay": "", "birthday": "2003-02-02", "province": "Z", "username": "dasd24", "id_number": "5944-6411", "is_active": 1, "last_name": "asdasd", "first_name": "asdasd", "middle_name": "", "street_purok": "", "city_municipal": "Zx", "contact_number": "09945450785", "extension_name": ""}	xcxvcxv	approved	100		2026-02-24 02:36:35.080145+08	2026-02-24 02:36:57.278127+08
2	8	107	{"sex": "Female", "email": "asdsad24@asda.asd", "country": "Philippines", "role_id": 3, "zipcode": "1230", "barangay": "", "birthday": "2003-02-02", "province": "Z", "username": "dasd24", "id_number": "5944-6411", "is_active": 1, "last_name": "asdasd", "first_name": "asdasd", "middle_name": "", "street_purok": "", "city_municipal": "Zx", "contact_number": "09945450785", "extension_name": ""}		pending	\N	\N	2026-02-24 06:58:52.521607+08	2026-02-24 06:58:52.521607+08
\.


--
-- Data for Name: login_attempts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.login_attempts (attempt_id, user_id, username, ip_address, success, attempt_time) FROM stdin;
3	100	superadmin	::1	t	2026-02-14 23:14:41.377948
4	\N	superadmin 	::1	f	2026-02-14 23:20:41.346421
5	100	superadmin	::1	t	2026-02-14 23:20:52.877827
6	100	superadmin	::1	t	2026-02-14 23:21:34.655859
9	\N	superadmin 	::1	f	2026-02-15 11:10:12.622074
10	\N	superadmin123	::1	f	2026-02-15 11:10:18.929639
11	\N	superadmin 	::1	f	2026-02-15 11:10:24.387665
12	100	superadmin	::1	t	2026-02-15 11:10:38.369654
14	\N	asd	::1	f	2026-02-15 11:49:40
15	105	crissaunt24	::1	t	2026-02-15 11:49:46
16	\N	asd	::1	f	2026-02-15 11:50:13
17	105	crissaunt24	::1	f	2026-02-15 11:50:18
18	105	crissaunt24	::1	t	2026-02-15 11:50:55
19	\N	asd	::1	f	2026-02-15 11:52:11
20	\N	asd	::1	f	2026-02-15 11:52:14
21	105	crissaunt24	::1	f	2026-02-15 11:52:22
22	105	crissaunt24	::1	t	2026-02-15 11:56:12
23	105	crissaunt24	::1	t	2026-02-15 11:56:40
24	105	crissaunt24	::1	f	2026-02-15 11:57:14
25	105	crissaunt24	::1	t	2026-02-15 11:57:18
26	105	crissaunt24	::1	t	2026-02-15 12:10:00
27	105	crissaunt24	::1	t	2026-02-15 12:24:23
28	\N	asd	::1	f	2026-02-15 12:24:37
29	\N	asd	::1	f	2026-02-15 12:25:06
30	\N	asd	::1	f	2026-02-15 12:25:23
31	\N	asd	::1	f	2026-02-15 12:26:03
32	\N	asd	::1	f	2026-02-15 12:31:36
33	\N	asd	::1	f	2026-02-15 12:31:49
34	\N	::1	::1	f	2026-02-15 12:32:03
35	\N	asd	::1	f	2026-02-15 12:33:58
36	\N	asd	::1	f	2026-02-15 12:34:10
37	\N	asd	::1	f	2026-02-15 12:36:25
38	\N	asd	::1	f	2026-02-15 12:37:06
39	\N	asd	::1	f	2026-02-15 12:38:20
40	\N	asd	::1	f	2026-02-15 12:38:25
41	105	crissaunt24	::1	t	2026-02-15 12:38:51
42	105	crissaunt24	::1	t	2026-02-15 12:41:32
43	105	crissaunt24	::1	t	2026-02-15 12:44:28
44	\N	asd	::1	f	2026-02-15 13:22:41
45	105	crissaunt24	::1	t	2026-02-15 13:23:05
47	105	crissaunt24	::1	t	2026-02-15 13:25:13
48	\N	asd	::1	f	2026-02-15 13:29:37
49	105	crissaunt24	::1	t	2026-02-15 13:29:45
50	\N	asd	::1	f	2026-02-15 13:35:59
51	105	crissaunt24	::1	t	2026-02-15 13:36:06
52	\N	asd	::1	f	2026-02-15 13:36:16
53	\N	asd	::1	f	2026-02-15 13:36:18
54	\N	asd	::1	f	2026-02-15 13:36:20
55	\N	asd	::1	f	2026-02-15 13:36:37
56	\N	asd	::1	f	2026-02-15 13:36:38
57	\N	asd	::1	f	2026-02-15 13:36:41
58	\N	asd	::1	f	2026-02-15 13:37:13
59	\N	asd	::1	f	2026-02-15 13:37:18
60	\N	asdas	::1	f	2026-02-15 13:37:20
61	\N	asd	::1	f	2026-02-15 13:39:23
62	\N	crissaunt24	::1	f	2026-02-15 17:06:00.681314
63	100	superadmin	::1	t	2026-02-15 17:06:29.030298
65	100	superadmin	::1	t	2026-02-15 17:13:45.269057
67	100	superadmin	::1	t	2026-02-15 17:25:06.959473
68	\N	admin	::1	f	2026-02-15 21:58:20.486928
69	\N	admin	::1	f	2026-02-15 21:58:24.961716
70	\N	admin	::1	f	2026-02-15 21:58:32.753847
71	\N	admin	::1	f	2026-02-15 21:58:46.694437
72	100	superadmin	::1	t	2026-02-15 21:59:02.618003
73	\N	admin	::1	f	2026-02-15 22:01:00.185663
74	\N	admin	::1	f	2026-02-15 22:01:06.51692
75	100	superadmin	::1	t	2026-02-15 22:01:16.381894
76	\N	admin	::1	f	2026-02-15 22:01:31.167173
77	\N	admin	::1	f	2026-02-15 22:01:33.581297
78	\N	admin	::1	f	2026-02-15 22:01:35.303338
79	100	superadmin	::1	t	2026-02-15 22:01:44.42449
80	\N	admin	::1	f	2026-02-15 22:02:29.485856
81	\N	superadmin	::1	f	2026-02-15 22:06:35.383481
82	100	superadmin	::1	t	2026-02-15 22:06:40.915752
83	109	crissaunt123	::1	t	2026-02-16 11:40:45
84	\N	admin	::1	f	2026-02-16 11:41:45.663843
85	\N	admin24	::1	f	2026-02-16 11:41:52.02784
86	\N	admin24	::1	f	2026-02-16 11:41:54.801293
87	\N	admin123	::1	f	2026-02-16 11:41:59.715861
88	100	superadmin	::1	t	2026-02-16 11:42:11.140668
89	100	superadmin	::1	t	2026-02-16 12:36:18.654897
90	100	superadmin	::1	t	2026-02-16 13:19:49.064625
91	100	superadmin	::1	t	2026-02-16 13:20:27.012102
92	\N	crissaunt24	::1	f	2026-02-16 19:42:48.428257
93	\N	crissaunt24	::1	f	2026-02-16 19:45:11.589942
94	100	superadmin	::1	t	2026-02-16 19:45:34.770961
95	\N	crissaunt123	::1	f	2026-02-16 19:46:25.574939
96	\N	crissaunt24	::1	f	2026-02-16 19:49:25.643684
97	\N	crissaunt24	::1	f	2026-02-16 19:49:49.902413
98	100	superadmin	::1	t	2026-02-16 19:51:01.809833
99	\N	cris	::1	f	2026-02-16 19:52:07.133507
100	100	superadmin	::1	t	2026-02-16 19:52:20.605515
101	\N	crissaunt123	::1	f	2026-02-16 19:52:53.65115
102	\N	crissaunt24	::1	f	2026-02-16 19:54:23.557792
103	100	superadmin	::1	t	2026-02-16 19:54:40.205983
104	\N	admin24	::1	f	2026-02-16 19:59:01.118591
105	100	superadmin	::1	t	2026-02-16 20:01:22.326057
106	\N	cris_admin	::1	f	2026-02-16 20:04:51.386926
107	\N	cris_admin	::1	f	2026-02-16 20:05:25.369505
108	\N	admin	::1	f	2026-02-16 20:08:35.603607
109	\N	crissaunt24	::1	f	2026-02-16 20:12:40.024787
110	\N	superadmin	::1	f	2026-02-16 20:13:09.586936
111	\N	superadmin	::1	f	2026-02-16 20:13:14.920469
112	100	superadmin	::1	t	2026-02-16 20:13:22.936007
113	\N	admin	::1	f	2026-02-16 20:15:21.593684
114	\N	admin	::1	f	2026-02-16 20:15:33.920361
115	\N	admin	::1	f	2026-02-16 20:17:11.349783
116	\N	admin	::1	f	2026-02-16 20:19:56.950867
117	\N	superadmin2	::1	f	2026-02-16 20:23:59.625355
118	\N	superadmin2	::1	f	2026-02-16 20:24:15.602058
119	\N	superadmin2	::1	f	2026-02-16 20:25:09.306984
120	\N	superadmin2	::1	f	2026-02-16 20:26:25.192472
121	\N	superadmin1	::1	f	2026-02-16 20:29:52.069063
122	\N	superadmin1	::1	f	2026-02-16 20:30:00.990059
123	\N	superadmin1	::1	f	2026-02-16 20:30:38.120721
124	\N	superadmin1	::1	f	2026-02-16 20:30:42.089654
125	\N	superadmin1	::1	f	2026-02-16 20:30:49.599844
126	\N	superadmin1	::1	f	2026-02-16 20:30:57.469757
127	\N	superadmin	::1	f	2026-02-16 20:33:15.898601
128	\N	superadmin	::1	f	2026-02-16 20:33:22.081078
129	\N	superadmin	::1	f	2026-02-16 20:34:13.58602
130	\N	superadmin1	::1	f	2026-02-16 20:34:18.694602
131	105	crissaunt24	::1	t	2026-02-16 21:24:34
132	\N	asd	::1	f	2026-02-21 23:03:47
133	\N	crissaunt	::1	f	2026-02-21 23:30:47
134	105	crissaunt24	::1	t	2026-02-21 23:30:53
135	105	crissaunt24	::1	t	2026-02-21 23:34:40
136	\N	crissaunt	::1	f	2026-02-22 00:21:03
137	105	crissaunt24	::1	t	2026-02-22 00:21:10
138	\N	crissaunt	::1	f	2026-02-22 00:29:14
139	\N	alex24	::1	f	2026-02-22 00:29:22
141	\N	asdasd24a	::1	f	2026-02-22 00:30:42
142	\N	asdasd24a	::1	f	2026-02-22 00:30:46
143	100	superadmin	::1	f	2026-02-22 00:50:40
\.


--
-- Data for Name: password_reset_attempts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_reset_attempts (attempt_id, email, ip_address, attempt_time, attempt_type) FROM stdin;
\.


--
-- Data for Name: password_reset_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_reset_logs (log_id, user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until, details) FROM stdin;
1	2	\N	security_answer	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-25 23:58:04.031035	f	\N	\N
2	2	\N	security_answer	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-25 23:58:06.421314	f	\N	\N
3	2	\N	security_answer	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-25 23:58:07.505108	f	\N	\N
4	2	solayaoflorence@gmail.com	security_answer	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-25 23:58:11.947394	f	2026-01-26 00:28:11	\N
5	2	solayaoflorence@gmail.com	otp_request	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 00:09:25.206295	t	\N	OTP sent successfully
6	2	solayaoflorence@gmail.com	otp_request	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 00:09:40.332986	t	\N	OTP sent successfully
7	2	solayaoflorence@gmail.com	otp_request	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 00:15:54.805725	t	\N	OTP sent successfully
8	2	solayaoflorence@gmail.com	otp_verify	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 00:16:12.290131	t	\N	OTP verified successfully
9	2	solayaoflorence@gmail.com	otp_verify	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 00:18:28.873802	t	\N	OTP verified successfully
10	2	solayaoflorence@gmail.com	otp_verify	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 00:19:10.11634	t	\N	OTP verified successfully
11	2	solayaoflorence@gmail.com	otp_verify	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 00:24:12.17532	t	\N	OTP verified successfully
12	2	solayaoflorence@gmail.com	otp_request	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 21:45:37.417707	t	\N	OTP sent successfully
13	2	solayaoflorence@gmail.com	otp_verify	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 21:46:12.075883	t	\N	OTP verified successfully
14	2	solayaoflorence@gmail.com	otp_request	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 22:23:28.669596	t	\N	OTP sent successfully
15	2	solayaoflorence@gmail.com	otp_verify	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	2026-01-26 22:23:59.034015	t	\N	OTP verified successfully
16	2	solayaoflorence@gmail.com	otp_request	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-14 22:53:39.776613	t	\N	OTP sent successfully
17	\N	asd	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 11:49:40	f	\N	Username not found
18	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:50:13	f	\N	Username not found
19	105	crissaunt24	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:50:18	f	\N	Incorrect password
20	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:52:11	f	\N	Username not found
21	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:52:14	f	\N	Username not found
22	105	crissaunt24	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:52:22	f	\N	Incorrect password
23	105	crissaunt24	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:57:14	f	\N	Incorrect password
24	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:24:37	f	\N	Username not found
25	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:25:06	f	\N	Username not found
26	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:25:23	f	\N	Username not found
27	\N	asd	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:26:03	f	\N	Username not found
28	\N	asd	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:31:36	f	\N	Username not found
29	\N	asd	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:31:49	f	\N	Username not found
30	\N	::1	lockout	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:32:03	f	\N	Blocked due to lockout
31	\N	asd	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:33:58	f	\N	Username not found
32	\N	asd	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:34:10	f	\N	Username not found
33	\N	asd	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:36:25	f	\N	Username not found
34	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:37:06	f	\N	Username not found
35	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:38:20	f	\N	Username not found
36	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:38:25	f	\N	Username not found
37	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:22:41	f	\N	Username not found
39	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:29:37	f	\N	Username not found
40	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:35:59	f	\N	Username not found
41	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:16	f	\N	Username not found
42	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:18	f	\N	Username not found
43	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:20	f	\N	Username not found
44	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:37	f	\N	Username not found
45	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:38	f	\N	Username not found
46	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:41	f	\N	Username not found
47	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:37:13	f	\N	Username not found
48	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:37:18	f	\N	Username not found
49	\N	asdas	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:37:20	f	\N	Username not found
50	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:39:23	f	\N	Username not found
51	\N	asd	login	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:03:47	f	\N	Username not found
52	\N	crissaunt	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-21 23:30:47	f	\N	Username not found
53	\N	crissaunt	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:21:03	f	\N	Username not found
54	\N	crissaunt	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:29:14	f	\N	Username not found
55	\N	alex24	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:29:22	f	\N	Username not found
56	\N	asdasd24a	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:30:42	f	\N	Username not found
57	\N	asdasd24a	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:30:46	f	\N	Username not found
58	2	solayaoflorence@gmail.com	otp_request	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:30:53.848947	t	\N	OTP sent successfully
59	2	solayaoflorence@gmail.com	otp_verify	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:31:19.792229	t	\N	OTP verified successfully
60	2	\N	security_answer	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:31:24.859287	f	\N	\N
61	100	superadmin	login	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:50:40	f	\N	Admin attempted user login
\.


--
-- Data for Name: password_reset_sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_reset_sessions (session_id, user_id, email, otp_hash, otp_expiry, otp_attempts, answer_attempts, current_step, created_at, updated_at) FROM stdin;
fa869aa758f681dea8ea1fddee10ff614581a7dc55dad0a14eed969f74cba9db	2	solayaoflorence@gmail.com	$2y$12$eqvfLAbmCgnHdm2TG.6t3OGy../f5Ocr0ECOzMWUCvmyHUNNDiUrW	2026-02-22 00:40:53	0	0	questions	2026-02-22 00:30:53.846481	2026-02-22 00:31:19.789808
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.permissions (permission_id, permission_name, permission_description, resource, action, created_at) FROM stdin;
1	View Users	Can view user list and details	users	view	2024-01-01 00:00:00
2	Create Users	Can create new users	users	create	2024-01-01 00:00:00
3	Edit Users	Can edit user information	users	edit	2024-01-01 00:00:00
4	Delete Users	Can delete users	users	delete	2024-01-01 00:00:00
5	View Roles	Can view roles and permissions	roles	view	2024-01-01 00:00:00
6	Create Roles	Can create new roles	roles	create	2024-01-01 00:00:00
7	Edit Roles	Can edit roles and their permissions	roles	edit	2024-01-01 00:00:00
8	Delete Roles	Can delete roles	roles	delete	2024-01-01 00:00:00
9	View Logs	Can view activity logs	logs	view	2024-01-01 00:00:00
10	View Security Questions	Can view security questions	security_questions	view	2024-01-01 00:00:00
11	Manage Security Questions	Can manage security questions	security_questions	manage	2024-01-01 00:00:00
12	View Reports	Can view system reports	reports	view	2024-01-01 00:00:00
13	Generate Reports	Can generate system reports	reports	generate	2024-01-01 00:00:00
14	System Settings	Can modify system settings	system	configure	2024-01-01 00:00:00
\.


--
-- Data for Name: role_permissions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.role_permissions (role_permission_id, role_id, permission_id, created_at) FROM stdin;
24	1	1	2026-02-14 22:51:12.492946
25	1	2	2026-02-14 22:51:12.492946
26	1	3	2026-02-14 22:51:12.492946
27	1	4	2026-02-14 22:51:12.492946
28	1	5	2026-02-14 22:51:12.492946
29	1	6	2026-02-14 22:51:12.492946
30	1	7	2026-02-14 22:51:12.492946
31	1	8	2026-02-14 22:51:12.492946
32	1	9	2026-02-14 22:51:12.492946
33	1	10	2026-02-14 22:51:12.492946
34	1	11	2026-02-14 22:51:12.492946
35	1	12	2026-02-14 22:51:12.492946
36	1	13	2026-02-14 22:51:12.492946
37	1	14	2026-02-14 22:51:12.492946
38	2	1	2026-02-14 22:51:12.504042
39	2	2	2026-02-14 22:51:12.504042
40	2	3	2026-02-14 22:51:12.504042
41	2	9	2026-02-14 22:51:12.504042
42	2	10	2026-02-14 22:51:12.504042
43	2	12	2026-02-14 22:51:12.504042
44	2	13	2026-02-14 22:51:12.504042
45	3	1	2026-02-14 22:51:12.510564
46	3	10	2026-02-14 22:51:12.510564
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.roles (role_id, role_name, role_description, is_active, created_at, updated_at) FROM stdin;
1	Super Admin	Full system access with all permissions	t	2024-01-01 00:00:00	2024-01-01 00:00:00
2	Admin	Administrative access with limited permissions	t	2024-01-01 00:00:00	2024-01-01 00:00:00
3	User	Regular user with basic permissions	t	2024-01-01 00:00:00	2024-01-01 00:00:00
\.


--
-- Data for Name: security_questions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.security_questions (question_id, question_text, is_active, created_at) FROM stdin;
1	What was your childhood nickname?	t	2026-01-24 10:32:37.515334
2	What is the name of your favorite childhood friend?	t	2026-01-24 10:32:37.515334
3	In what city or town did your mother and father meet?	t	2026-01-24 10:32:37.515334
4	What is your favorite movie?	t	2026-01-24 10:32:37.515334
5	What was your favorite sport in high school?	t	2026-01-24 10:32:37.515334
6	What was the make and model of your first car?	t	2026-01-24 10:32:37.515334
7	What is your paternal grandmother's maiden name?	t	2026-01-24 10:32:37.515334
8	In what city or town was your first job?	t	2026-01-24 10:32:37.515334
9	What is the name of your favorite teacher?	t	2026-01-24 10:32:37.515334
10	What is your favorite book?	t	2026-01-24 10:32:37.515334
11	What is your mother's maiden name?	t	2026-02-14 22:51:12.529665
12	What was the name of your first pet?	t	2026-02-14 22:51:12.529665
13	What was the name of your elementary school?	t	2026-02-14 22:51:12.529665
14	What is your favorite book?	t	2026-02-14 22:51:12.529665
15	What city were you born in?	t	2026-02-14 22:51:12.529665
16	What is your favorite movie?	t	2026-02-14 22:51:12.529665
17	What is your father's middle name?	t	2026-02-14 22:51:12.529665
18	What was your childhood nickname?	t	2026-02-14 22:51:12.529665
19	What is the name of your best friend?	t	2026-02-14 22:51:12.529665
20	What is your favorite food?	t	2026-02-14 22:51:12.529665
\.


--
-- Data for Name: system_activity_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.system_activity_logs (log_id, user_id, admin_id, actor_type, action, category, description, table_affected, record_identifier, ip_address, user_agent, created_at) FROM stdin;
1	\N	1	super_admin	CREATE_ADMIN	ADMIN_MANAGEMENT	Created new admin: john_admin	admin_users	2	\N	\N	2026-02-14 21:17:51.006783
4	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 11:49:46.97499
7	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:50:55.262415
11	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:56:12.831207
12	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:56:40.549313
14	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 11:57:18.498114
15	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:10:00.693685
16	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:24:23.457249
24	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asd: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:33:58.016268
25	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asd: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:34:10.390974
26	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asd: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-15 12:36:25.120873
27	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asd: Username not found	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:37:06.143925
28	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asd: Username not found	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:38:20.60367
29	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asd: Username not found	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:38:25.605528
30	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:38:51.91972
31	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:41:32.058177
32	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:44:28.578856
33	105	\N	user	LOGOUT	authentication	User crissaunt24 logged out	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 12:44:35.225854
35	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:23:05.845853
36	105	\N	user	LOGOUT	authentication	User crissaunt24 logged out	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:23:07.808586
38	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:25:13.286426
40	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:29:45.182107
42	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:06.53218
43	105	\N	user	LOGOUT	authentication	User crissaunt24 logged out	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:36:10.939981
53	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asd: Username not found	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-15 13:39:23.031019
54	109	\N	user	LOGIN_SUCCESS	authentication	User crissaunt123 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 11:40:45.695693
55	100	\N	user	LOGOUT	authentication	User superadmin logged out	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 12:25:22.887913
56	105	\N	user	LOGIN_SUCCESS	authentication	User crissaunt24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-16 21:24:34.280649
57	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asd: Username not found	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-21 23:03:47.95134
58	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user crissaunt: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-21 23:30:47.676475
59	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user crissaunt: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:21:03.016958
60	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user crissaunt: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:29:14.362875
61	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user alex24: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:29:22.746643
64	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asdasd24a: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:30:42.625635
65	\N	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user asdasd24a: Username not found	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:30:46.088668
66	100	\N	user	LOGIN_FAILED	authentication	Failed login attempt for user superadmin: Admin attempted user login	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:50:40.495147
62	\N	\N	user	LOGIN_SUCCESS	authentication	User asdasd24 logged in successfully	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:30:17.425621
63	\N	\N	user	LOGOUT	authentication	User asdasd24 logged out	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36	2026-02-22 00:30:20.321048
67	\N	\N	user	LOGOUT	authentication	User asdasd24 logged out	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-22 01:01:46.378289
68	128	\N	user	LOGOUT	authentication	User florence24 logged out	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36	2026-02-24 00:41:39.663684
\.


--
-- Data for Name: user_security_answers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.user_security_answers (answer_id, user_id, question_id, answer_hash, created_at) FROM stdin;
1	1	1	$2y$12$bzRH3rciJRKZ7RJMW0SQd.AFbm..ArDOhFG9cS0lXZx0k.1WyOUq6	2026-01-24 11:11:35.954263
2	1	2	$2y$12$H2/oIBpbpu4CYzgdBBDcmOhBdPtLDDlCenRcY0s5VnUeBhn43D5Te	2026-01-24 11:11:35.954263
3	1	3	$2y$12$EU3uEnSyVqWEnk1rRBBE2uHCBWh4No5rmhmuuCZo9LxS.Sf0/icke	2026-01-24 11:11:35.954263
4	2	3	$2y$12$5vtx1botGyoffUPxs.3ilOP5cgaVupIEweV5qKMA9K6858D2LezT6	2026-01-24 11:56:31.746139
5	2	8	$2y$12$wEUeD9qRC2DO8Vz8FguRRO6blsY95zFi.0nwDW54wdDxajgZGDpm6	2026-01-24 11:56:31.746139
6	2	2	$2y$12$KPIitsDC90KWq7JEW4WIgugLu9iwVZwSH1C3dWRTu.Fa/5IApfEZe	2026-01-24 11:56:31.746139
22	8	8	$2y$12$pLWQJPUXCVgElA9m8tYEyuhEBy4YuUvAJ2M0MZ4FgZzp5FyH6NGuO	2026-01-24 13:01:40.260607
23	8	4	$2y$12$UKU0KI3jqBnCMWxEn7I7t.tXdaPklWjyEZ/DZTtUmz58JC9fsrZTq	2026-01-24 13:01:40.260607
24	8	1	$2y$12$iToraSUHkC9H.B8pZ/9oB.SRD3wMfxvaMxldqCGDF9K1JbNE4YtzS	2026-01-24 13:01:40.260607
28	10	3	$2y$12$yl4sAhntKdK/MXJz.0Zz/upR/S06RRiJsHZISzTAz2UgMB4YVK/kK	2026-01-24 13:13:20.844228
29	10	9	$2y$12$ANF/fvmASNi8y9Q.ngiEbOaGmzQCPP/G8F59infkjPVnfJZdiTny6	2026-01-24 13:13:20.844228
30	10	4	$2y$12$2oJV/3MPrlvew3MDwr3E.eiGdTtShS/7n7zdVaKkP6fimqg9424GC	2026-01-24 13:13:20.844228
31	105	13	$2y$12$aA0OtgiJ/GHYgZw3oJfYruEt/1dDq2Zqq5fEr.KurznHvFGHJfipC	2026-02-14 23:47:16.890268
32	105	7	$2y$12$qX7hJDCtOg7pbrE9KetgXeRs9nNkcoSj70EpVjcF3sboBDM.jdLQq	2026-02-14 23:47:16.890268
33	105	10	$2y$12$xmK8MpWY9k4/xOwBr6OAae0M0o5AN/7UuDrvyx55p8.1LPEbVcZ66	2026-02-14 23:47:16.890268
34	109	10	$2y$12$u2BGoHG9.Aqj9/5lYpKtxOf9fyYpchJSDDZxS17aMAnfVJBzds5Jy	2026-02-16 11:40:21.061848
35	109	5	$2y$12$hZVvP7K/jWznfW/.yeLM4O1pVPo.PeKyAKn4rvMA4XoT7giKp6wXC	2026-02-16 11:40:21.061848
36	109	2	$2y$12$fcjQh3ZnZlrKG21EhdBS8.rNu6v1a/uSCfiujo9GxV9gKRWfPqxVS	2026-02-16 11:40:21.061848
37	128	6	$2y$12$1VtFca0TCnOsGswsg9UQ6uHuPanYVH9URieqRIcd.adIG4Un6grtm	2026-02-24 00:33:56.867077
38	128	5	$2y$12$j4AzL0C0Q56qlcJw3WayHen7jO5.LS1TeTA9S1AEiYSOsjnBrVYZS	2026-02-24 00:33:56.867077
39	128	8	$2y$12$kIIlZTPnzC7ze50BIsk9suvwgV5r0Y4g3hmiUy9hENsdIph2iJx9C	2026-02-24 00:33:56.867077
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (user_id, id_number, email, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, age, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, created_at, reset_token, reset_token_expiry, last_reset_attempt, role_id, is_active, updated_at, last_login, permissions, created_by, updated_by) FROM stdin;
10	4142-1245	asdsad@53asda.asd	09925450766	asdas24	asdasd	\N	asdasd	\N	2001-02-02	25	\N	$2y$12$aWPN3BKnXw7G7Pf3ipErZO8xB1HXSVnVdTxQazKBUxlLl7c.Im7Eq	asd	asdasd	asd	asdasdasd	Philippines	1245	2026-01-24 13:13:20.844228	\N	\N	\N	3	f	2026-02-24 02:34:12.603304	\N	[]	\N	\N
8	5944-6411	asdsad24@asda.asd	09945450785	dasd24	asdasd	\N	asdasd	\N	2003-02-02	23	Female	$2y$12$KmchhkvbFFGqtLBCU8fr2uZhqDqe6Uzna70qBG.gPJkGTOLgQmeDG	\N	\N	Zx	Z	Philippines	1230	2026-01-24 13:01:40.260607	\N	\N	\N	3	t	2026-02-24 02:36:57.278127	\N	[]	\N	\N
107	2022-0945	asdsad@asda.asd	99245450721	admin	asdasd	\N	asdasd	\N	\N	\N	Male	$2y$12$jWA2pkK/B3GEC.lx4ZV9bOIyPK9.R55P.WY93eFGJ47K5Jv4c92Xa	\N	asdasd	asd	asdasdasd	\N	\N	2026-02-15 22:00:42.786066	\N	\N	\N	2	t	2026-02-24 06:58:43.667006	2026-02-24 06:58:43.667006	["manage_users", "view_logs"]	\N	100
2	9120-0064	solayaoflorence@gmail.com	09925450721	dsaf	Asdasd		Asdasd		2003-02-02	22	female	$2y$12$u4HpY8Wb4rywoRuvnliLB.OOtJAqr4E3ffq27mwveeSjqjTAiawoK	Asd	Sfasd	Asd	Asdasdasd	Philippines	1245	2026-01-24 11:56:31.746139	\N	\N	\N	3	t	2026-02-14 23:08:54.054368	\N	[]	\N	\N
105	5555-1111	ollow@asdl.com	09924540000	crissaunt24	asdsad		asdsad		2003-12-14	22	male	$2y$12$JTKYpXAEKmpuhzk8LpgDL.pVIy2JpJpfl9AdkZY3J4KUNAVOTWPJ6	asd	asdasd	asd	asdasdasd	Philippines	1245	2026-02-14 23:47:16.890268	\N	\N	\N	2	t	2026-02-24 00:42:21.604085	2026-02-24 00:42:21.604085	["manage_users", "view_logs"]	\N	\N
113	\N	regular1@example.com	\N	regularadmin1	Regular	\N	Admin	\N	\N	\N	\N	$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8	\N	\N	\N	\N	\N	\N	2026-02-16 20:29:40.134395	\N	\N	\N	2	t	2026-02-22 03:15:55.632589	\N	["manage_users", "view_logs"]	\N	100
114	\N	john@example.com	\N	john_admin	John	\N	Doe	\N	\N	\N	\N	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	\N	\N	\N	\N	\N	\N	2026-02-14 21:17:51.006783	\N	\N	\N	2	t	2026-02-21 22:28:23.555985	\N	["manage_users", "view_logs"]	\N	\N
115	\N	asdsad2412@asda.asd	\N	cris	asdasd	\N	asdasd	\N	\N	\N	\N	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	\N	\N	\N	\N	\N	\N	2026-02-16 19:52:00.441021	\N	\N	\N	2	t	2026-02-21 22:28:23.555985	\N	["manage_users", "view_logs"]	\N	\N
109	2022-0999	florencecris1.solayao2@gmail.com	09254554544	crissaunt123	asdasd	dasd	asdasd		2003-02-02	23	male	$2y$12$QU2w2G/mPnnYXJ5RAKH.IOSgjfQvpsMxP7jJxVi3SPEwKwjWiKW7G	asd	asdsad	asd	asdasdasd	Philippines	1245	2026-02-16 11:40:21.061848	\N	\N	\N	2	t	2026-02-21 22:28:23.555985	2026-02-16 11:40:45.643891	["manage_users", "view_logs"]	\N	\N
117	\N	instrcutor@asda.asd	\N	instructor	asdasd	\N	asdasd	\N	\N	\N	\N	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	\N	\N	\N	\N	\N	\N	2026-02-16 19:55:01.537435	\N	\N	\N	2	t	2026-02-21 22:28:23.555985	\N	["manage_users", "view_logs"]	\N	\N
118	\N	admin0909@asda.asd	\N	admin0909	asdasd	\N	asdasd	\N	\N	\N	\N	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	\N	\N	\N	\N	\N	\N	2026-02-16 19:55:36.506057	\N	\N	\N	2	t	2026-02-21 22:28:23.555985	\N	["manage_users", "view_logs"]	\N	\N
119	\N	postman@man.com	\N	cris_admin	postman	\N	tester	\N	\N	\N	\N	$2y$12$cCM6B73uRAtQr7DNWK5ux.WbO0rrvdIhUNyR.0yj/d.VrWXWg.u9q	\N	\N	\N	\N	\N	\N	2026-02-16 20:03:34.96575	\N	\N	\N	2	t	2026-02-21 22:28:23.555985	\N	["manage_users", "view_logs"]	\N	\N
112	\N	superadmin_new@solayao.com	\N	superadmin2	Super	\N	Admin	\N	\N	\N	\N	$2y$12$lTv7M3HqKs9L1G5K7Xp8YuRq9W5X2F3J7L4M8N1P6Q3R9S2T5V8	\N	\N	\N	\N	\N	\N	2026-02-16 20:23:03.661427	\N	\N	\N	1	t	2026-02-21 22:28:23.555985	\N	["all"]	\N	\N
1	2024-0001	test@example.com	09123456789	testuser123	John	Middle	Doe	Jr	1990-01-01	34	male	$2y$12$YFs9gN20uxbnMCFQTqzfOuAwMILWtmDM0pIWVhQNeepp1EGxqrwse	Street 123	Barangay 1	City	Province	Philippines	1234	2026-01-24 11:11:35.954263	\N	\N	\N	1	t	2026-02-21 22:28:23.555985	\N	["all"]	\N	\N
108	2442-992	asdsadw24@asda.asd	99245450721	admin24	asdasd	\N	asdasd	\N	\N	\N	Male	$2y$12$SmpSpjfJIz.GUF7ylWRX9.sp7jqIsPZhvN5tp.thnVwV8ky5mcrWy	\N	asdasd	asd	asdasdasd	\N	\N	2026-02-15 22:07:46.137937	\N	\N	\N	2	t	2026-02-21 22:28:23.555985	\N	["manage_users", "view_logs"]	\N	\N
128	1234-4321	florence2424@gmail.com	09936264997	florence24	florence		solayao		2003-11-04	22	male	$2y$12$n4YqWI6lZhfif5JnNtWsu.o3oZ9/rbHecpVDCtFGXWxWzYpiLKVn6	asd	asd	asd	asdasdasd	Philippines	1245	2026-02-24 00:33:56.867077	\N	\N	\N	3	f	2026-02-24 02:01:12.934602	2026-02-24 00:41:14.009009	[]	\N	\N
100	SA-2024-001	superadmin@solayao.com	\N	superadmin	Super	\N	Admin	\N	\N	\N	\N	$2y$12$AsS3M1Xh0bOpJIJHJa8ldu1PoaCfnNT/.e8LvFWMg.UmhzJvloHPi	\N	\N	\N	\N	\N	\N	2026-02-14 23:08:45.209468	\N	\N	\N	1	t	2026-02-24 06:59:09.996525	2026-02-24 06:59:09.996525	["all"]	\N	\N
\.


--
-- Name: activity_logs_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.activity_logs_log_id_seq', 587, true);


--
-- Name: admin_activity_logs_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.admin_activity_logs_log_id_seq', 95, true);


--
-- Name: admin_login_attempts_attempt_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.admin_login_attempts_attempt_id_seq', 19, true);


--
-- Name: admin_users_admin_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.admin_users_admin_id_seq', 38, true);


--
-- Name: deletion_requests_request_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.deletion_requests_request_id_seq', 4, true);


--
-- Name: edit_requests_request_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.edit_requests_request_id_seq', 2, true);


--
-- Name: login_attempts_attempt_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.login_attempts_attempt_id_seq', 143, true);


--
-- Name: password_reset_attempts_attempt_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.password_reset_attempts_attempt_id_seq', 1, false);


--
-- Name: password_reset_logs_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.password_reset_logs_log_id_seq', 61, true);


--
-- Name: permissions_permission_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.permissions_permission_id_seq', 15, true);


--
-- Name: role_permissions_role_permission_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.role_permissions_role_permission_id_seq', 46, true);


--
-- Name: roles_role_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.roles_role_id_seq', 4, true);


--
-- Name: security_questions_question_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.security_questions_question_id_seq', 20, true);


--
-- Name: system_activity_logs_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.system_activity_logs_log_id_seq', 68, true);


--
-- Name: user_security_answers_answer_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.user_security_answers_answer_id_seq', 39, true);


--
-- Name: users_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_user_id_seq', 128, true);


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (log_id);


--
-- Name: admin_activity_logs admin_activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_activity_logs
    ADD CONSTRAINT admin_activity_logs_pkey PRIMARY KEY (log_id);


--
-- Name: admin_login_attempts admin_login_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_login_attempts
    ADD CONSTRAINT admin_login_attempts_pkey PRIMARY KEY (attempt_id);


--
-- Name: admin_users_backup admin_users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users_backup
    ADD CONSTRAINT admin_users_email_key UNIQUE (email);


--
-- Name: admin_users_backup admin_users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users_backup
    ADD CONSTRAINT admin_users_pkey PRIMARY KEY (admin_id);


--
-- Name: admin_users_backup admin_users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users_backup
    ADD CONSTRAINT admin_users_username_key UNIQUE (username);


--
-- Name: deletion_requests deletion_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deletion_requests
    ADD CONSTRAINT deletion_requests_pkey PRIMARY KEY (request_id);


--
-- Name: edit_requests edit_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.edit_requests
    ADD CONSTRAINT edit_requests_pkey PRIMARY KEY (request_id);


--
-- Name: login_attempts login_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.login_attempts
    ADD CONSTRAINT login_attempts_pkey PRIMARY KEY (attempt_id);


--
-- Name: password_reset_attempts password_reset_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_attempts
    ADD CONSTRAINT password_reset_attempts_pkey PRIMARY KEY (attempt_id);


--
-- Name: password_reset_logs password_reset_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_logs
    ADD CONSTRAINT password_reset_logs_pkey PRIMARY KEY (log_id);


--
-- Name: password_reset_sessions password_reset_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_sessions
    ADD CONSTRAINT password_reset_sessions_pkey PRIMARY KEY (session_id);


--
-- Name: permissions permissions_permission_name_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_permission_name_key UNIQUE (permission_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (permission_id);


--
-- Name: role_permissions role_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_pkey PRIMARY KEY (role_permission_id);


--
-- Name: role_permissions role_permissions_role_id_permission_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_role_id_permission_id_key UNIQUE (role_id, permission_id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (role_id);


--
-- Name: roles roles_role_name_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_role_name_key UNIQUE (role_name);


--
-- Name: security_questions security_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.security_questions
    ADD CONSTRAINT security_questions_pkey PRIMARY KEY (question_id);


--
-- Name: system_activity_logs system_activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.system_activity_logs
    ADD CONSTRAINT system_activity_logs_pkey PRIMARY KEY (log_id);


--
-- Name: user_security_answers user_security_answers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_security_answers
    ADD CONSTRAINT user_security_answers_pkey PRIMARY KEY (answer_id);


--
-- Name: user_security_answers user_security_answers_user_id_question_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_security_answers
    ADD CONSTRAINT user_security_answers_user_id_question_id_key UNIQUE (user_id, question_id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_id_number_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_id_number_key UNIQUE (id_number);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- Name: idx_activity_logs_created_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_activity_logs_created_at ON public.activity_logs USING btree (created_at);


--
-- Name: idx_activity_logs_performed_by; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_activity_logs_performed_by ON public.activity_logs USING btree (performed_by);


--
-- Name: idx_admin_activity_logs_action_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admin_activity_logs_action_type ON public.admin_activity_logs USING btree (action_type);


--
-- Name: idx_admin_activity_logs_admin_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admin_activity_logs_admin_id ON public.admin_activity_logs USING btree (admin_id);


--
-- Name: idx_admin_activity_logs_created_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admin_activity_logs_created_at ON public.admin_activity_logs USING btree (created_at);


--
-- Name: idx_login_attempts_attempt_time; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_login_attempts_attempt_time ON public.login_attempts USING btree (attempt_time);


--
-- Name: idx_login_attempts_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_login_attempts_user_id ON public.login_attempts USING btree (user_id);


--
-- Name: idx_password_reset_logs_email_time; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_password_reset_logs_email_time ON public.password_reset_logs USING btree (email, attempt_time);


--
-- Name: idx_password_reset_sessions_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_password_reset_sessions_email ON public.password_reset_sessions USING btree (email);


--
-- Name: idx_password_reset_sessions_otp_expiry; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_password_reset_sessions_otp_expiry ON public.password_reset_sessions USING btree (otp_expiry);


--
-- Name: idx_pwd_logs_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pwd_logs_email ON public.password_reset_logs USING btree (email);


--
-- Name: idx_pwd_logs_time; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pwd_logs_time ON public.password_reset_logs USING btree (attempt_time);


--
-- Name: idx_reset_expiry; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reset_expiry ON public.users USING btree (reset_token_expiry);


--
-- Name: idx_reset_logs_email_time; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reset_logs_email_time ON public.password_reset_logs USING btree (email, attempt_time);


--
-- Name: idx_reset_sessions_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reset_sessions_email ON public.password_reset_sessions USING btree (email);


--
-- Name: idx_reset_sessions_expiry; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reset_sessions_expiry ON public.password_reset_sessions USING btree (otp_expiry);


--
-- Name: idx_reset_tokens; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_reset_tokens ON public.users USING btree (reset_token);


--
-- Name: idx_system_activity_logs_action; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_system_activity_logs_action ON public.system_activity_logs USING btree (action);


--
-- Name: idx_system_activity_logs_admin_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_system_activity_logs_admin_id ON public.system_activity_logs USING btree (admin_id);


--
-- Name: idx_system_activity_logs_created_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_system_activity_logs_created_at ON public.system_activity_logs USING btree (created_at);


--
-- Name: idx_system_activity_logs_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_system_activity_logs_user_id ON public.system_activity_logs USING btree (user_id);


--
-- Name: idx_user_security_answers_question_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_security_answers_question_id ON public.user_security_answers USING btree (question_id);


--
-- Name: idx_user_security_answers_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_security_answers_user_id ON public.user_security_answers USING btree (user_id);


--
-- Name: admin_users_backup trigger_admin_users_activity; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_admin_users_activity AFTER INSERT OR DELETE OR UPDATE ON public.admin_users_backup FOR EACH ROW EXECUTE FUNCTION public.log_admin_activity();


--
-- Name: admin_users_backup update_admin_users_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_admin_users_updated_at BEFORE UPDATE ON public.admin_users_backup FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: roles update_roles_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_roles_updated_at BEFORE UPDATE ON public.roles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: users update_users_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON public.users FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: activity_logs activity_logs_performed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_performed_by_fkey FOREIGN KEY (performed_by) REFERENCES public.users(user_id) ON DELETE SET NULL;


--
-- Name: admin_activity_logs admin_activity_logs_admin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_activity_logs
    ADD CONSTRAINT admin_activity_logs_admin_id_fkey FOREIGN KEY (admin_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: admin_users_backup admin_users_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users_backup
    ADD CONSTRAINT admin_users_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.admin_users_backup(admin_id);


--
-- Name: admin_users_backup admin_users_updated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users_backup
    ADD CONSTRAINT admin_users_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES public.admin_users_backup(admin_id);


--
-- Name: admin_users_backup admin_users_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users_backup
    ADD CONSTRAINT admin_users_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: deletion_requests deletion_requests_requested_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deletion_requests
    ADD CONSTRAINT deletion_requests_requested_by_fkey FOREIGN KEY (requested_by) REFERENCES public.users(user_id);


--
-- Name: deletion_requests deletion_requests_reviewed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deletion_requests
    ADD CONSTRAINT deletion_requests_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES public.users(user_id);


--
-- Name: deletion_requests deletion_requests_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deletion_requests
    ADD CONSTRAINT deletion_requests_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: edit_requests edit_requests_requested_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.edit_requests
    ADD CONSTRAINT edit_requests_requested_by_fkey FOREIGN KEY (requested_by) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: edit_requests edit_requests_reviewed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.edit_requests
    ADD CONSTRAINT edit_requests_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES public.users(user_id) ON DELETE SET NULL;


--
-- Name: edit_requests edit_requests_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.edit_requests
    ADD CONSTRAINT edit_requests_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: users fk_users_role; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES public.roles(role_id);


--
-- Name: login_attempts login_attempts_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.login_attempts
    ADD CONSTRAINT login_attempts_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: password_reset_logs password_reset_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_logs
    ADD CONSTRAINT password_reset_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: password_reset_sessions password_reset_sessions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_sessions
    ADD CONSTRAINT password_reset_sessions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: role_permissions role_permissions_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(permission_id) ON DELETE CASCADE;


--
-- Name: role_permissions role_permissions_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(role_id) ON DELETE CASCADE;


--
-- Name: system_activity_logs system_activity_logs_admin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.system_activity_logs
    ADD CONSTRAINT system_activity_logs_admin_id_fkey FOREIGN KEY (admin_id) REFERENCES public.admin_users_backup(admin_id) ON DELETE SET NULL;


--
-- Name: system_activity_logs system_activity_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.system_activity_logs
    ADD CONSTRAINT system_activity_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE SET NULL;


--
-- Name: user_security_answers user_security_answers_question_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_security_answers
    ADD CONSTRAINT user_security_answers_question_id_fkey FOREIGN KEY (question_id) REFERENCES public.security_questions(question_id) ON DELETE CASCADE;


--
-- Name: user_security_answers user_security_answers_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_security_answers
    ADD CONSTRAINT user_security_answers_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: users users_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(user_id);


--
-- Name: users users_updated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES public.users(user_id);


--
-- PostgreSQL database dump complete
--

\unrestrict 3M8aAMFiRmrTNUAmmAb1qeIEIgjB5xZWwHaCqPtIJ7a65lCeAt13Qigu5rNNabh

