@echo off

REM based on BatchGotAdmin by Evan Greene
REM https://sites.google.com/site/eneerge/scripts/batchgotadmin
REM  --> Check for permissions
>nul 2>&1 "at"

REM --> If error flag set, we do not have admin.
if '%errorlevel%' NEQ '0' (
    echo 0
) else ( echo 1 )
