@echo off
:: Batch script to package OpenCart 3 and OpenCart 4 extensions cleanly under Windows
setlocal enabledelayedexpansion

set "ROOT_DIR=%~dp0"
set "DIST_DIR=%ROOT_DIR%dist"

if exist "%DIST_DIR%" rmdir /s /q "%DIST_DIR%"
mkdir "%DIST_DIR%"

echo ==========================================================
echo Packaging Filecheck OpenCart Extensions...
echo ==========================================================

:: Package OpenCart 3
if exist "%ROOT_DIR%oc3" (
    echo Packaging OpenCart 3...
    cd /d "%ROOT_DIR%oc3"
    powershell -Command "Compress-Archive -Path 'upload', 'install.xml' -DestinationPath '%DIST_DIR%\filecheck-oc3.ocmod.zip' -Force"
    echo Success: dist/filecheck-oc3.ocmod.zip created.
)

:: Package OpenCart 4
if exist "%ROOT_DIR%oc4" (
    echo Packaging OpenCart 4...
    cd /d "%ROOT_DIR%oc4"
    powershell -Command "Compress-Archive -Path 'admin', 'catalog', 'system', 'install.json', 'install.xml' -DestinationPath '%DIST_DIR%\filecheck-oc4.ocmod.zip' -Force"
    echo Success: dist/filecheck-oc4.ocmod.zip created.
)

echo ==========================================================
echo Build complete! Check the 'dist/' folder for your release zips.
echo ==========================================================
cd /d "%ROOT_DIR%"
pause
