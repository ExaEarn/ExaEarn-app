@echo off
for %%I in ("%~dp0..\..") do set "REPO_ROOT=%%~fI"
set "USERPROFILE=%REPO_ROOT%"
set "HOME=%REPO_ROOT%"
set "EXPO_NO_TELEMETRY=1"

cd /d "%REPO_ROOT%"
echo Starting Expo mobile server on localhost:8082 > apps\mobile\expo-server.log
pnpm.cmd --dir apps\mobile exec expo start --localhost --port 8082 >> apps\mobile\expo-server.log 2>&1
