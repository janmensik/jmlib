<?php

namespace Janmensik\Jmlib;

class Modul {
    /** @var array */
    public $cache; # cache soubor

    /**
     * @var Database The database connection object.
     */
    public $DB;
    public $cache_total; # cache s pocet celkovych radek odpovidajicich poslednimu get
    public $cache_sql; # cache sql query posledniho get

    protected $sql_base; # zaklad SQL dotazu
    protected $sql_update; # zaklad SQL dotazu - UPDATE
    protected $sql_insert; # zaklad SQL dotazu - INSERT
    protected $sql_table;
    protected $id_format = 'id';
    protected $sql_group_total;
    protected $order = -6;
    protected $limit = 20;
    protected $sync = array();
    protected $many_to_many = array(); # M:N relationships definitions
    protected $fulltext_columns; # zakladni sloupce pro hledani

    public $text = array();

    # ...................................................................
    /**
     * Modul constructor.
     * @param Database $database
     */
    public function __construct(Database &$database) {
        $this->DB = &$database; # globalni objekt pro praci s databazi
        $this->cache = array();

        if (!is_object($this->DB)) {
            return (false);
        }
        return (true);
    }

    # ...................................................................
    public function getLimit() {
        return ($this->limit);
    }

    # ...................................................................
    public function setLimit($limit = null) {
        if (is_numeric($limit) && $limit > 0) {
            $this->limit = (int) $limit;
        }
        return ($this->limit);
    }

    # ...................................................................
    public function getNoCalcRows($where = null, $order = null, $limit = null, $limit_from = null) {
        return ($this->get($where, $order, $limit, $limit_from, true));
    }
    # ...................................................................
    public function get($where = null, $order = null, $limit = null, $limit_from = null, $nocalcrows = false) {
        if (!$this->sql_base) {
            return (false);
        }

        $data = null;

        # zaklad sql dotazu
        $sql = $this->sql_base;

        # pokud je v sql_base obsazen GROUP BY, musim to rozdel (mozna i neco dalsiho)
        if (function_exists("strripos")) {
            $pos_group_by = strripos($sql, 'GROUP BY ');
            $last_from = strripos($sql, 'FROM ');
            if ($pos_group_by > $last_from) {
                $sql_group_by = substr($sql, $pos_group_by);
                $sql = substr($sql, 0, $pos_group_by);
            }
        } elseif (strpos(strtolower($sql), 'group by')) {
            $sql_group_by = stristr($sql, 'group by');
            $sql = substr($sql, 0, strpos(strtolower($sql), 'group by'));
        }

        # pokud je v sql_base obsazen WHERE, musim to rozdelit
        $sql_where = '';
        if (function_exists("strripos")) {
            $pos_where = strripos($sql, 'WHERE ');
            if ($pos_where > $last_from) {
                $sql_where = substr($sql, $pos_where + 6);
                $sql = substr($sql, 0, $pos_where);
            }
        } elseif (strpos(strtolower($sql), 'where')) {
            $sql_where = substr(stristr($sql, 'where'), 6);
            $sql = substr($sql, 0, strpos(strtolower($sql), 'where'));
        }

        # WHERE - pridani podminky
        if (!is_array($where) && isset($where)) {
            $where = array($where);
        }
        if ($sql_where) {
            $where[] = $sql_where;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        # pokud je v sql_base GROUP BY, musis odriznutou cast pridat (zde)
        if (isset($sql_group_by) && $sql_group_by) {
            $sql .= ' ' . $sql_group_by;
        }

        # ORDER BY - pridani trideni
        if (!$order) {
            $order = $this->order;
        }
        foreach (explode(',', $order) as $part_order) {
            if (is_numeric($part_order)) {
                if ($part_order < 0) {
                    $orders[] = (-1 * $part_order) . ' DESC';
                } else {
                    $orders[] = $part_order;
                }
            }
        }
        if (isset($orders) && is_array($orders)) {
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        # LIMIT
        if ($limit != -1) {
            if (!is_numeric($limit) || (int) $limit < 1) {
                $limit = $this->limit;
            }
            if ($limit != -1) {
                $sql .= ' LIMIT ';
                if (is_numeric($limit_from) && (int) $limit_from > 1) {
                    $sql .= ($limit_from - 1) * $limit . ', ';
                }

                $sql .= $limit;
            }
        }

        $sql .= ';';

        # nechci pocet vsech radek - zdrzuje
        if ($nocalcrows) {
            $sql = str_replace(' SQL_CALC_FOUND_ROWS', '', $sql);
        }

        $this->cache_sql = $sql;

        # SQL dotaz
        $this->DB->query($sql, get_class($this) . ' -> get');
        while ($radka = $this->DB->getRow()) {
            # prepis hodnot statusu na CZ
            if (is_array($this->text)) {
                foreach ($this->text as $t_lang => $t_data) {
                    foreach ($t_data as $t_key => $t_value) {
                        if (isset($radka[$t_key]) && !empty($t_value[$radka[$t_key]]) && isset($t_value[$radka[$t_key]])) {
                            $radka[$t_key . '_' . $t_lang] = $t_value[$radka[$t_key]];
                        } elseif (isset($radka[$t_key])) {
                            $radka[$t_key . '_' . $t_lang] = $radka[$t_key];
                        }
                        //$radka[$t_key . '_' . $t_lang] = !empty($radka[$t_key]) && $t_value[$radka[$t_key]] ? $t_value[$radka[$t_key]] : $radka[$t_key];
                    }
                }
            }

            $data[] = $radka;
            if (isset($radka['id']) && $radka['id']) {
                $this->cache[$radka['id']] = $radka;
            }
        }

        # nactu si kolik by bylo celkove radek (pro pagination)
        if (strpos($this->sql_base, 'SQL_CALC_FOUND_ROWS') && !$nocalcrows) {
            $this->cache_total = $this->DB->getRowsCount();
        }

        return ($data);
    }

    # ...................................................................
    public function getCustom($custom_sql = null, $where = null, $order = null, $limit = null, $limit_from = null) {

        $temp = $this->sql_base;

        if ($custom_sql) {
            $this->sql_base = $custom_sql;
        }

        $data = $this->get($where, $order, $limit, $limit_from);

        $this->sql_base = $temp;

        return ($data);
    }


    # ...................................................................
    # vrati definovane agregovane vysledky, tedy soucet, prumer, pocet atd vsech vysledku bez ohledu na limit
    public function getGroupTotal($where = null) {
        if (!$this->sql_group_total) {
            return (false);
        }

        if (!is_array($where) && isset($where)) {
            $where = array($where);
        }

        $sql =  substr($this->sql_base, strpos(strtoupper($this->sql_base), ' FROM'));

        # pokud je v sql_base obsazen GROUP BY, musim to rozdel (mozna i neco dalsiho)
        if (strpos(strtoupper($sql), 'GROUP BY')) {
            $sql = substr($sql, 0, strpos(strtoupper($sql), 'GROUP BY'));
        }

        # pokud je v sql_base obsazen WHERE, musim to rozdelit
        if (strpos(strtolower($sql), 'where')) {
            $where[] = substr(stristr($sql, 'where'), 6);
            $sql = substr($sql, 0, strpos(strtolower($sql), 'where'));
        }

        # pokud je v sql_group_total obsazen GROUP BY, musim to rozdelit
        if (strpos(strtoupper($this->sql_group_total), 'GROUP BY')) {
            $sql_group_by = ' ' . stristr($this->sql_group_total, 'GROUP BY');
            $sql_gt = substr($this->sql_group_total, 0, strpos(strtoupper($this->sql_group_total), 'GROUP BY'));
        } else {
            $sql_group_by = ' GROUP BY ' . $this->sql_table . '.id';
            $sql_gt = $this->sql_group_total;
        }

        # WHERE - pridani podminky
        $sql_where = '';
        if (!is_array($where) && isset($where)) {
            $where = array($where);
        }
        if ($sql_where) {
            $where[] = $sql_where;
        }
        if ($where) {
            $sql_where .= ' WHERE ' . implode(' AND ', $where) . ' ';
        }

        # finalni dotaz
        $sql = $sql_gt . $sql . $sql_where . $sql_group_by;


        # SQL dotaz
        $this->DB->query($sql, get_class($this) . ' -> getGroupTotal');
        return ($this->DB->getRow());
    }

    # ...................................................................
    # stejne jako get (), ale vrati 1-n (parametr $count) nahodnych zaznamu
    public function getRandom($where = null, $order = null, $limit = null, $limit_from = null, $count = 1) {
        $data = $this->get($where, $order, $limit, $limit_from);

        if (!is_array($data)) {
            return (false);
        }

        # chci cislo
        $count = 1 * (int) $count;
        # pokud mam dostatecny pocet zaznamu
        if ($this->cache_total >= 1 && $this->cache_total > $count) {
            # vyberu klice nahodnych polozek
            srand();
            $keys = array_rand($data, $count);

            if ($count > 1) {
                foreach ($keys as $value) {
                    $output[] = $data[$value];
                }
                return ($output);
            } else {
                return (array($data[$keys]));
            }
        } else {
            # zadny vysledek nebo chci stejny pocet jako mam = vyber vseho
            return ($data);
        }
    }

    # ...................................................................
    # vrati informace o aktualnim trideni
    public function getExtra($order = null) {
        $first_order = '';
        $rest_order = '';

        if (!is_numeric($order)) {
            $order = $this->order;
        }

        # budu pracovat jen s prvni hodnotou trideni z retezce "3, -5, 2, 11"
        if (isset($order) && strpos($order, ',')) {
            $first_order = substr($order, 0, strpos($order, ','));
            $rest_order = substr($order, strpos($order, ','));
        } else {
            $first_order = $order;
        }

        # ORDER BY - pridani trideni
        if ($order < 0) {
            $output['order'] = -1 * $first_order;
            $output['order_type'] = 'down';
            $output['order_minus'] = 1;
            $output['order_other'] = $first_order;
            $output['order_full'] = (-1 * $first_order) . $rest_order;
            $output['order_full_other'] = $first_order . $rest_order;
        } else {
            $output['order'] = $first_order;
            $output['order_type'] = 'up';
            $output['order_plus'] = 1;
            $output['order_other'] = -1 * $first_order;
            $output['order_full'] = $first_order . $rest_order;
            $output['order_full_other'] = (-1 * $first_order) . $rest_order;
        }


        return ($output);
    }

    # ...................................................................
    public function getTotal($dataset = null, $values = null) {
        if (!is_array($values)) {
            return (false);
        }
        if (!is_array($dataset)) {
            return (false);
        }

        $output = array();
        $counter = array();
        foreach ($dataset as $row) {
            foreach ($values as $key => $function) {
                if ($function == 'count' && isset($row[$key])) {
                    @$output[$key]++;
                }
                if ($function == 'sum' && isset($row[$key])) {
                    @$output[$key] += $row[$key];
                }
                if ($function == 'avg' && isset($row[$key])) {
                    @$output[$key] += ((int) $row['id'] && $values['id'] == 'sum') ? $row['id'] * $row[$key] : $row[$key];
                    $counter[$key]++;
                }
            }
        }

        foreach ($values as $key => $function) {
            if ($function == 'avg' && $output[$key]) {
                $output[$key] = $output[$key] / (((int)$output['id'] && $values['id'] == 'sum') ? $output['id'] : $counter[$key]);
            }
        }

        return ($output);
    }

    # ...................................................................
    # not for AVG
    public function getTotalEval($dataset = null, $values = null) {
        if (!is_array($values)) {
            return (false);
        }
        if (!is_array($dataset)) {
            return (false);
        }

        $output = array();
        foreach ($dataset as $row) {
            foreach ($values as $key => $function) {
                if (strpos($key, ']')) {
                    eval('$in = $row' . $key . ';');
                } else {
                    $in = $row[$key];
                }

                if ($function == 'count' && isset($in)) {
                    $add = 1;
                } elseif ($function == 'sum' && isset($in)) {
                    $add = $in;
                } else {
                    continue;
                }

                if (strpos($key, ']')) {
                    eval('$output' . $key . '+=' . $add . ';');
                } else {
                    $output[$key] += $add;
                }
            }
        }

        return ($output);
    }

    # ...................................................................
    public function getRowsCount($result = null) {
        return ($this->cache_total);
    }

    # ...................................................................
    public function set(array|false|null $set = null, array|int|null $ids = null, string|null $special = null): int|false {
        if (!is_array($set)) {
            return false;
        }

        // --- M:N relationships handling ---
        $mn_data = [];
        if (!empty($this->many_to_many)) {
            foreach ($this->many_to_many as $key => $config) {
                if (isset($set[$key])) {
                    $mn_data[$key] = $set[$key];
                    unset($set[$key]);
                }
            }
        }
        // ------------------------------------

        $insert = false;

        # priprava pro UDATE
        if ($ids && !$special) {
            foreach ($set as $key => $value) {
                $sql_temp[] = $this->sql_table . '.' . $key . ' = ' . $value;
            }
        }

        if ($special == 'IODU') {
            # special - INSERT ON DUPLICATE UPDATE
            $sql = $this->sql_insert . ' (' . implode(', ', array_keys($set)) . ') VALUES (' . implode(', ', $set) . ') ON DUPLICATE KEY UPDATE ';
            $keys = array_keys($set);
            unset($sql_temp);
            $sql_temp = array();
            foreach ($keys as $key) {
                $sql_temp[] .= $key . '=VALUES(' . $key . ')';
            }
            $sql .= implode(', ', $sql_temp);
        } elseif (is_array($ids) && count($ids)) {
            # MULTI UPDATE
            $sql = $this->sql_update . ' SET ' . implode(', ', $sql_temp) . ' WHERE ' . $this->sql_table . '.' . $this->id_format . ' IN ("' . implode('", "', $ids) . '");';
        } elseif (is_numeric($ids)) {
            # SINGLE UPDATE
            $sql = $this->sql_update . ' SET ' . implode(', ', $sql_temp) . ' WHERE ' . $this->sql_table . '.' . $this->id_format . ' = "' . $ids . '";';
        } else {
            # INSERT
            $sql = $this->sql_insert . ' (' . implode(', ', array_keys($set)) . ') VALUES (' . implode(', ', $set) . ');';
            $insert = true;
        }

        //$this->DB->query ('START TRANSACTION;');
        $this->DB->query($sql);

        //echo ($sql . '<br>\n***************************************************\n<hr>\n***************************************************\n<br>');

        $affected_rows = $this->DB->getNumAffected();

        // A failed query will result in -1.
        if ($affected_rows < 0) {
            return false;
        }

        # kdyz byl insert, jeste nactu nove id
        if ($insert || $special) {
            $next_id = $this->DB->getId();
            if (is_numeric($next_id) && !$ids) {
                $ids = $next_id;
            }
        }

        // An insert should affect at least one row to be considered a success for returning an ID,
        // unless there is M:N data to process, in which case we can proceed.
        if ($insert && $affected_rows === 0 && empty($mn_data)) {
            return false;
        }

        # Old sync logic - should only run if rows were actually changed.
        if ($affected_rows > 0) {
            foreach (array_keys($set) as $key => $value) {
                if (in_array($key, $this->sync)) {
                    if ($insert) {
                        $this->syncInsert($set, $ids);
                    } else {
                        $this->syncUpdate($set, $ids);
                    }
                    break;
                }
            }
        }

        // --- M:N relationships handling ---
        if ($ids && !empty($mn_data)) {
            foreach ($mn_data as $key => $data) {
                $this->syncManyToMany($ids, $data, $this->many_to_many[$key]);
            }
        }
        // ------------------------------------

        # smazu si cache
        unset($this->cache);
        unset($this->cache_total);

        return ($ids);
    }

    /**
     * Synchronizes a many-to-many relationship table.
     * Inserts new relations, deletes old ones, keeps existing ones.
     *
     * @param int|array $main_ids The ID(s) of the main record.
     * @param array $relations The array of relation data to be saved.
     * @param array $config The configuration for the M:N relationship.
     * @return bool
     */
    private function syncManyToMany($main_ids, array $relations, array $config): bool {
        if (empty($main_ids) || empty($config['table']) || empty($config['main_key']) || empty($config['columns'])) {
            return false;
        }

        $main_ids = is_array($main_ids) ? $main_ids : [$main_ids];
        $main_id = reset($main_ids); // Currently handles single main ID sync

        // 1. Get existing relations from DB
        // $existing_relations_raw = $this->DB->getAllRows(
        //  $this->DB->query('SELECT * FROM ' . $config['table'] . ' WHERE ' . $config['main_key'] . ' = ' . (int)$main_id . ';')
        // );

        // $existing_relations = [];
        // if (is_array($existing_relations_raw)) {
        //  foreach ($existing_relations_raw as $row) {
        //      $existing_relations[] = $row;
        //  }
        // }

        // 2. Find relations to add and to delete
        $to_add = $relations; // Start with all new relations
        // $to_delete_ids = array_column($existing_relations, 'id');

        // This simple implementation deletes all and re-inserts.
        // A more complex implementation would compare $relations with $existing_relations
        // to find the exact records to add and delete, which is more efficient.
        // For now, we stick to the original logic but encapsulated here.

        // 3. Delete old relations
        // if (!empty($to_delete_ids)) {
        //  $this->DB->query('DELETE FROM ' . $config['table'] . ' WHERE id IN (' . implode(',', $to_delete_ids) . ');');
        // }
        $this->DB->query('DELETE FROM ' . $config['table'] . ' WHERE ' . $config['main_key'] . ' = ' . (int)$main_id . ';');

        // 4. Insert new relations
        if (!empty($to_add)) {
            $sql_rows = [];
            $insert_columns = array_merge([$config['main_key']], $config['columns']);

            foreach ($to_add as $relation_data) {
                $values = [$main_id]; // Start with the main foreign key
                // Loop through the configured columns to ensure correct order
                foreach ($config['columns'] as $column_name) {
                    // Add the value if it exists, otherwise add NULL
                    $values[] = $relation_data[$column_name] ?? 'NULL';
                }
                $sql_rows[] = '(' . implode(', ', $values) . ')';
            }
            // echo ('INSERT INTO ' . $config['table'] . ' (' . implode(', ', $insert_columns) . ') VALUES ' . implode(', ', $sql_rows) . ';');
            $this->DB->query('INSERT INTO ' . $config['table'] . ' (' . implode(', ', $insert_columns) . ') VALUES ' . implode(', ', $sql_rows) . ';');
        }

        return true;
    }

    # ...................................................................
    public function syncInsert($set, $id) {
        return (true);
    }

    # ...................................................................
    public function syncUpdate($set, $old_data) {
        return (true);
    }

    # ...................................................................
    # $returnarray true znaci ze vysledek bude pole [] = data, pri false se vrati jen 1. vysledek data
    public function getId($ids = null, $returnarray = false) {
        $output = null;

        # prohledam cache, budu nacitat jen nove potrebne
        if (is_array($ids)) {
            foreach ($ids as $key => $id) {
                if ($this->cache[$id]) {
                    $output[] = $this->cache[$id];
                    unset($ids[$key]);
                }
            }
        } elseif ($ids) {
            if (isset($this->cache[$ids])) {
                $output[] = $this->cache[$ids];
            } else {
                $ids = array($ids);
            }
        }
        # nenasel jsem (vse), doctu potrebne
        if (is_array($ids) && count($ids)) {
            $data = $this->get($this->sql_table . '.' . $this->id_format . ' IN("' . implode('", "', $ids) . '")');
            $cache[@$data[$this->id_format]] = $data;
            if (is_array($data) && isset($output) && is_array($output)) {
                $output = array_merge($data, $output);
            } elseif (is_array($data)) {
                $output = $data;
            }
        }

        # pripadne vratim i castecny vysledek
        if (is_array($output)) {
            if ($returnarray) {
                return ($output);
            } else {
                return ($output[0]);
            }
        }

        return (false);
    }

    # ...................................................................
    # $returnarray true znaci ze vysledek bude pole [] = data, pri false se vrati jen 1. vysledek data
    public function getIds($ids = null, $returnarray = true) {
        return ($this->getId($ids, $returnarray));
    }

    # ...................................................................
    public function findId($where, $return_only_first = true) {
        if (!$where) {
            return (null);
        }
        $data = $this->get($where);
        if ($data && $return_only_first) {
            $data = reset($data);
            return ($data['id']);
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                $output[] = $value['id'];
            }
            return ($output);
        } else {
            return (null);
        }
    }

    # ...................................................................
    public function findIds($where, $return_only_first = true) {
        return ($this->findId($where, false));
    }

    # ...................................................................
    /**
     * Return one random row from dataset according to where condition
     * @param array|string|null $where SQL condition(s) for filtering the dataset.
     * @param int|null $sample_size How many rows to take into account for randomness (default is 100).
     * @return int|null ID of the random row or null if not found.
     *
     */
    public function findRandomId(string|array|null $where = null, int|null $sample_size = 100): int|null {
        $data = $this->getRandom($where, null, intval($sample_size), null, 1);
        if (is_array($data)) {
            $data = reset($data);
            return ($data['id']);
        } else {
            return (null);
        }
    }

    # ...................................................................
    public function createFulltextSubquery($input = '', $columns = null, $separator_or = false) {
        # kdyz nemam vstup nebo hledane sloupce, konec
        if (!$input || (!$columns && !is_array($this->fulltext_columns))) {
            return (null);
        }

        # prevedu si seznam sloupcu na pole, pripadne nactu default
        if ($columns && !is_array($columns)) {
            $columns[] = $columns;
        } elseif (!is_array($columns)) {
            $columns = $this->fulltext_columns;
        }

        # vytvorim si subquery aplikovane na kazde hledane slovo
        foreach ($columns as $value) {
            $sub[] = 'CAST(' . $value . ' AS CHAR)';
        }
        $word_query = 'CONCAT_WS(" ",' . implode(',', $sub) . ')';
        //$word_query = 'z.nazev';

        # vytvorim si pole hledanych slov
        $input = mb_strtolower(preg_replace('/[^a-ž0-9 ]+/i', ' ', $input));
        $input = preg_replace('/ +/', ' ', $input);
        $words = explode(' ', $input);

        # vytvoreni dotazu
        if (!is_array($words)) {
            return (null);
        }
        foreach ($words as $word) {
            $query[] = $word_query . ' LIKE "%' . $word . '%"';
        }
        //$query[] = $word_query . ' COLLATE utf8_general_ci LIKE "%' . $word . '%"';
        if ($separator_or) {
            $output = '(' . implode(' OR ', $query) . ')';
        } else {
            $output = '(' . implode(' AND ', $query) . ')';
        }

        return ($output);
    }

    # ...................................................................
    public function sanitize($value = null, $type = 'text', $required = false, $extra_data = null) {
        if ($required && !isset($value)) {
            return false;
        }

        switch ($type) {
            case 'float':
                return (JmLib::parseFloat($value));
                //return (filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT));
            case 'int':
                return (filter_var($value, FILTER_SANITIZE_NUMBER_INT));
            case 'email':
                return (filter_var($value, FILTER_VALIDATE_EMAIL));
            case 'inarray':
            case 'in_array':
                return ((is_array($extra_data) && in_array($value, $extra_data)) ? $value : false);
            case 'text':
            default:
                if ($required && (!$value || !mysqli_real_escape_string($this->DB->db, (string)$value))) {
                    return false;
                } else {
                    return ($value ? mysqli_real_escape_string($this->DB->db, (string)$value) : "");
                }
        }
    }
}
