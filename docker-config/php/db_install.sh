#!/bin/sh
echo "Creando y SCHEMA de Base de Datos"
php bin/console doctrine:migrations:migrate
echo "Insertando Fixtures Base de Datos"
php bin/console doctrine:fixtures:load