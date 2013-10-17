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
    protected function start()
    {
        return $this;
    }

    protected function finish()
    {
        gzclose($this->ex_gz);
        var_dump($this->tmpf);
//        readfile($this->tmpf);
//        flush();
    }
}
