param(
  [string]$AndroidSdk = 'C:\AndroidSdk',
  [string]$JdkRoot = 'C:\Program Files\Eclipse Adoptium'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $AndroidSdk -PathType Container)) {
  throw "Android SDK was not found at $AndroidSdk. Install/configure the SDK before building."
}

$jdk = Get-ChildItem -LiteralPath $JdkRoot -Directory -ErrorAction Stop |
  Where-Object { $_.Name -match '^jdk-17(?:\.|$)' } |
  Sort-Object Name -Descending |
  Select-Object -First 1
if (-not $jdk) { throw "Eclipse Temurin JDK 17 was not found under $JdkRoot." }
$java = Join-Path $jdk.FullName 'bin\java.exe'
if (-not (Test-Path -LiteralPath $java -PathType Leaf)) { throw 'The selected JDK does not contain java.exe.' }

# These changes are process-scoped. Nothing is written to Windows user or machine settings.
$env:JAVA_HOME = $jdk.FullName
$env:ANDROID_HOME = $AndroidSdk
$env:ANDROID_SDK_ROOT = $AndroidSdk
$env:PATH = (Join-Path $jdk.FullName 'bin') + ';' +
  (Join-Path $AndroidSdk 'platform-tools') + ';' +
  (Join-Path $AndroidSdk 'cmdline-tools\latest\bin') + ';' + $env:PATH

$javaVersion = (& $java -version 2>&1 | Select-Object -First 1 | Out-String).Trim()
if ($javaVersion -notmatch '"17\.') { throw "Expected JDK 17, found: $javaVersion" }

$node = Get-Command node -ErrorAction SilentlyContinue
$requiredNdk = Join-Path $AndroidSdk 'ndk\27.1.12297006'
$requiredCmake = Join-Path $AndroidSdk 'cmake\3.22.1'
$missing = @()
if (-not (Test-Path -LiteralPath $requiredNdk -PathType Container)) { $missing += 'ndk;27.1.12297006' }
if (-not (Test-Path -LiteralPath $requiredCmake -PathType Container)) { $missing += 'cmake;3.22.1' }
$nodeIssue = $null
if (-not $node) {
  $nodeIssue = 'Node.js is not available on PATH. Install/select the repository-supported Node 22 runtime.'
} else {
  $nodeVersion = (& $node.Source --version).Trim()
  if ($nodeVersion -notmatch '^v22\.') { $nodeIssue = "Expected Node 22, found $nodeVersion. Select Node 22 before running Expo/Gradle." }
}
if ($missing.Count -gt 0) {
  $quoted = ($missing | ForEach-Object { '"' + $_ + '"' }) -join ' '
  throw "Missing Android SDK components: $($missing -join ', '). Install with: sdkmanager $quoted"
}
if ($nodeIssue) { throw $nodeIssue }

Write-Output "Android build environment ready (process scope): SDK=$AndroidSdk; JDK=$($jdk.FullName); Node=$nodeVersion"
