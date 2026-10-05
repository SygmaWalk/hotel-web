# Continuar en otra PC

GitHub guarda el código, el esquema y los scripts de instalación y demostración. MySQL guarda los datos localmente en cada computadora: hacer pull no copia ni sincroniza una base de datos.

## Primera preparación en Windows con XAMPP

1. Instalar XAMPP con PHP 8.2 o superior y Git. Iniciar Apache y MySQL. Las instrucciones siguientes asumen la configuración local estándar de MySQL: puerto 3306 y root sin contraseña.
2. Si ya tenés el repositorio en C:\xampp\htdocs\hotel-web, abrir PowerShell y actualizarlo:

```powershell
cd C:\xampp\htdocs\hotel-web
git pull --ff-only origin main
```

Si todavía no está clonado:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/SygmaWalk/hotel-web.git
cd hotel-web
```

3. Si esta PC todavía no tiene la base ni la configuración del proyecto, ejecutar:

```powershell
& C:\xampp\php\php.exe scripts/setup.php
```

Este comando crea la base hotel_aurora, sus tablas, tres habitaciones, un usuario de base con permisos limitados y las cuentas de administrador y staff. Genera config/local.php y config/access.local.txt; ambos quedan fuera de Git. Las contraseñas serán nuevas y diferentes de las de la otra PC. Abrí config/access.local.txt desde el explorador o editor para consultarlas.

El instalador se detiene si encuentra config/local.php y no reemplaza una base hotel_aurora preexistente. Si hay una base o un usuario hotel_aurora_app de una instalación anterior, revisar esa instalación antes de ejecutar setup; no borrar nada para forzar la instalación. Si copiaste una carpeta con configuración privada, un pull tampoco corregirá automáticamente esa conexión.

4. Para reproducir los ocho casos ficticios del proyecto:

```powershell
& C:\xampp\php\php.exe scripts/seed-demo.php 2026-10-05
```

La carga agrega tres pendientes, cuatro confirmadas y una rechazada. Repetirla no duplica ni restablece datos. Para crear los mismos escenarios relativos al día actual en una instalación nueva, omitir la fecha; elegir una alternativa antes de la primera carga. Los cambios posteriores en reservas de otra PC no se transfieren mediante este script.

5. Abrir http://localhost/hotel-web/public/ e iniciar sesión con las cuentas generadas en esta PC. Para ver los casos de la fecha fija, consultar el panel o calendario del 05/10/2026. Una fecha distinta puede mostrar otros totales aunque los datos estén cargados.

## Siguientes actualizaciones

Con la instalación funcionando, basta con git pull --ff-only origin main para actualizar el código. Esta entrega no agrega tablas ni columnas: si ya tenés la base funcionando, no necesitás volver a ejecutar setup. La configuración y las reservas locales se conservan. Si Git informa cambios locales o ramas divergentes, resolverlos antes de actualizar; no usar un reset para descartar trabajo.

Para trabajar alternando PCs: guardar y subir los cambios de código en una antes de hacer pull en la otra. Las reservas, cuentas y contraseñas de MySQL permanecen independientes.

Si en el futuro necesitás trasladar exactamente una base con cambios propios, habrá que exportarla e importarla de forma privada, con una copia de respaldo del destino. No subir dumps con cuentas o datos personales al repositorio. Una base remota compartida sería otra configuración distinta; no es necesaria para continuar este proyecto localmente.

## Comprobación opcional

```powershell
& C:\xampp\php\php.exe tests/run.php
& C:\xampp\php\php.exe tests/seed-demo.php
```

Resultados de esta entrega: 127 y 17 verificaciones respectivamente. Las pruebas crean y eliminan únicamente sus bases temporales, no hotel_aurora. Requieren las extensiones PHP PDO MySQL, mbstring y cURL, proc_open habilitado y acceso al MySQL local. Ante una configuración de MySQL personalizada, revisar README.md antes de instalar.