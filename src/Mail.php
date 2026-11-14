<?php

/**
 * Mail.php
 * @Auteur: Christophe Dufour
 * 
 * Permet l'envoi d'un email simple et rapide en ligne de commande
 */

declare(strict_types=1);

namespace ChrisDfr\Mail;

use ChrisDfr\ConfigJSON\ConfigJSON as Cfg;
use ChrisDfr\GetOptions\GetOptions;
use ChrisDfr\Simplemail\Simplemail;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

class Mail
{
    /**
     * Version de l'application
     */
    const VERSION = "1.0.0";

    /**
     * Instance de PHPMailer
     */
    private PHPMailer $mail;

    /**
     * Mode silencieux
     */
    private bool $quiet = false;

    /**
     * Mode verbeux
     */
    private bool $verbose = false;

    /**
     * Chaine de caractère des options courtes
     */
    private string $shortOptions = "t:s:j:c:qv";

    /**
     * Tableau des options longues
     */
    private array $longOptions = [
        "cc:",
        "help",
        "join:",
        "quiet",
        "to:",
        "subject:",
        "verbose",
        "version"
    ];

    /**
     * Tableau des destinataires
     */
    private array $to = [];

    /**
     * Tableau des destinataires en copie
     */
    private array $cc = [];

    /**
     * Sujet du message
     */
    private string $subject = "";

    /**
     * Tzbleau des pièces jointes
     */
    private array $join = [];

    /**
     * Constructeur
     */
    public function __construct(string $configFile)
    {
        set_exception_handler(array($this, "exceptionHandler"));
        Cfg::load($configFile);
    }

    /**
     * Afficher une erreur et quitter
     *  
     * @return never
     */
    public function exceptionHandler(Throwable $exception): never
    {
        fwrite(STDERR, basename(__FILE__) . ": " . $exception->getMessage() . PHP_EOL);
        fwrite(STDERR, "Saisissez « mail.php --help » pour plus d'informations." . PHP_EOL);
        exit(1);
    }

    /**
     * Vérifie les informations et envoi le mail
     * 
     * @throws Exception
     * Lance des exceptions si divers erreurs surviennent
     * @return void
     */
    public function run(): void
    {
        $this->mail = Simplemail::getMailer(Cfg::$setting['host'], Cfg::$setting['user'], Cfg::$setting['password'], false, false);
        $this->mail->isHTML(true);
        $this->mail->setFrom(Cfg::$setting['email'], Cfg::$setting['name']);

        $this->analyse();

        // Récupération des arguments positionnels
        $argPos = GetOptions::positional();

        if(empty($this->to)) {
            throw new Exception("Options requise manquante -- to");
        }

        if(empty($this->subject)) {
            throw new Exception("Options requise manquante -- subject");
        }

        if(empty($argPos)) {
            throw new Exception("Veuillez entrer un message à envoyer.");
        }

        $msg = implode(' ', $argPos);
        $this->mail->Body = $msg;
        $this->mail->AltBody = $msg;
        
        if($this->mail->send() === FALSE) {
            throw new Exception("Erreur: message non envoyé.");
        }
        
        $this->status();
    }

    /**
     * Analyse de options
     * 
     * @return void
     */
    private function analyse(): void
    {
        // Récupération des options
        $options = GetOptions::options($this->shortOptions, $this->longOptions);

        restore_exception_handler();

        foreach($options as $opts) {
            if(array_key_exists("help", $opts)) {
                $this->usage();
            }

            if(array_key_exists("version", $opts)) {
                $this->version();
            }

            foreach($opts as $opt => $value) {
                switch($opt) {
                    case "t":
                    case "to": {
                        $this->to[]=$value;

                        if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Format d'adresse email invalide -- $value");
                        }

                        $this->mail->addAddress($value);
                    } break;

                    case "c":
                    case "cc": {
                        $this->cc[]=$value;

                        if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Format d'adresse email invalide -- $value");
                        }

                        $this->mail->addCC($value);
                    } break;

                    case "s":
                    case "subject": {
                        if(!empty($this->subject)) {
                            throw new Exception("L'option suivante doit être unique -- subject.");
                        }

                        $this->subject = $value;
                        $this->mail->Subject = $value;
                    } break;

                    case "j":
                    case "join": {
                        $this->join[] = $value;

                        if(!is_readable($value) || is_link($value)) {
                            throw new Exception("Impossible de joindre le fichier -- $value");
                        }
                        $this->mail->addAttachment($value);
                    } break;

                    case "q":
                    case "quiet":
                        $this->quiet = true;
                        break;
                        
                    case "v":
                    case "verbose":
                        $this->verbose = true;
                        break;
                }
            }
        }
    }

    /**
     * Affichier d'éventuels informations
     * 
     * @return void
     */
    private function status(): void
    {
        if(!$this->quiet) {
            if(!$this->verbose) {
                echo "Message envoyé." . PHP_EOL;
            }
            else {
                echo "Destinataire       : ";
                foreach($this->to as $t) {
                    echo "$t  ";
                }
                echo PHP_EOL;

                if(!empty($this->cc)) {
                    echo "Copie conforme à   : ";
                    foreach($this->cc as $c) {
                        echo "$c  ";
                    }
                }
                echo PHP_EOL;
                echo "Sujet              : $this->subject" . PHP_EOL;
                
                if(!empty($this->join)) {
                    echo "Pièce(s) jointe(s) : ";
                    foreach($this->join as $j) {
                        echo "$j  ";
                    }
                    echo PHP_EOL;
                }
                echo "Message envoyé" . PHP_EOL;
            }
        }
    }

    /**
     * Afficher l'aide et quitter
     * 
     * @return never
     */
    private function usage(): never
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
     * @return never
     */
    private function version(): never
    {
        echo "mail.php version " . self::VERSION . "
Licence GPLv3+ : GNU GPL version 3 ou ultérieure <https://gnu.org/licenses/gpl.html>
Logiciel libre : vous êtes libre de le modifier ou de le redistribuer.
Il n'y a AUCUNE GARANTIE, dans les limites permises par la loi.

Ecrit par Christophe Dufour
";
        exit(0);
    }

}
