#! /usr/bin/env php
<?php

/**
 * mail.php
 * @Auteur: Christophe Dufour
 * 
 * Mail permet l'envoi d'un email simple et rapide en ligne de commande
 */

// Est-on bien en mode console ?
if(php_sapi_name() !== "cli") {
    fwrite(STDERR, basename(__FILE__) . ": Ce script ne peut être utilisé qu'en ligne de commande." . PHP_EOL);
    exit(1);
}

// Autoload de Composer
require __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, "/vendor/autoload.php");

use ChrisDfr\Mail\Mail;

$configFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, "/config/conf.json");

$mail = new Mail($configFile);
$mail->run();

// Fin du script
exit(0);
