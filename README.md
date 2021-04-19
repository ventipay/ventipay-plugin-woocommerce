# ventipay-plugin-woocommerce

# EN DESARROLLO

# Instalación

* Instalar Docker para macOS
* En Docker instalar la imagen llamada "wordpress" (https://hub.docker.com/_/wordpress)
* Al instalar y configurar Wordpress, ingresar al administrador en la URL /wp-admin
* En el administrador, instalar el plugin "WooCommerce" (https://docs.woocommerce.com/document/installing-uninstalling-woocommerce/)
* Con WooCommerce instalado, se debe instalar este plugin "zipeando" el directorio completo y subiendolo desde la sección "Plugins"
* Habilitar el plugin "VENTI Pay"
* Luego en la sección "Payments" de la configuración de WooCommerce, se debe configurar la API Key de VENTI Pay

Nota: cada vez que se quiera hacer un cambio, se debe zipear el contenido del directorio y volver a subirlo. Wordpress dará la opción de reemplazar el contenido del plugin ya instalado.