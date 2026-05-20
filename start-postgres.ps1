$pgBin = "C:\Users\MY PC\Downloads\postgresql-18.3-3-windows-x64-binaries\pgsql\bin"
$dataDir = "C:\Users\MY PC\Desktop\EXAEARN\.postgres-data"
$logFile = "C:\Users\MY PC\Desktop\EXAEARN\.postgres-data\postgresql.log"

& "$pgBin\pg_ctl.exe" -D $dataDir -l $logFile -o "-p 5432" start
