<?php
/**
 * DbDumper
 * делает дамп базы mysql в формате. Дамп можно
 * импортировать phpmyadmin'ом
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
 * DbDumper
 *
 * Отдает дамп сжатый в gz
 *
 * PHP version 5
 *
 * @category Website
 * @package  Supplemental
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
     * @var array экспортируемые таблицы
     */
    protected $tables = array();
    /**
     * @var array таблички, дамп которых не делаем
     */
    protected $exclude_tables = array();
    /**
     * @var имя временного файла gz
     */
    protected $tmpf;
    /**
     * @var double переменная mysql max_allowed_packet.
     * Это нужно для разбивки INSERT'ов
     */
    protected $max_packet;
    /**
     * @var mixed handle файла gz
     */
    protected $ex_gz;
    /**
     * @var string кодировка базы (SET NAMES 'xxx')
     */
    protected $names;

    /**
     * коннект к базе в конструкторе.
     * В параметрах данные авторизации
     *
     * @param string $user  имя пользователя
     * @param string $pass  пароль
     * @param string $db    имя базы
     * @param string $host  хост
     * @param string $names set names
     */
    public function __construct($user, $pass, $db, $host, $names = "utf8")
    {
        $this->names          = $names;
        $this->exclude_tables = array(
            "s_visits_stat",
            "s_visits_log",
            "s_robots_stat",
            "s_robots_log"
        );

        if (is_null(self::$db)) {
            self::$db = new mysqli($host, $user, $pass, $db);
            self::$db->query("set names '{$this->names}'");
        }
    }

    /**
     * дамп базы
     *
     * @return void
     */
    public function export()
    {
        $this->init()
            ->start()
            ->exTables()
            ->finish();
    }

    /**
     * старт экспорта. Отдает HTTP заголовки
     *
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
     * инициализация процесса экспорта
     *
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
     * проход по всем таблицам базы и экспорт
     * - таблицы которые не нужно экспортировать
     * в массиве $this->exclude_tables
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

        // выбор всех таблиц в базе
        $res          = self::$db->query('SHOW TABLES');
        $this->tables = $res->fetch_all();

        // проход по найденным таблицам
        foreach ($this->tables as $val) {
            if (in_array($val[0], $this->exclude_tables) === false) {
                $this->exportTable($val[0]);
            }
        }

        return $this;
    }

    /**
     * пишет строку в эксп. файл (gz)
     *
     * @param string $str строка SQL
     *
     * @return void
     */
    private function _writeRow($str)
    {
        $str .= ";" . PHP_EOL . PHP_EOL;
        gzwrite($this->ex_gz, $str, strlen($str));
    }

    /**
     * экспорт таблицы: структура, данные.
     * INSERT - в транзакции (на случай InnoDB)
     *
     * @param string $table имя таблицы для экспорта
     *
     * @return void
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

        // структура
        $this->_writeRow($tbl_struct["Create Table"]);

        // данные
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
     * Окончание процесса. Выдача gz
     *
     * @return void
     */
    protected function finish()
    {
        gzclose($this->ex_gz);
        readfile($this->tmpf);
        flush();
    }
}
