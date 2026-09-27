<?php
/* Enrutador para el servidor embebido de PHP.
   Apache ejecuta cada script con el directorio de trabajo puesto en la carpeta
   del propio script; el servidor embebido lo deja en la raíz del sitio. Sin
   este ajuste, los require_once '../includes/...' de los endpoints no
   resolverían a la misma ruta que en producción. */
$ruta    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$archivo = $_SERVER['DOCUMENT_ROOT'] . $ruta;

if (is_file($archivo) && substr($archivo, -4) === '.php') {
    chdir(dirname($archivo));
    require $archivo;
    return true;
}
return false;   // el resto lo sirve el servidor como estático
