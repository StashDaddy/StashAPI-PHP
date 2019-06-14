@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../theseer/phpdox/phpdox
php "%BIN_TARGET%" %*
