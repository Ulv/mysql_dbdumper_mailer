<?php
/**
 * DbDumper
 * делает дамп базы mysql в формате, читаемом phpmyadmin
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
 * DbDumper
 * делает дамп базы mysql в формате, читаемом phpmyadmin
 * Отдает дамп сжатый в gz
 *
 * PHP version 5
 *
 * @category Website
 * @package  Application
 * @author   Vladimir Chmil <vladimir.chmil@gmail.com>
 * @license  http://mit-license.org/ MIT license
 * @link     http://xxx
 */

class DbDumper
{
    /**
     * @var mysqli
     */
    protected static $db;
    /**
     * @var array таблички из базы
     */
    protected $tables = array();
    /**
     * @var array таблички, которых в базе быть не должно
     */
    protected $exclude_tables = array(
        "s_visits_stat", "s_visits_log",
        "s_robots_stat", "s_robots_log"
    );
    /**
     * @var имя временного файла gz
     */
    protected $tmpf;
    /**
     * @var double переменная mysql max_allowed_packet
     */
    protected $max_packet;
    /**
     * @var handler файла gz
     */
    protected $ex_gz;
    /**
     * @var string кодировка базы (SET NAMES 'names')
     */
    protected $names;

    /**
     * коннект к базе
     *
     * @param        $user
     * @param        $pass
     * @param        $db
     * @param        $host
     * @param string $names
     */
    public function __construct($user, $pass, $db, $host, $names = "cp1251")
    {
        if (is_null(self::$db)) {
            self::$db = new mysqli($host, $user, $pass, $db);
            self::$db->query("set names '{$names}'");
        }

        $this->names = $names;
    }

    /**
     * дамп базы
     */
    public function export()
    {
        $this->init()
        ->start()
        ->exTables()
        ->finish();
    }

    /**
     * @return $this
     */
    protected function start()
    {
        header("Content-type: application/x-gzip");
        header(
            "Content-Disposition: attachment; filename=\"masterovoy-" .
            date('d-m-Y-h-i-s') . ".sql.gz\""
        );

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function init()
    {
        set_time_limit(600);

        $res = self::$db->query(
            "SHOW VARIABLES LIKE 'max_allowed_packet'"
        );

        $sz = $res->fetch_object();

        $this->max_packet = floor($sz->Value * 0.8);

        if (! $this->max_packet) {
            $this->max_packet = 838860;
        }

        $this->tmpf = tempnam(sys_get_temp_dir(), '_masterovoy') . '.gz';
        if (! ($this->ex_gz = gzopen($this->tmpf, 'wb9'))) {
            throw new Exception("Error trying to create gz tmp file");
        }


        return $this;
    }

    /**
     * перебор всех таблиц (кроме исключенных)
     *
     * @return $this
     */
    protected function exTables()
    {
        $this->_writeRow(
            sprintf(
                "/*!40030 SET NAMES %s */",
                $this->names
            )
        );

        $this->_writeRow(
            sprintf(
                "/*!40030 SET GLOBAL max_allowed_packet=%d */",
                $this->max_packet
            )
        );

        $res          = self::$db->query('SHOW TABLES');
        $this->tables = $res->fetch_all();

        foreach ($this->tables as $key => $val) {
            if (in_array($val[0], $this->exclude_tables) === false) {
                $this->exportTable($val[0]);
            }
        }

        return $this;
    }

    /**
     * пишет строку в gzip
     *
     * @param $str
     */
    private function _writeRow($str)
    {
        $str .= ";" . PHP_EOL . PHP_EOL;
        gzwrite($this->ex_gz, $str, strlen($str));
    }

    /**
     * экспорт одной таблицы: структура, данные
     * закатано в транзакцию
     *
     * @param $table
     */
    protected function exportTable($table)
    {
        $this->_writeRow(
            sprintf(
                "START TRANSACTION",
                $table
            )
        );
        $this->_writeRow(
            sprintf(
                "DROP TABLE IF EXISTS `%s`",
                $table
            )
        );

        $res = self::$db->query(
            "SHOW CREATE TABLE `{$table}`"
        );

        $tbl_struct = $res->fetch_assoc();

        // create table
        $this->_writeRow($tbl_struct["Create Table"]);

        // inserts
        $this->_writeRow(
            sprintf(
                "/*!40000 ALTER TABLE `%s` DISABLE KEYS */",
                $table
            )
        );

        $res     = self::$db->query("SELECT * FROM `{$table}`");
        $records = $res->fetch_all();

        $value = "";
        foreach ($records as $v) {
            foreach ($v as $k => $val) {
                $v[$k] = (empty($val))
                    ? '""'
                    : self::$db->real_escape_string($val);

            }

            $value .= '("' . implode('", "', $v) . '"),';

            if (strlen($value) > $this->max_packet) {
                $value = sprintf(
                    "INSERT INTO %s VALUES %s",
                    $table,
                    rtrim($value, ',')
                );
                $this->_writeRow($value);

                $value = "";
            }

        }

        if (! empty($value)) {
            $value = sprintf(
                "INSERT INTO %s VALUES %s",
                $table,
                rtrim($value, ',')
            );
            $this->_writeRow($value);
        }

        $this->_writeRow(
            sprintf(
                "/*!40000 ALTER TABLE `%s` ENABLE KEYS */",
                $table
            )
        );

        $this->_writeRow(
            sprintf(
                "COMMIT",
                $table
            )
        );
    }

    /**
     * отдает gz клиенту
     */
    protected function finish()
    {
        gzclose($this->ex_gz);
        readfile($this->tmpf);
        flush();
    }
}
