<?php
namespace cm;

use Medoo\Medoo;

if (!function_exists('fixfn')) {
    function fixfn ($fnlist){
        foreach($fnlist as $fname){
            if (!function_exists($fname)) {
                eval("function $fname(){}");
            }
        }
    }
}

if (!class_exists('cfg')) {
    class cfg {
        public static function get_db_cfg(){
            return array(
                'database_type' => 'mysql',
                'database_name' => 'myapp_dev',
                'server' => 'mysql',
                'username' => 'root',
                'password' => '123456',
                'charset' => 'utf8',
            );
        }
    }
}
$fnlist = array(
    'debug',
);
fixfn($fnlist);

class db {
    private static $_db_list;
    private static $_db_default;

    private static $_db;
    private static $_dbc;
    private static $_ins;
    private static $tbl_desc = [];


    public static function init($cfg, $withUse=true) {
        self::init_db($cfg, $withUse);
    }


    public static function new_db($cfg){
      	return new Medoo($cfg);
    }
    public static function get_db_cfg($cfg='use_db') {
        if(is_string($cfg)){
            $cfg = \cfg::get_db_cfg($cfg);
        }
        $cfg['database_name'] = env('DB_NAME', $cfg['database_name']);
        $cfg['server'] = env('DB_HOST', $cfg['server']);
        $cfg['username'] = env('DB_USER', $cfg['username']);
        $cfg['password'] = env('DB_PASS', $cfg['password']);
        return $cfg;
    }

    /**
     * @param $cfg
     * @param bool $withUse
     *
     *  \db::init_db('use_db1');
     *
     *  or
     *
     *  $db_cfg = \cfg::get_db_cfg('use_db1');
     *  \db::init_db($db_cfg);
     *
     */
    public static function init_db($cfg, $withUse=true) {
        self::$_dbc = self::get_db_cfg($cfg);
        $dbname = self::$_dbc['database_name'];
        self::$_db_list[$dbname] = self::new_db(self::$_dbc);
        if($withUse){
            self::use_db($dbname);
        }
    }

    public static function use_db($dbname) {
        self::$_db = self::$_db_list[$dbname];
    }

    public static function use_default_db() {
        self::$_db = self::$_db_default;
    }

    public static function dbc() {
        return self::$_dbc;
    }
    public static function obj() {
        if(!self::$_db){
            self::$_dbc = self::get_db_cfg();
            self::$_db = self::$_db_default = self::new_db(self::$_dbc);
        }
        return self::$_db;
    }

    public static function db_type(){
        if(!self::$_dbc){
            self::obj();
        }
        return self::$_dbc['database_type'];
    }

    public static function desc_sql($tbl_name){
        if(self::db_type()=='mysql'){
            return "desc $tbl_name";
        }else if(self::db_type()=='pgsql'){
            return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '$tbl_name'";
        } else return '';
    }

    public static function table_cols($name){
        $tbl_desc = self::$tbl_desc;
        if(!isset($tbl_desc[$name])){
            $sql = self::desc_sql($name);
            if($sql){
                $tbl_desc[$name] = self::query($sql);
                self::$tbl_desc = $tbl_desc;
                debug("---------------- cache not found : $name");
            }else{
                debug("empty desc_sql for: $name");
            }
        }
        if(!isset($tbl_desc[$name])){
            return array();
        }else{
            return self::$tbl_desc[$name];
        }
    }

    public static function col_array($name){
        $fn = function($v) use($name){
            return $name.'.'.$v;
        };
        return getKeyValues(self::table_cols($name), 'Field', $fn);
    }

    public static function valid_table_col($name, $col){
        $table_cols = self::table_cols($name);
        foreach($table_cols as $tbl_col){
            if($tbl_col['Field']==$col){
                $type = $tbl_col['Type'];
                return is_string_column($tbl_col['Type']);
            }
        }
        return false;
    }

    public static function tbl_data($name, $data){
        $table_cols = self::table_cols($name);
        $ret = [];
        foreach($table_cols as $tbl_col){
            $col_name = $tbl_col['Field'];
            if(isset($data[$col_name])){
                $ret[$col_name] = $data[$col_name];
            }
        }
        return $ret;
    }

    public static function test(){
        $sql = "select * from tags limit 10";
        $rows = self::obj()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        var_dump($rows);
    }

    public static function has_st($name, $cond){
        $st_col_name = '_st';
        return isset($cond[$st_col_name]) || isset($cond[$name.'.'.$st_col_name]);
    }

    public static function getWhere($name, $where){
        // $name = preg_replace('/\(.*\)/', '', "`$name`");
        // debug(" getWhere : $name");

        $st_col_name = '_st';
        if(!self::valid_table_col($name, $st_col_name)){
            return $where;
        }
        $st_col_name = $name.'._st';

		if (is_array($where))
		{
			$where_keys = array_keys($where);
			$where_AND = preg_grep("/^AND\s*#?$/i", $where_keys);
			$where_OR = preg_grep("/^OR\s*#?$/i", $where_keys);

			$single_condition = array_diff_key($where, array_flip(
				explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')
			));

			if ($single_condition != array()) {
                $cond = $single_condition;
                if(!self::has_st($name, $cond)){
                    $where[$st_col_name]=1;
                    $where = ['AND' => $where];
                }
			}

			if (!empty($where_AND)) {
				$value = array_values($where_AND);
                $cond = $where[$value[0]];
                if(!self::has_st($name, $cond)){
                    $where[$value[0]][$st_col_name]=1;
                }
			}

			if (!empty($where_OR)) {
				$value = array_values($where_OR);
                $cond = $where[$value[0]];
                if(!self::has_st($name, $cond)){
                    $where[$value[0]][$st_col_name]=1;
                }
			}

            if(!isset($where['AND']) && !self::has_st($name, $cond)){
                $where['AND'][$st_col_name]=1;
            }
		}
        return $where;
    }

    public static function all_sql($name, $where='', $cols='*', $join=null){
        $map = [];
        if($join){
            $sql= self::obj()->selectContext($name, $map, $join, $cols, $where);
        }else{
            $sql= self::obj()->selectContext($name, $map, $cols, $where);
        }
        return $sql;
    }

    // $user_fields = array_merge(
    //     db::col_array('user'),
    //     ['users_bind.username','users_bind.avatar','users_bind.nickname']
    // );
    // $user_join = ['[>]users_bind'=>['id'=>'uid']];
    //
    // $where = [];
    // if($keyword){
    //     $where = ['user.nickname[~]'=>$keyword];
    // }
    //
    // ctx::count(db::count('user', $where));
    // $whe = [
    //     'AND'=> $where,
    //     'ORDER'=>['user.id'=>'DESC'],
    //     'LIMIT'=>ctx::limit()
    // ];
    // $data = [
    //     'users'=>db::all('user', $whe , $user_fields, $user_join),
    //      'pageinfo' => ctx::pageinfo(),
    //      'tpl'=>'/admin/user_list'
    // ];
    public static function all($name, $where='', $cols='*', $join=null){
        $where = self::getWhere($name, $where);
        if($join){
            $rows = self::obj()->select($name, $join, $cols, $where);
        }else{
            $rows = self::obj()->select($name, $cols, $where);
        }
        return $rows;
    }

    public static function count($name, $where=['_st'=>1]){
        $where = self::getWhere($name, $where);
        return self::obj()->count($name, $where);
    }

    public static function row_sql($name, $where='', $cols='*', $join=''){
        return self::row($name, $where, $cols, $join, true);
    }

    public static function row($name, $where='', $cols='*', $join='', $sql_only=null){
        $where = self::getWhere($name, $where);
        if(!isset($where['LIMIT'])){
            $where['LIMIT'] = 1;
        }
        if($join){
            if($sql_only) return self::obj()->selectContext($name, $join, $cols, $where);
            $rows = self::obj()->select($name, $join, $cols, $where);
        }else{
            if($sql_only) return self::obj()->selectContext($name, $cols, $where);
            $rows = self::obj()->select($name, $cols, $where);
        }
        if($rows){
            return $rows[0];
        }else{
            return null;
        }
    }

    public static function one($name, $where='', $cols='*', $join=''){
        $row = self::row($name, $where, $cols, $join);
        $var = '';
        if($row){
            $keys = array_keys($row);
            $var = $row[$keys[0]];
        }
        return $var;
    }

    public static function parseUk($name, $uk, $data){
        $data_has_uk = true;
        if(is_array($uk)){
            foreach($uk as $item){
                if(!isset($data[$item])){
                    $data_has_uk = false;
                }else{
                    $whe[$item] = $data[$item];
                }
            }
        }
        else {
            if (!isset($data[$uk])) {
                $data_has_uk = false;
            }else{
                $whe = [$uk => $data[$uk]];
            }
        }

        $isInsert = false;
        if($data_has_uk){
            if(!self::obj()->has($name, ['AND' => $whe])){
                $isInsert = true;
            }
        }else{
            $isInsert = true;
        }

        return [$whe, $isInsert];
    }
    public static function save($name, $data, $uk='id'){
        list($whe, $isInsert) = self::parseUk($name, $uk, $data);
        // info("isInsert: $isInsert, $name $uk ". json_encode($data));

        if($isInsert){
            debug("insert $name : ".json_encode($data) );
            self::obj()->insert($name, $data);
            $data['id'] = self::obj()->id();
        }else{
            debug("update $name " . json_encode($whe));
            self::obj()->update($name, $data, ['AND' => $whe]);
        }
        return $data;
    }

    public static function update($name, $data, $where){
        self::obj()->update($name, $data, $where);
    }

    public static function exec($sql){
        return self::obj()->query($sql);
    }

    public static function query($sql){
        info($sql);
        return self::obj()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function queryRow($sql){
        $rows = self::query($sql);
        if($rows){
            return $rows[0];
        }else{
            return null;
        }
    }

    public static function queryOne($sql){
        $row = self::queryRow($sql);
        return self::oneVal($row);
    }

    public static function oneVal($row){
        $var = '';
        if($row){
            $keys = array_keys($row);
            $var = $row[$keys[0]];
        }
        return $var;
    }

    /**
     * 根据id批量更新
     * 调用示例：
     * $IDs = array_flip($IDs);
     *     foreach($IDs as &$id){
     *         $id = array(
     *             'priority' => $id++
     *         );
     *     }
     * updateBatch($IDs)
     * $someModel->updateBatch(array(
     *        9 => array(
     *            'values' => 1,
     *            'sort'   => 1
     *        ),
     *        10 => array(
     *            'values' => 11,
     *            'sort'   => 2
     *        ),
     *    ));
     * @param string $where
     * @param array $data  键名为主键id，键值为各个字段的值
     * @return boolean|boolean|number
     */
    public static function updateBatch($name, $data){
        $table = $name;
        if(!is_array($data) || empty($table))return FALSE;
        $sql = "UPDATE `$table` SET";
        foreach($data as $id => $row){
            foreach($row as $key=>$val){
                $toDB[$key][] = "WHEN $id THEN $val";
            }
        }
        foreach($toDB as $key=>$val){
            $sql .= ' `'.trim($key, '`').'`=CASE id '.join(' ', $val).' END,';
        }
        $sql = trim($sql, ',');
        $sql .= ' WHERE id IN('.join(',', array_keys($data)).')';
        return self::query($sql);
    }
}
