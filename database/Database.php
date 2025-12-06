<?php
/**
 * Classe de conexão com banco de dados
 * Compatível com WordPress $wpdb para migração futura
 */

class AquabeatDatabase {
    private $connection;
    public $last_error = '';
    public $last_query = '';
    public $insert_id = 0;
    public $rows_affected = 0;

    public function __construct() {
        $this->connect();
    }

    /**
     * Conecta ao banco de dados
     */
    private function connect() {
        try {
            $this->connection = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            die('Erro de conexão com banco de dados: ' . $this->last_error);
        }
    }

    /**
     * Executa query e retorna resultados (compatível com $wpdb->get_results)
     */
    public function get_results($query, $output = OBJECT) {
        $this->last_query = $query;
        try {
            $stmt = $this->connection->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($output === OBJECT) {
                return array_map(function($row) {
                    return (object) $row;
                }, $results);
            }
            return $results;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    /**
     * Retorna uma única linha (compatível com $wpdb->get_row)
     */
    public function get_row($query, $output = OBJECT) {
        $this->last_query = $query;
        try {
            $stmt = $this->connection->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) return null;

            if ($output === OBJECT) {
                return (object) $result;
            }
            return $result;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    /**
     * Retorna uma única variável (compatível com $wpdb->get_var)
     */
    public function get_var($query) {
        $this->last_query = $query;
        try {
            $stmt = $this->connection->query($query);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    /**
     * Executa query (INSERT, UPDATE, DELETE) - compatível com $wpdb->query
     */
    public function query($query) {
        $this->last_query = $query;
        try {
            $stmt = $this->connection->exec($query);
            $this->rows_affected = $stmt;
            $this->insert_id = $this->connection->lastInsertId();
            return $stmt;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Insert - compatível com $wpdb->insert
     */
    public function insert($table, $data, $format = null) {
        $fields = array_keys($data);
        $values = array_values($data);

        $fields_sql = '`' . implode('`, `', $fields) . '`';
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $query = "INSERT INTO `$table` ($fields_sql) VALUES ($placeholders)";

        $this->last_query = $query;
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($values);
            $this->insert_id = $this->connection->lastInsertId();
            $this->rows_affected = $stmt->rowCount();
            return $this->rows_affected;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Update - compatível com $wpdb->update
     */
    public function update($table, $data, $where, $format = null, $where_format = null) {
        $set_parts = [];
        $values = [];

        foreach ($data as $field => $value) {
            $set_parts[] = "`$field` = ?";
            $values[] = $value;
        }

        $where_parts = [];
        foreach ($where as $field => $value) {
            $where_parts[] = "`$field` = ?";
            $values[] = $value;
        }

        $query = "UPDATE `$table` SET " . implode(', ', $set_parts) .
                 " WHERE " . implode(' AND ', $where_parts);

        $this->last_query = $query;
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($values);
            $this->rows_affected = $stmt->rowCount();
            return $this->rows_affected;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Delete - compatível com $wpdb->delete
     */
    public function delete($table, $where, $where_format = null) {
        $where_parts = [];
        $values = [];

        foreach ($where as $field => $value) {
            $where_parts[] = "`$field` = ?";
            $values[] = $value;
        }

        $query = "UPDATE `$table` SET " . implode(' AND ', $where_parts);

        $this->last_query = $query;
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($values);
            $this->rows_affected = $stmt->rowCount();
            return $this->rows_affected;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Prepare query - compatível com $wpdb->prepare
     */
    public function prepare($query, ...$args) {
        // Substitui %s, %d, %f pelos valores
        $offset = 0;
        foreach ($args as $arg) {
            $pos = strpos($query, '%', $offset);
            if ($pos === false) break;

            $type = substr($query, $pos + 1, 1);
            if ($type === 's') {
                $value = "'" . $this->connection->quote($arg) . "'";
            } elseif ($type === 'd') {
                $value = intval($arg);
            } elseif ($type === 'f') {
                $value = floatval($arg);
            } else {
                continue;
            }

            $query = substr_replace($query, $value, $pos, 2);
            $offset = $pos + strlen($value);
        }

        return $query;
    }

    /**
     * Escapa string para SQL
     */
    public function esc_like($text) {
        return addcslashes($text, '_%\\');
    }

    /**
     * Inicia transação
     */
    public function begin_transaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transação
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Rollback transação
     */
    public function rollback() {
        return $this->connection->rollBack();
    }

    /**
     * Retorna conexão PDO (para casos específicos)
     */
    public function get_connection() {
        return $this->connection;
    }
}
