-- Crie primeiro o usuário em Authentication > Users > Add user.
-- Substitua o e-mail abaixo pelo e-mail exato da conta criada.
update public.profiles p set role='admin', active=true, name='Administrador'
from auth.users u where u.id=p.id and lower(u.email)=lower('ADMIN@EXEMPLO.COM');
-- Verifique se uma linha foi atualizada:
select p.id,p.name,p.role,p.active from public.profiles p join auth.users u on u.id=p.id where lower(u.email)=lower('ADMIN@EXEMPLO.COM');
