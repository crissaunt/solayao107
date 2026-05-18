-- update_users_schema.sql
-- Add missing columns to users table to match the original schema and register.php expectations.

BEGIN;

ALTER TABLE public.users ADD COLUMN IF NOT EXISTS id_number character varying(20);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS contact_number character varying(20);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS middle_name character varying(50);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS extension_name character varying(10);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS birthday date;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS age integer;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS sex character varying(10);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS street_purok character varying(100);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS barangay character varying(100);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS city_municipal character varying(100);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS province character varying(100);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS country character varying(100);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS zipcode character varying(10);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS reset_token character varying(255);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS reset_token_expiry timestamp without time zone;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS last_reset_attempt timestamp without time zone;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS created_by integer;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS updated_by integer;

COMMIT;
