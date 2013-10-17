<?php

/**
 * DbDumperMailer
 * отправляет дамп базы mysql на email
 *
 * PHP version 5
 *
 * @category Website
 * @package  Application
 * @author   Vladimir Chmil <vladimir.chmil@gmail.com>
 * @license  http://mit-license.org/ MIT license
 * @link     http://xxx
 */

/**
 * отправляет дамп базы mysql на email
 * Дамп сжат в gz
 *
 * PHP version 5
 *
 * @category Website
 * @package  Application
 * @author   Vladimir Chmil <vladimir.chmil@gmail.com>
 * @license  http://mit-license.org/ MIT license
 * @link     http://xxx
 */
class DbDumperMailer extends DbDumper
{
    protected $email = "vladimir.chmil@gmail.com";

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    protected function start()
    {
        return $this;
    }

    protected function finish()
    {
        gzclose($this->ex_gz);

        $mailer = new PHPMailer(true);
        $mailer->setFrom($this->email);
        $mailer->CharSet  = "Windows-1251";
        $mailer->WordWrap = 80;

        $mailer->addAddress($this->email);

        $mailer->Subject = "[" . date('H:i d/m/Y') . "] Дамп базы";

        $mbody = <<<EOD
Здравствуйте

Во вложении дамп базы от %s
EOD;

        $mbody = sprintf($mbody, date('H:i d/m/Y'));

        $mailer->msgHTML(nl2br($mbody));
        $mailer->AltBody = $mbody;
        $mailer->AddAttachment(
            $this->tmpf,
            "masterovoy-" . date('d-m-Y-h-i-s') . ".sql.gz"
//            "base64",
//            "application/x-gzip"
        );

        $mailer->send();
    }
}
