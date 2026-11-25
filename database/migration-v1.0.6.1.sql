BEGIN;
CREATE OR REPLACE FUNCTION public.amicao_sync_auth_profile() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $$
BEGIN
 IF new.email IS DISTINCT FROM old.email THEN
  UPDATE public.profiles SET email=new.email WHERE id=new.id;
 END IF;
 IF (new.raw_app_meta_data->'amicao_profile') IS DISTINCT FROM (old.raw_app_meta_data->'amicao_profile') AND jsonb_typeof(new.raw_app_meta_data->'amicao_profile')='object' THEN
  UPDATE public.profiles SET name=new.raw_app_meta_data->'amicao_profile'->>'name', phone=new.raw_app_meta_data->'amicao_profile'->>'phone' WHERE id=new.id;
 END IF;
 RETURN new;
END $$;
REVOKE ALL ON FUNCTION public.amicao_sync_auth_profile() FROM PUBLIC, anon, authenticated;
DROP TRIGGER IF EXISTS amicao_sync_auth_profile ON auth.users;
CREATE TRIGGER amicao_sync_auth_profile AFTER UPDATE OF email,raw_app_meta_data ON auth.users FOR EACH ROW EXECUTE FUNCTION public.amicao_sync_auth_profile();
COMMIT;
