# Amicão Muriaé — v1.0.4.1
PHP 8.2+, HTML, CSS, JS, Supabase. Instalação inicial em projeto Supabase novo e dedicado (não executar em banco existente sem revisão). O banco PostgreSQL já existe quando o projeto Supabase é criado.

## Supabase
1. Crie um projeto no dashboard do Supabase. Em SQL Editor > New query, cole todo `database/schema.sql` e execute Run uma única vez. O script usa transação.
2. Em Authentication, desabilite o cadastro público (Allow new users to sign up). Em Users > Add user, crie a conta inicial com email e senha forte e confirme o email se necessário.
3. Em SQL Editor execute `database/primeiro-admin.sql`, trocando AMBAS as ocorrências do email de exemplo. Verifique uma linha com role admin e active true.
4. Copie Project URL e publishable key (ou anon legada) do painel Connect/API Keys. Nunca use service_role ou secret key.
5. Novos usuários: crie em Authentication > Users; depois, no sistema, menu Usuários, defina role e marque Ativo. Contas começam inativas. Redefinição de senha é administrada pelo Supabase; não há recuperação self-service nesta versão.

## WAMP
Extraia em C:/wamp64/www/amicao. Copie app/config.example.php para app/config.php e preencha url e key. Habilite php_curl e php_fileinfo, configure CA TLS se necessário (nunca desligue validação TLS), upload_max_filesize=5M, post_max_size=7M. Recomenda-se VirtualHost amicao.test apontando exclusivamente para C:/wamp64/www/amicao/public, com hosts 127.0.0.1 amicao.test. Alternativa para teste local: http://localhost/amicao/public/ com Apache mod_rewrite e AllowOverride All para bloquear app/database pela .htaccess. Não exponha a raiz do projeto na internet. Abra e entre com a conta criada.

## EasyPanel
Envie ao GitHub sem app/config.php nem .env. Crie serviço app a partir do repositório com Dockerfile, porta 80, domínio HTTPS. Variáveis SUPABASE_URL, SUPABASE_KEY, COOKIE_SECURE=1. Não precisa de MySQL. Sessões PHP locais: reinício pode exigir novo login; use uma instância inicialmente. Configure backup e política de retenção no Supabase.

## Homologação
Use exclusivamente dados fictícios até validar segurança e governança. Teste login inválido, conta inativa, admin e usuário; crie registro totalmente vazio; edite cada campo; adicione imagem após salvar; recarregue; transfira nas duas direções e veja histórico; teste pesquisa e paginação; arquive como admin; confirme que usuário comum não arquiva nem acessa usuários; desative usuário logado e confirme bloqueio no próximo acesso. Teste RLS diretamente com token de usuário comum (sem chave administrativa): negar update de perfis, negar insert em histórico, negar acesso anônimo e negar leitura de storage sem autorização. Não publique até testar em Supabase real.

## Limites da versão
Criação de contas e recuperação de senha pelo dashboard Supabase; arquivamento lógico (não equivale a eliminação LGPD); histórico de inclusão/edição/transferência/arquivamento com campos alterados, sem valores anteriores; imagens adicionadas uma por vez, sem interface de remoção; histórico limitado às últimas 100 operações. Todos os usuários ativos têm acesso aos registros, incluindo CPF completo na edição; CPF mascarado nas listas. Sem exportação, métricas numéricas ou configuração de permissões granulares. Para retirada definitiva de dados, defina procedimento administrativo seguro para registros, imagens, backups e histórico antes de produção. Registre apenas informações necessárias, objetivas e verificáveis; defina base legal, transparência, revisão, retenção e acesso mínimo. Não utilize como lista pública ou decisão automática de adoção.

## Atualização v1.0.1
Preserve app/config.php. Substitua os arquivos do projeto, sem executar schema.sql novamente. No WAMP, abra PHP > php.ini pelo menu do WAMP e confirme file_uploads=On, upload_max_filesize=5M e post_max_size=7M; reinicie os serviços. Teste uma foto PNG/JPG pequena após salvar o cadastro. O envio usa multipart via cURL; o Storage permanece privado. Se falhar, registre a mensagem exibida e execute database/diagnostico-imagens.sql no SQL Editor. Nenhuma migration é necessária. Teste cancelar e confirmar a transferência e o arquivamento; confirme a persistência da imagem após recarregar e transferir.

## Atualização v1.0.2
Preserve app/config.php, substitua os arquivos e reinicie o WAMP. Não execute schema.sql novamente. Upload utiliza corpo binário e MIME real. Teste JPEG pequeno, recarregue e transfira o cadastro. Se falhar, consulte Logs > Storage no Supabase, sem compartilhar tokens ou dados pessoais.

### Atualização v1.0.3
Em bancos existentes execute somente database/migration-v1.0.3.sql no SQL Editor. Não reinstale o schema.


## Atualização v1.0.4
Preserve app/config.php. Substitua os arquivos, reinicie WAMP e pressione Ctrl+F5. Não execute schema.sql sobre banco existente. Caso ainda não aplicada, a migration v1.0.3 permanece necessária. Teste totais, filtros exatos, visualizar, editar, miniaturas, galeria, upload e transferência.

## Atualização v1.0.4.1 (EasyPanel)
Substitua o Dockerfile no GitHub e faça novo build usando Dockerfile. Porta 80, HTTPS, SUPABASE_URL, SUPABASE_KEY e COOKIE_SECURE=1. DocumentRoot é /var/www/html/public; app e database ficam fora da raiz pública. Não execute SQL novamente. Verifique /, /app/bootstrap.php e /database/schema.sql (estes dois últimos devem retornar 404 ou 403). Valide login, imagens e transferências.

## Atualização v1.0.4.2 (subdiretório)
O Apache aceita / e /amicao/; /amicao redireciona com 308 para /amicao/. O alias aponta exclusivamente para public. Os links e assets já são relativos. Não configure APP_BASE_PATH; não é necessário. Configure o domínio EasyPanel com caminho /amicao, destino /, porta 80. Se o proxy remover o prefixo, use a URL pública com barra final. Se /amicao sem barra continuar falhando, verifique o caminho efetivamente recebido nos logs do container e as regras do proxy. A sessão usa AMICAOSESSID para evitar colisão com outros projetos; será necessário entrar novamente. Não execute migrations. Teste também /amicao/app/bootstrap.php e /amicao/database/schema.sql (404/403).

## Atualização v1.0.4.3
Rodapé institucional no login e painel. Preserve app/config.php no WAMP; no EasyPanel faça novo deploy. Sem migration.


## Compartilhamento (v1.0.4.4)
Após novo deploy, teste em janela anônima https://rsdigitallab.com/amicao/assets/og-cover.jpg e confira as tags og:image no código-fonte da tela de login. Compartilhe https://rsdigitallab.com/amicao/?v=1044 para tentar contornar o cache antigo. A URL OG está definida para o domínio de produção no public/index.php; ajuste se mudar o domínio. Nenhuma migration necessária.
