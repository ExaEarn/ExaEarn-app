$pgBin = "C:\Users\MY PC\Downloads\postgresql-18.3-3-windows-x64-binaries\pgsql\bin"
$dataDir = "C:\Users\MY PC\Desktop\EXAEARN\.postgres-data"

& "$pgBin\pg_ctl.exe" -D $dataDir stop
