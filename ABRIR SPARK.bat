@echo off
chcp 65001 >nul
title SPARK
color 0D

echo.
echo   ================================================
echo      SPARK - Quebre o Bloqueio Criativo
echo   ================================================
echo.
echo      Endereco:  http://localhost/spark-app/
echo.

set XAMPP=C:\xampp

if not exist "%XAMPP%\apache\bin\httpd.exe" (
  echo   [ERRO] Nao encontrei o XAMPP em %XAMPP%
  echo   Ajuste a variavel XAMPP no topo deste arquivo.
  pause
  exit /b 1
)

echo   [1/3] Ligando o Apache...
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if errorlevel 1 (
  start "" /B "%XAMPP%\apache\bin\httpd.exe"
  timeout /t 3 /nobreak >nul
  echo         Apache iniciado.
) else (
  echo         Apache ja estava rodando.
)

echo   [2/3] Ligando o MySQL...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul
if errorlevel 1 (
  start "" /B "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini" --standalone
  timeout /t 6 /nobreak >nul
  echo         MySQL iniciado.
) else (
  echo         MySQL ja estava rodando.
)

echo   [3/3] Abrindo o Spark no navegador...
timeout /t 2 /nobreak >nul
start http://localhost/spark-app/

echo.
echo   ================================================
echo      Pronto! O Spark esta no ar em:
echo      http://localhost/spark-app/
echo   ================================================
echo.
echo   IMPORTANTE
echo   Nao extraia o ZIP dentro do htdocs. O site ja esta
echo   instalado ali. Cada extracao cria uma copia congelada
echo   que compete com a versao real e parece "nao mudou nada".
echo.
echo   Em duvida sobre qual versao esta na tela, abra
echo   Configuracoes e confira o carimbo no rodape:
echo   ele mostra a data do codigo e a pasta que respondeu.
echo.
pause
