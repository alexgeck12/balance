<?php
namespace App\Core;

class Database extends \mysqli
{
    protected static $instance;
    private $conf = [];
    /** @var \mysqli_stmt[] **/
    private $prepared = [];
    private $tables = [];

    /**
     * @param string $db
     * @return Database
     */
    public static function getInstance($db = 'main')
    {
        if ( !isset(self::$instance[$db]) || is_null(self::$instance[$db]) ) {
            self::$instance[$db] = new self($db);
        }
        return self::$instance[$db];
    }

    public function __construct($db)
    {
        $this->conf = Config::getInstance()->get('db/'.$db);
        parent::__construct($this->conf['host'], $this->conf['user'], $this->conf['password'], $this->conf['dbname']);

        $this->set_charset('utf8');
    }

    private function analyze_table($table)
    {
        if (!isset($this->tables[$table])) {
            $fields = parent::query("DESCRIBE $table")->fetch_all(MYSQLI_ASSOC);
            foreach ($fields as $field) {
                $variants = false;
                $type = preg_split('/[^a-z]/is', $field['Type'])[0];
                switch ($type) {
                    case 'enum':
                        $variants = explode(',', substr($field['Type'], 5, -1));
                        foreach ($variants as &$variant) {
                            $variant = trim($variant, "'\ ");
                        }
                        $symbol = 's';
                        break;
                    case 'char':
                    case 'text':
                    case 'tinytext':
                    case 'mediumtext':
                    case 'longtext':
                    case 'varchar':
                    case 'datetime':
                    case 'timestamp':
                    case 'json':
                        $symbol = 's';
                        break;
                    case 'bit':
                    case 'int':
                    case 'tinyint':
                    case 'smallint':
                    case 'bigint':
                    case 'mediumint':
                        $symbol = 'i';
                        break;
                    case 'float':
                    case 'double':
                    case 'real':
                        $symbol = 'f';
                        break;
                    case 'decimal':
                        $symbol = 'd';
                        break;
                    case 'blob':
                    case 'tinyblob':
                    case 'mediumblob':
                    case 'longblob':
                    case 'varbinary':
                    case 'binary':
                        $symbol = 'b';
                        break;

                }
                $this->tables[$table][$field['Field']] = [
                    'symbol' => $symbol,
                    'type' =>$type,
                    'null' => $field['Null'] == 'YES',
                    'default' => $field['Default']
                ];

                if ($variants) {
                    $this->tables[$table][$field['Field']]['possible'] = $variants;
                }
            }
        }
    }

    public function __get($var)
    {
        switch ($var) {
            case 'total':
                return (int)$this->query("SELECT FOUND_ROWS()")->fetch_row()[0];
                break;
        }
    }

    private function filter($table, &$values)
    {
        $this->analyze_table($table);
        foreach ($values as $field => &$value) {
            if (!isset($this->tables[$table][$field])) {
                unset($values[$field]);
            }
        }
    }

    private function where($params)
    {
        $where = [];
        $values = [];

        if (is_array($params) && !empty($params)) {
            $prev_condition = null;

            foreach ($params as $condition) {
                if ($prev_condition !== null && $prev_condition !== 'OR'
                    && $condition !== 'AND' && $condition !== 'OR'
                    && $prev_condition !== '(' && $condition !== ')') {
                    $where[] = 'AND';
                }

                if (is_array($condition)) {
                    if (count($condition) < 3) {
                        throw new \UnexpectedValueException(
                            "\core\db error: where condition should contain 3 or 4 elements"
                        );
                    }
                    list($field, $operator, $value) = $condition;
                    if (isset($condition[3])) {
                        $type = $condition[3];
                    }
                    if (is_array($value)) {
                        switch ($operator) {
                            case 'LIKE':
                                $i = 0;
                                foreach ($value as $item) {

                                    if ($i > 0) {
                                        if (!isset($type)) {
                                            $where[] = 'OR';
                                        } else {
                                            $where[] = $type;
                                        }

                                    } else {
                                        $where[] = '(';
                                    }

                                    $values[] = ['field' => $field, 'value' => $item];
                                    $where[] = '`'.$field.'` '.$operator.' ?';
                                    $i++;
                                }
                                $where[] = ')';

                                break;
                            case "IN":
                            case "NOT IN":
                            case "=":
                            case "!=":
                                if (!empty($value)) {
                                    foreach ($value as $item) {
                                        $values[] = ['field' => $field, 'value' => $item];
                                    }
                                    if ($operator == '=') {
                                        $operator = "IN";
                                    }

                                    if ($operator == '!=') {
                                        $operator = "NOT IN";
                                    }

                                    $where[] = '`'.$field."` {$operator} (".implode(',', array_fill(0, count($value), '?')).')';
                                } else {
                                    $where[] = '1=1';
                                }

                                break;
                            default:
                                throw new \UnexpectedValueException("Incorrect operator {$operator} for array in WHERE");
                        }
                    } elseif ($value === null || $value === 'NULL') {
                        $where[] = '`'.$field.'` '.$operator.' NULL';
                    } elseif (stristr($value, 'select')) {
                        $where[] = '`'.$field.'` IN ('.$value.')';
                    } else {
                        $values[] = ['field' => $field, 'value' => $value];
                        $where[] = '`'.$field.'` '.$operator.' ?';
                    }
                } else {
                    $where[] = $condition;
                }

                $prev_condition = $condition;
            }
            if (!empty($where)) {
                $where = 'WHERE '.implode(' ', $where);
            } else {
                $where = '';
            }

        } elseif (!is_array($params)) {
            $where = 'WHERE '.$params;
        } else {
            $where = '';
        }

        return [$where, $values];
    }

    public function getFiltered($table, $params, $calc = false, $sql_only = false)
    {
        $fields = '*';
        $where = $group = $limit = $order = '';
        $calc = $calc?'SQL_CALC_FOUND_ROWS':'';
        $this->analyze_table($table);

        if (isset($params['fields'])) {
            if (is_array($params['fields']) && !empty($params['fields'])) {
                foreach ($params['fields'] as &$field) {
                    if (strpbrk($field, ' ,()`') === false) {
                        $field = '`' . $field . '`';
                    }
                }
                $fields = implode(',', $params['fields']);
            } elseif (is_string($params['fields'])) {
                $fields = $params['fields'];
            }
        }

        $values = [];

        if (isset($params['where']) && !empty($params['where'])) {
            list($where, $values) = $this->where($params['where']);
        }

        if (isset($params['group']) && !empty($params['group'])) {
            if (is_array($params['group'])) {
                foreach ($params['group'] as &$field) {
                    if (strpbrk($field, ' ,()`') === false) {
                        $field = '`' . $field . '`';
                    }
                }
                $params['group'] = implode($params['group'], ',');
            }
            $group = 'GROUP BY '.$params['group'];
        }

        if (isset($params['order']) && !empty($params['order'])) {
            $order = 'ORDER BY ';
            foreach ($params['order'] as $field => $direction) {
                $orders[] = '`'.$field.'` '.$direction;
            }
            $order.= implode(',', $orders);
        }

        if (isset($params['limit']) && !empty($params['limit'])) {
            $limit = "LIMIT ".implode(',', $params['limit']);
        }

        $sql = "SELECT $calc $fields FROM `$table` $where $group $order $limit";
        return $this->_query($sql, $table, $values)->fetch_all(MYSQLI_ASSOC);
    }

    public function getRowByKeys($table, $params, $fields = false)
    {
        $this->analyze_table($table);

        if ($fields) {
            if (is_array($fields) && !empty($fields)) {
                foreach ($fields as &$field) {
                    if (strpbrk($field, ' ,()`') === false) {
                        $field = '`' . $field . '`';
                    }
                }
                $fields = implode($fields, ',');
            }
        } else {
            $fields = '*';
        }

        list($where, $values) = $this->where($params);
        $sql = "SELECT $fields FROM `$table` $where";

        return $this->_query($sql, $table, $values)->fetch_assoc() ?: [];
    }

    public function insert($table, $data, $duplicate_update = false)
    {
        $this->filter($table, $data);

        foreach ($data as $field => $value) {
            $values[] = ['field' => $field, 'value' => $value];
            $fields[] = "`$field` = ?";
        }

        if ($duplicate_update) {
            $sql = "INSERT INTO `$table` SET ".implode(',' ,$fields);

            if ($duplicate_update === true) {
                $update_fields = [];
                foreach ($data as $field => $value) {
                    $update_fields[$field] = "$field = VALUES($field)";
                }

                $sql .= " ON DUPLICATE KEY UPDATE ".implode(',',$update_fields);
            } else {
                $sql .= " ON DUPLICATE KEY UPDATE ".$duplicate_update;
            }
        } else {
            $sql = "INSERT IGNORE INTO `$table` SET ".implode(',' ,$fields);
        }

        return $this->_query($sql, $table, $values);
    }

    public function multi_insert($table, $data, $duplicate_update = false)
    {
        foreach ($data as $i => $row) {
            $this->filter($table, $row);
            ksort($row);
            $fields[$i] = [];

            if (empty($field_order)) {
                foreach ($row as $field => $value) {
                    $field_order[] = "`$field`";
                }
            }


            foreach ($row as $field => $value) {
                $values[] = ['field' => $field, 'value' => $value];
                $fields[$i][] = "?";
            }
        }

        $rows = [];
        foreach ($fields as $row) {
            $rows[] = '('.implode(',' ,$row).')';
        }

        if ($duplicate_update) {
            $sql = "INSERT INTO `$table`(".implode(',', $field_order).") VALUES ".implode(',' ,$rows);

            if ($duplicate_update === true) {
                $update_fields = [];
                foreach ($field_order as $field) {
                    $update_fields[$field] = "$field = VALUES($field)";
                }

                $sql .= " ON DUPLICATE KEY UPDATE ".implode(',',$update_fields);
            } else {
                $sql .= " ON DUPLICATE KEY UPDATE ".$duplicate_update;
            }
        } else {
            $sql = "INSERT IGNORE INTO `$table`(".implode(',', $field_order).") VALUES ".implode(',' ,$rows);
        }

        return $this->_query($sql, $table, $values);
    }

    public function update($table, $data, $params)
    {
        $this->filter($table, $data);

        foreach ($data as $field => $value) {
            $values[] = ['field' => $field, 'value' => $value];
            $fields[] = "`$field` = ?";
        }
        list($where, $where_params) = $this->where($params);
        $values = array_merge($values, $where_params);
        $sql = "UPDATE `$table` SET ".implode(',' ,$fields)." $where";
        return $this->_query($sql, $table, $values);
    }

    public function delete($table, $params)
    {
        list($where, $values) = $this->where($params);
        $sql = "DELETE FROM `$table` $where";
        return $this->_query($sql, $table, $values);

    }

    private function _query($sql, $table, $params)
    {
        $this->analyze_table($table);
        $sql = trim($sql);

        if (!isset($this->prepared[$sql])) {
            if (!($this->prepared[$sql] = $this->prepare($sql))) {
                if ($this->errno == 2006) {
                    $this->prepared = [];
                    $this->connect($this->conf['host'], $this->conf['user'], $this->conf['password'], $this->conf['dbname']);
                    $this->set_charset('utf8');
                    return $this->_query($sql, $table, $params);
                } else {
                    error_log("DB ERROR:".$this->error."\nSQL: ".$sql."\n");
                    throw new \UnexpectedValueException($this->error, $this->errno);
                }
            }
        }

        $original_params = $params;
        $stmt = $this->prepared[$sql];

        $types = '';
        $values = [];
        $long_data_params = [];
        foreach ($params as $i => &$param) {
            $symbol = $this->tables[$table][$param['field']]['symbol'];
            $type = $this->tables[$table][$param['field']]['type'];

            $types .= $symbol;
            if ($symbol == 'b') {
                $NULL = null;
                $values[] = &$NULL;
                if (!is_null($param['value'])) {
                    $long_data_params[$i] = $param['value'];
                }
            } elseif($type == 'json') {
                $param['value'] = json_encode($param['value']);
                $values[] = &$param['value'];
            } elseif($symbol == 'f') {
                $param['value'] = (float) $param['value'];
                $values[] = &$param['value'];
            } else {
                $values[] = &$param['value'];
            }
        }

        if (!empty($values)) {
            array_unshift($values, $types);
            call_user_func_array(array($stmt, 'bind_param'), $values);

            if (!empty($long_data_params)) {
                foreach ($long_data_params as $pos => $param) {
                    if (substr($param, 0,2) == '0x') {
                        $param = hex2bin(substr($param, 2));
                    }

                    $stmt->send_long_data($pos, $param);
                }
            }
        }

        if (!$stmt->execute()) {
            if ($this->errno == 2006 || $stmt->errno == 2006) {
                $this->prepared = [];
                $this->connect($this->conf['host'], $this->conf['user'], $this->conf['password'], $this->conf['dbname']);
                $this->set_charset('utf8');
                return $this->_query($sql, $table, $original_params);
            } else {
                if ($stmt->error) {
                    error_log("DB ERROR:".$stmt->error."\nSQL: ".$sql."\n");
                    throw new \UnexpectedValueException($stmt->error, $stmt->errno);
                }
            }
        };

        if ($this->error) {
            error_log("DB ERROR:".$this->error."\nSQL: ".$sql."\n");
            throw new \UnexpectedValueException($this->error, $this->errno);
        }

        if (stripos($sql, 'select') !== false) {
            return $stmt->get_result();
        } elseif (stripos($sql, 'insert') !== false) {
            if (isset($stmt->insert_id) && $stmt->insert_id) {
                return $stmt->insert_id;
            }
        }

        return $stmt->affected_rows;
    }
}