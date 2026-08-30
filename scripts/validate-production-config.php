<?php
declare(strict_types=1);
$required=['APP_KEY','APP_URL','DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD','REDIS_HOST','NODE_SERVICE_SECRET','TRUSTED_PROXIES'];
$forbidden=['APP_DEBUG'=>['true','1'],'APP_ENV'=>['local','testing'],'DEVELOPER_API_RUNTIME_ENVIRONMENT'=>['sandbox'],'SECURITY_API_SIGNATURE_REQUIRED'=>['false','0']];
$ci=in_array('--ci',$argv,true);$errors=[];
if($ci){
 $example=file_get_contents(dirname(__DIR__).'/backend/api-gateway/.env.example');
 foreach(array_merge($required,array_keys($forbidden),['DEVELOPER_PRODUCTION_WEBHOOK_DELIVERY_ENABLED','DEVELOPER_PRODUCTION_WEBHOOK_EGRESS_VERIFIED','WEBHOOK_EGRESS_PROXY']) as $name)if(!preg_match('/^'.preg_quote($name,'/').'=.*$/m',$example))$errors[]="{$name} is missing from .env.example";
 if(!preg_match('/^DEVELOPER_PRODUCTION_WEBHOOK_DELIVERY_ENABLED=false$/m',$example))$errors[]='Production webhook delivery must default to false';
 if(!preg_match('/^DEVELOPER_PRODUCTION_WEBHOOK_EGRESS_VERIFIED=false$/m',$example))$errors[]='Production webhook egress must default to unverified';
}
foreach($required as $name){$value=getenv($name);if(!$ci && ($value===false || trim((string)$value)===''))$errors[]="{$name} is required";}
foreach($forbidden as $name=>$values){$value=strtolower((string)getenv($name));if(!$ci && in_array($value,$values,true))$errors[]="{$name} has a forbidden production value";}
if(!$ci && strtolower((string)getenv('DEVELOPER_PRODUCTION_WEBHOOK_DELIVERY_ENABLED'))==='true' && (getenv('WEBHOOK_EGRESS_PROXY')===false || strtolower((string)getenv('DEVELOPER_PRODUCTION_WEBHOOK_EGRESS_VERIFIED'))!=='true'))$errors[]='Verified WEBHOOK_EGRESS_PROXY is required before production webhook delivery';
if($errors){fwrite(STDERR,implode(PHP_EOL,$errors).PHP_EOL);exit(1);}echo "Production configuration policy: PASS\n";
