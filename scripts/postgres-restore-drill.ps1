param([Parameter(Mandatory=$true)][string]$SourceDatabase,[string]$DrillDatabase="exaearn_restore_drill")
$ErrorActionPreference='Stop'
if($SourceDatabase -match 'prod'){throw 'Refusing a database name containing prod. Use an approved non-production source.'}
$started=Get-Date;$dump=Join-Path $env:TEMP "exaearn-restore-drill-$([guid]::NewGuid()).dump"
try{pg_dump --format=custom --no-owner --no-acl --file=$dump $SourceDatabase; dropdb --if-exists $DrillDatabase; createdb $DrillDatabase; pg_restore --exit-on-error --no-owner --no-acl --dbname=$DrillDatabase $dump; $env:DB_DATABASE=$DrillDatabase; Push-Location "$PSScriptRoot\..\backend\api-gateway"; php artisan migrate:status; php artisan tinker --execute="foreach (['developer_projects','developer_production_access_requests','developer_api_keys','developer_webhook_endpoints','audit_logs'] as `$t) { echo `$t.':'.DB::table(`$t)->count().PHP_EOL; }"; Pop-Location; Write-Output "RESTORE_DRILL_SECONDS=$([int]((Get-Date)-$started).TotalSeconds)"}finally{if(Test-Path $dump){Remove-Item -LiteralPath $dump -Force}}

