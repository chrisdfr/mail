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

// Version de l'application
define("VERSION", "1.0");
// Dossier racine
define("ROOT_DIR", __DIR__);
// Fichier de configuration
define("CONFIG_FILE", ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, "/config/conf.json"));

// Autoload de Composer
require ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, "/vendor/autoload.php");

// Classe à utiliser
use ChrisDfr\GetOptions\GetOptions;
use ChrisDfr\Simplemail\Simplemail;

/**
 * Lit la configuration dans le fichier de conf
 * 
 * @param string $filename
 * Le nom du fichier de configuration
 * @return array
 * Retourne un tableau contenant la configuration
 * @throws Exception
 * 
 */
function loadConfig(string $filename): array
{
    if(!is_readable($filename)) {
        throw new Exception("Impossible de lire le fichier de configuration.");
    }

    $file = new SplFileObject($filename, "r");
    $contents = $file->fread($file->getSize());

    return json_decode($contents, true);
}

/**
 * Afficher l'aide et quitter
 * 
 * @return void
 */
function usage(): void
{
    echo "mail.php
Utilisation: mail [OPTIONS] --to DESTINATAIRE --subject \"SUJET\" \"MESSAGE\"
Envoie un mail au DESTINATAIRE avec comme sujet SUJET comme message MESSAGE

Options requise:
    -t, --to        : le(s) destinataire(s) du message.
    -s, --subject   : le sujet du message.

Options:
    -c, --cc        : Le(s) destinataire(s) en copie pour le message.
    -j, --join      : Pièce jointe à ajouter.
    -v, --verbose   : mode verbeux.
    -q, --quiet     : mode silence.
    --help          : afficher l'aide et quitter.
    --version       : afficher des informations de version et quitter.
";
    exit(0);
}

/**
 * Afficher des informations de version et quitter
 * 
 * @return void
 */
function version(): void
{
    echo "mail.php version " . VERSION . "
Licence GPLv3+ : GNU GPL version 3 ou ultérieure <https://gnu.org/licenses/gpl.html>
Logiciel libre : vous êtes libre de le modifier ou de le redistribuer.
Il n'y a AUCUNE GARANTIE, dans les limites permises par la loi.

Ecrit par Christophe Dufour
";
    exit(0);
}

try {
    $config = loadConfig(CONFIG_FILE);
    $mail = Simplemail::getMailer($config['host'], $config['user'], $config['password'], false, false);
    $mail->isHTML(true);
    $mail->setFrom($config['email'], $config['name']);

    $quiet = false;
    $verbose = false;
    $shortOptions = "t:s:j:c:qv";
    $longOptions = [
        "cc:",
        "help",
        "join:",
        "quiet",
        "to:",
        "subject:",
        "verbose",
        "version"
    ];

    // Récupération des options
    $options = GetOptions::options($shortOptions, $longOptions);
    // Récupération des arguments positionnels
    $argPos = GetOptions::positional();

    foreach($options as $opts) {
        if(array_key_exists("help", $opts)) {
            usage();
        }

        if(array_key_exists("version", $opts)) {
            version();
        }

        foreach($opts as $opt => $value) {
            switch($opt) {
                case "t":
                case "to": {
                    $to[]=$value;

                    if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Format d'adresse email invalide -- $value");
                    }

                    $mail->addAddress($value);
                } break;

                case "c":
                case "cc": {
                    $cc[]=$value;

                    if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Format d'adresse email invalide -- $value");
                    }

                    $mail->addCC($value);
                } break;

                case "s":
                case "subject": {
                    if(isset($subject)) {
                        throw new Exception("L'option suivante doit être unique -- subject.");
                    }

                    $subject = $value;
                    $mail->Subject = $value;
                } break;

                case "j":
                case "join": {
                    $join[] = $value;

                    if(!is_readable($value) || is_link($value)) {
                        throw new Exception("Impossible de joindre le fichier -- $value");
                    }
                    $mail->addAttachment($value);
                } break;

                case "q":
                case "quiet":
                    $quiet = true;
                    break;
                    
                case "v":
                case "verbose":
                    $verbose = true;
                    break;
            }
        }
    }

    if(!isset($to)) {
        throw new Exception("Options requise manquante -- to");
    }

    if(!isset($subject)) {
        throw new Exception("Options requise manquante -- subject");
    }

    if(empty($argPos)) {
        throw new Exception("Veuillez entrer un message à envoyer.");
    }

    $msg = implode(' ', $argPos);
    $mail->Body = $msg;
    $mail->AltBody = $msg;
    
    if($mail->send() === FALSE) {
        throw new Exception("Erreur: message non envoyé.");
    }
    else {
        if(!$quiet) {
            if(!$verbose) {
                echo "Message envoyé." . PHP_EOL;
            }
            else {
                echo "Destinataire: ";
                foreach($to as $t) {
                    echo "$t  ";
                }
                echo PHP_EOL;

                if(isset($cc)) {
                    echo "Copie conforme à: ";
                    foreach($cc as $c) {
                        echo "$c  ";
                    }
                    echo PHP_EOL;
                }

                echo "Sujet: $subject" . PHP_EOL;
                
                if(isset($join)) {
                    echo "Pièce(s) jointe(s): ";
                    foreach($join as $j) {
                        echo "$j  ";
                    }
                    echo PHP_EOL;
                }
                echo "Message envoyé" . PHP_EOL;
            }
        }
    }

}
catch(Exception $e) {
    fwrite(STDERR, basename(__FILE__) . ": " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Saisissez « mail.php --help » pour plus d'informations." . PHP_EOL);
    exit(1);
}

// Fin du script
exit(0);
