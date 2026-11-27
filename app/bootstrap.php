<?php
declare(strict_types=1);
ini_set('session.use_strict_mode','1');
session_name('AMICAOSESSID');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off' || getenv('COOKIE_SECURE')==='1','samesite'=>'Lax']); session_start();
$config=file_exists(__DIR__.'/config.php')?require __DIR__.'/config.php':[];
$url=rtrim(getenv('SUPABASE_URL')?:($config['url']??''),'/'); $key=getenv('SUPABASE_KEY')?:($config['key']??'');
if (!preg_match('~^https://[a-z0-9-]+\.supabase\.co$~',$url)||!$key){http_response_code(503);exit('Configure SUPABASE_URL e SUPABASE_KEY conforme README.');}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function req($method,$path,$data=null,$token=null,$mime='application/json',$raw=false,$count=false,$overrideKey=null){global $url,$key; static $ch=null; if($ch===null)$ch=curl_init(); curl_reset($ch); curl_setopt($ch,CURLOPT_URL,$url.$path); $started=hrtime(true); $headers=['apikey: '.($overrideKey??$key),'Accept: '.($method==='GET'&&str_starts_with($path,'/storage/')?'*/*':'application/json')]; if($mime!=='multipart/form-data')$headers[]='Content-Type: '.$mime; if($token)$headers[]='Authorization: Bearer '.$token; if(str_starts_with($path,'/rest/v1/'))$headers[]=$count?'Prefer: count=exact':'Prefer: return=representation'; curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false]); $range=null;if($count)curl_setopt($ch,CURLOPT_HEADERFUNCTION,function($ch,$line)use(&$range){if(stripos($line,'Content-Range:')===0)$range=trim(substr($line,14));return strlen($line);}); if($data!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$mime==='application/json'?json_encode($data,JSON_THROW_ON_ERROR):$data);  $out=curl_exec($ch);$transport=curl_errno($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $GLOBALS['amicao_api_calls']=($GLOBALS['amicao_api_calls']??0)+1; $GLOBALS['amicao_api_ms']=($GLOBALS['amicao_api_ms']??0)+(hrtime(true)-$started)/1e6; if($out===false||$code<200||$code>=300){$stage=str_starts_with($path,'/storage/')?'Armazenamento de imagens':(str_contains($path,'record_images')?'Vínculo da imagem':'Supabase');$detail=match($code){0=>'Falha de comunicação (cURL '.$transport.').',401=>'Sessão ou chave não aceita.',403=>'Permissão negada. Confira as políticas RLS.',404=>'Recurso ou bucket não encontrado.',413=>'Arquivo excede o limite do servidor.',400=>'Requisição recusada. Confira configuração e políticas do recurso.',default=>'Confira a configuração do serviço.'};if(str_starts_with($path,'/storage/') && is_string($out)){ $j=json_decode($out,true); if(is_array($j)){ $msg=strtolower(implode(' ',array_filter([$j['error']??null,$j['message']??null,$j['code']??null], 'is_string'))); $reason=match(true){str_contains($msg,'row-level security'),str_contains($msg,'unauthorized'),str_contains($msg,'access denied')=>'Política de acesso recusou a operação.',str_contains($msg,'mime'),str_contains($msg,'content type')=>'Tipo MIME recusado pelo Storage.',str_contains($msg,'bucket')&&str_contains($msg,'not found')=>'Bucket não encontrado.',str_contains($msg,'jwt'),str_contains($msg,'token')=>'Token de autenticação recusado.',str_contains($msg,'size'),str_contains($msg,'too large')=>'Limite de tamanho recusado.',str_contains($msg,'duplicate'),str_contains($msg,'already exists')=>'Arquivo já existente.',default=>'Motivo não classificado; consulte os logs do Storage.'}; $status=$j['statusCode']??null; $detail=$reason. (is_scalar($status)&&preg_match('/^[0-9]{3}$/',(string)$status)?' Código interno: '.$status.'.':''); }}if(str_starts_with($path,'/auth/v1/token?grant_type=password') && is_string($out)){ $j=json_decode($out,true); $detail=match($j['error_code']??$j['code']??''){ 'invalid_credentials'=>'E-mail ou senha incorretos.', 'email_not_confirmed'=>'E-mail ainda não confirmado.', 'user_banned'=>'Conta bloqueada.', 'over_request_rate_limit'=>'Muitas tentativas. Tente novamente mais tarde.', default=>$detail}; } if(str_starts_with($path,'/rest/v1/') && is_string($out)){ $j=json_decode($out,true); $err=$j['code']??''; if(is_string($err)&&preg_match('/^[A-Z0-9]{5,12}$/',$err))$detail.=' Código: '.$err.'.'; } throw new RuntimeException($stage.' (HTTP '.$code.'): '.$detail);} if($count){if(!preg_match('~/([0-9]+)$~',(string)$range,$m))throw new RuntimeException('Não foi possível consultar o total.');return (int)$m[1];} return $raw?$out:(json_decode($out,true)??[]);}
function api($method,$path,$data=null){return req($method,'/rest/v1/'.$path,$data,$_SESSION['access']??null);}
function tokens($r){$_SESSION['access']=$r['access_token'];$_SESSION['refresh']=$r['refresh_token'];$_SESSION['expires']=time()+($r['expires_in']??3600);$_SESSION['uid']=$r['user']['id']??$_SESSION['uid']??'';}
function redirect($page='dashboard'){header('Location: ?page='.$page);exit;}
function uuid($v){if(!is_string($v)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',$v))throw new RuntimeException('Registro inválido.');return $v;}
function csrf(){return $_SESSION['csrf']??=bin2hex(random_bytes(32));} function hidden(){return '<input type="hidden" name="csrf" value="'.h(csrf()).'">';}
function checkcsrf(){if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))throw new RuntimeException('Sessão inválida. Atualize a página.');}
function profile(){if(empty($_SESSION['access']))return null;try{if(time()>($_SESSION['expires']??0)-60)tokens(req('POST','/auth/v1/token?grant_type=refresh_token',['refresh_token'=>$_SESSION['refresh']]));$p=api('GET','profiles?id=eq.'.uuid($_SESSION['uid']));return !empty($p[0]['active'])?$p[0]:null;}catch(Throwable $e){return null;}}
function safeurl($v){return is_string($v)&&filter_var($v,FILTER_VALIDATE_URL)&&in_array(parse_url($v,PHP_URL_SCHEME),['http','https'],true)?$v:null;}
function cpfmask($v){$s=preg_replace('/\D/','',(string)$v);return $s? '***.***.***-'.substr($s,-2):'—';}

function exact_count($path):int{return req("GET","/rest/v1/".$path."&limit=1",null,$_SESSION["access"],"application/json",false,true);}

function dashboard_counts():array {
    $r=api('POST','rpc/amicao_dashboard_counts',new stdClass());
    if(!isset($r['adocao'],$r['restricao']))throw new RuntimeException('Indicadores indisponíveis. Confira a migration v1.0.5.');
    return $r;
}
function performance_headers():void {
    if(!headers_sent())header('Server-Timing: supabase;dur='.number_format($GLOBALS['amicao_api_ms']??0,2,'.','').', api_calls;desc="'.(int)($GLOBALS['amicao_api_calls']??0).'"');
}

function create_team_user(array $input): string {
    global $config;
    $name=trim((string)($input['name']??'')); $phone=trim((string)($input['phone']??''));
    $email=trim((string)($input['email']??'')); $password=(string)($input['password']??'');
    if($name===''||strlen($name)>200||strlen($phone)>50||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||strlen($password)<8||strlen($password)>72)throw new RuntimeException('Informe nome, e-mail válido e senha de 8 a 72 bytes. Telefone é opcional.');
    $service=getenv('SUPABASE_SERVICE_ROLE_KEY')?:($config['service_role_key']??'');
    if(!$service)throw new RuntimeException('Criação de usuários indisponível: configure SUPABASE_SERVICE_ROLE_KEY no servidor.');
    try{$r=req('POST','/auth/v1/admin/users',['email'=>$email,'password'=>$password,'email_confirm'=>true,'user_metadata'=>['name'=>$name,'phone'=>$phone]],$service,'application/json',false,false,$service);}
    catch(RuntimeException $e){throw new RuntimeException('Não foi possível criar a conta. Confira se o e-mail já existe ou consulte os logs de Auth no Supabase.');}
    $id=uuid($r['id']??$r['user']['id']??'');
    try{$updated=api('PATCH','profiles?id=eq.'.$id,['role'=>'user','active'=>true]);if(!$updated)throw new RuntimeException('Perfil não atualizado.');}
    catch(Throwable $e){throw new RuntimeException('Conta criada, mas não liberada. Localize o usuário nesta tela e ative o acesso; não crie a conta novamente.');}
    return $id;
}
function local_history_date($value):string {
    try{return (new DateTimeImmutable((string)$value))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s');}catch(Throwable $e){return '—';}
}
function category_label($v):string{return ['adocao'=>'Adoção responsável','restricao'=>'Restrição'][$v??'']??'—';}

function edit_team_user(array $input, array $actor): bool {
    global $config;
    if(($actor['role']??'')!=='admin'||empty($actor['active']))throw new RuntimeException('Acesso negado.');
    $id=uuid($input['id']??'');
    $target=api('GET','profiles?id=eq.'.$id)[0]??null;
    if(!$target)throw new RuntimeException('Usuário não encontrado.');
    $name=trim((string)($input['name']??'')); $phone=trim((string)($input['phone']??''));
    $email=trim((string)($input['email']??'')); $password=(string)($input['password']??'');
    if($name===''||strlen($name)>200||strlen($phone)>50||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||($password!==''&&(strlen($password)<8||strlen($password)>72)))throw new RuntimeException('Informe nome, e-mail válido e, se desejar trocar a senha, utilize de 8 a 72 bytes.');
    $service=getenv('SUPABASE_SERVICE_ROLE_KEY')?:($config['service_role_key']??'');
    if(!$service)throw new RuntimeException('Edição de usuários indisponível: configure a chave administrativa no servidor.');
    $emailChanged=strcasecmp($email,(string)($target['email']??''))!==0;
    $data=['app_metadata'=>['amicao_profile'=>['name'=>$name,'phone'=>$phone,'revision'=>bin2hex(random_bytes(12))]]];
    if($emailChanged){$data['email']=$email;$data['email_confirm']=true;}
    if($password!=='')$data['password']=$password;
    try {req('PUT','/auth/v1/admin/users/'.$id,$data,$service,'application/json',false,false,$service);}
    catch(RuntimeException $e){throw new RuntimeException('Não foi possível atualizar a conta. Confira se o e-mail já existe, os requisitos da senha e os logs de Auth.');}
    $saved=api('GET','profiles?id=eq.'.$id)[0]??null;
    if(!$saved||$saved['name']!==$name||(string)$saved['phone']!==$phone||strcasecmp((string)$saved['email'],$email)!==0)throw new RuntimeException('A conta foi atualizada no Auth, mas o perfil não foi confirmado. Confira a migration v1.0.6.1 antes de repetir a operação.');
    return $id===$actor['id']&&($emailChanged||$password!=='');
}
