<?php
declare(strict_types=1);

define('SOKNA_S2S_REQUEST', true);
define('SOKNA_JSON_ENDPOINT', true);
require dirname(__DIR__).'/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

function core_directory_reply(array $payload,int $status=200): never
{
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function core_directory_fail(string $code,int $status): never
{
    core_directory_reply(['ok'=>false,'error'=>['code'=>$code]],$status);
}

if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))!=='GET'){
    header('Allow: GET');
    core_directory_fail('METHOD_NOT_ALLOWED',405);
}

$pageRaw=(string)($_GET['page']??'1');
$perPageRaw=(string)($_GET['per_page']??'50');
if(!preg_match('/^[1-9][0-9]*$/',$pageRaw)||!preg_match('/^[1-9][0-9]*$/',$perPageRaw))core_directory_fail('DIRECTORY_PAGINATION',422);
$page=(int)$pageRaw;$perPage=(int)$perPageRaw;
if($page<1||$perPage<1||$perPage>100)core_directory_fail('DIRECTORY_PAGINATION',422);

$authorization=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if($authorization==='')core_directory_fail('DIRECTORY_AUTH',401);

try{
    SoknaCoreClient::verifyDirectoryAuthorization($authorization,$page,$perPage);
}catch(Throwable $e){
    $code=$e->getMessage();
    $status=match($code){
        'CORE_NOT_PAIRED'=>503,
        'DIRECTORY_REPLAY'=>409,
        'DIRECTORY_PAGINATION'=>422,
        'DIRECTORY_SCOPE'=>403,
        'DIRECTORY_REPLAY_STORE'=>503,
        default=>401,
    };
    core_directory_fail(preg_match('/^[A-Z0-9_:-]{1,80}$/',$code)?$code:'DIRECTORY_AUTH',$status);
}

$offset=($page-1)*$perPage;
try{
    $total=(int)db()->query('SELECT COUNT(*) FROM '.table('users'))->fetchColumn();
    $stmt=db()->prepare('SELECT id,display_name,role,active,updated_at FROM '.table('users').' ORDER BY id ASC LIMIT ? OFFSET ?');
    $stmt->bindValue(1,$perPage,PDO::PARAM_INT);
    $stmt->bindValue(2,$offset,PDO::PARAM_INT);
    $stmt->execute();
    $rows=$stmt->fetchAll();
}catch(Throwable $e){
    log_exception($e,'core_user_directory_query');
    core_directory_fail('DIRECTORY_UNAVAILABLE',503);
}

$data=[];
foreach($rows as $row){
    $data[]=[
        'local_user_id'=>(string)(int)$row['id'],
        'display_name'=>(string)$row['display_name'],
        'role'=>(string)$row['role'],
        'active'=>(bool)(int)$row['active'],
        'updated_at'=>(string)$row['updated_at'],
    ];
}
$pages=$total===0?0:(int)ceil($total/$perPage);
core_directory_reply([
    'ok'=>true,
    'data'=>$data,
    'pagination'=>[
        'page'=>$page,
        'per_page'=>$perPage,
        'total'=>$total,
        'pages'=>$pages,
        'has_next'=>$page<$pages,
    ],
]);
