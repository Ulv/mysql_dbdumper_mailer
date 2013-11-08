<?php

/**
 * DbDumperMailer
 * отправляет дамп базы mysql на email
 * Наследует класс DbDumper
 *
 * PHP version 5
 *
 * @category Website
 * @package  Supplemental
 * @author   Vladimir Chmil <vladimir.chmil@gmail.com>
 * @license  http://mit-license.org/ MIT license
 * @link     http://xxx
 */

/**
 * отправляет дамп базы mysql на email
 * Дамп в аттаче в gz
 *
 * Использование:
 * <code>
 *  $dbdumper = new DbDumperMailer(
 *      $db_user,
 *      $db_pass,
 *      $db_dbase,
 *      $db_server
 *  );
 *
 *  $dbdumper->setEmail($this->admin_mail);
 *  $dbdumper->export();
 * </code>
 *
 * Для отсылки почты исп, PHPMailer
 * (https://github.com/PHPMailer/PHPMailer)
 *
 * PHP version 5
 *
 * @category Website
 * @package  Supplemental
 * @author   Vladimir Chmil <vladimir.chmil@gmail.com>
 * @license  http://mit-license.org/ MIT license
 * @link     http://xxx
 */
class DbDumperMailer extends DbDumper
{
    protected $email = "vladimir.chmil@gmail.com";

    /**
     * возвращает email на который уйдет дамп
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * устанавливает email на который уйдет дамп
     *
     * @param string $email email на который уйдет дамп
     *
     * @return void
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * начало. Заглушим вывод заголовков
     * родительского класса
     *
     * @return $this
     */
    protected function start()
    {
        return $this;
    }

    /**
     * окончание процесса, отсылка дампа на email
     *
     * @return boolean
     */
    protected function finish()
    {
        gzclose($this->ex_gz);

        $mailer = new PHPMailer(true);
        $mailer->setFrom($this->email);
        $mailer->CharSet  = "utf8";
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
        );

        return $mailer->send();
    }
}
