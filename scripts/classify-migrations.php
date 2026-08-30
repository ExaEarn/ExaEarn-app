<?php
declare(strict_types=1);
$root=dirname(__DIR__).'/backend/api-gateway/database/migrations';$jsonTarget=null;$fail=in_array('--fail-on-unclassified',$argv,true);
foreach($argv as $argument)if(str_starts_with($argument,'--json='))$jsonTarget=substr($argument,7);
function migrationUp(string $body):string{if(!preg_match('/public\s+function\s+up\s*\([^)]*\)\s*:\s*void\s*\{(?<up>.*)public\s+function\s+down\s*\(/sU',$body,$match))return '';return $match['up'];}
function classifyMigration(string $up):array{
 $signals=[];$rules=[
  'DROP_TABLE'=>'/Schema::(?:drop|dropIfExists)\s*\(|\bDROP\s+TABLE\b/i','DROP_COLUMN'=>'/->dropColumn\s*\(|\bDROP\s+COLUMN\b/i','RENAME'=>'/Schema::rename\s*\(|->renameColumn\s*\(|\bRENAME\s+(?:COLUMN|TABLE)\b/i',
  'ALTER_TYPE_OR_COLUMN'=>'/->change\s*\(|\bALTER\s+COLUMN\b|\bTYPE\s+(?:VARCHAR|TEXT|INTEGER|BIGINT|DECIMAL|NUMERIC|TIMESTAMP)/i','RAW_DDL'=>'/DB::(?:statement|unprepared)\s*\(|\bALTER\s+TABLE\b|\bCREATE\s+(?:UNIQUE\s+)?INDEX\b/i',
  'INDEX_OR_CONSTRAINT'=>'/->(?:index|unique|foreign|dropIndex|dropUnique|dropForeign)\s*\(/i','NOT_NULL_OR_DEFAULT'=>'/->nullable\s*\(\s*false\s*\)|->default\s*\(|\bSET\s+NOT\s+NULL\b|\bSET\s+DEFAULT\b/i',
  'DATA_BACKFILL'=>'/DB::(?:table|update|delete|insert)|->update\s*\(|->delete\s*\(|\bUPDATE\s+\w+\s+SET\b|\bDELETE\s+FROM\b/i'];
 foreach($rules as $name=>$pattern)if(preg_match($pattern,$up))$signals[]=$name;
 if(array_intersect($signals,['DROP_TABLE','DROP_COLUMN']))return['classification'=>'DESTRUCTIVE','signals'=>$signals];
 if(in_array('DATA_BACKFILL',$signals,true))return['classification'=>'DATA_MIGRATION','signals'=>$signals];
 if(array_intersect($signals,['ALTER_TYPE_OR_COLUMN','RAW_DDL','RENAME']))return['classification'=>'POSTGRES_REHEARSAL_REQUIRED','signals'=>$signals];
 if(array_intersect($signals,['INDEX_OR_CONSTRAINT','NOT_NULL_OR_DEFAULT']))return['classification'=>'REVIEW_REQUIRED','signals'=>$signals];
 if($up!==''&&preg_match('/Schema::create\s*\(/',$up)&&!preg_match('/Schema::table\s*\(/',$up))return['classification'=>'SAFE_AUTOMATED','signals'=>[]];
 if($up!==''&&preg_match('/Schema::table\s*\(/',$up))return['classification'=>'REVIEW_REQUIRED','signals'=>['SCHEMA_TABLE_CHANGE']];
 return['classification'=>'REVIEW_REQUIRED','signals'=>['UNKNOWN_PATTERN']];
}
$records=[];foreach(glob($root.'/*.php')?:[]as$file){$result=classifyMigration(migrationUp((string)file_get_contents($file)));$records[]=['migration'=>basename($file)]+$result;}
$counts=[];foreach($records as$record)$counts[$record['classification']]=($counts[$record['classification']]??0)+1;ksort($counts);$report=['generated_at'=>gmdate(DATE_ATOM),'database'=>'postgresql','total'=>count($records),'counts'=>$counts,'migrations'=>$records];
if($jsonTarget){$path=preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/',$jsonTarget)?$jsonTarget:dirname(__DIR__).'/'.$jsonTarget;if(!is_dir(dirname($path)))mkdir(dirname($path),0777,true);file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);}
foreach($counts as$class=>$count)echo"{$class}: {$count}\n";$unknown=array_filter($records,fn(array$r):bool=>in_array('UNKNOWN_PATTERN',$r['signals'],true));if($fail&&$unknown!==[]){fwrite(STDERR,"Unclassified migrations require explicit review:\n - ".implode("\n - ",array_column($unknown,'migration'))."\n");exit(1);}
