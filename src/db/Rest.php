<?php

namespace cm\db{

use cm\db;
use cm\db\RestHelper;

class Rest {
    private static $tbl_desc = [];

    /**
     * id=10
     * id{<}=15
     */
    public static function whereStr($where, $name){
        $ret='';
        foreach($where as $k=>$v){
            $pattern='/(.*)\{(.*)\}/i';
            $str=preg_match($pattern, $k, $matchs);
            $kk_op = '=';
            if($matchs){
                $kk = $matchs[1];
                $kk_op = $matchs[2];
            }else{
                $kk = $k;
            }
            if($col_type=db::valid_table_col($name, $kk)){
                if($col_type==2){
                    $ret.=" and t1.$kk{$kk_op}'$v'";
                }else{
                    $ret.=" and t1.$kk{$kk_op}$v";
                }
            }else{
                #info("column [$k] not exist for table '$name'");
            }
            // info("[$name] [$kk] [$col_type] $ret");
        }
        return $ret;
    }

    public static function getSqlFrom($name, $join_add, $uid, $where_str, $order){
        // Tags
        $has_tags = isset($_GET['tags'])?1:0;
        $is_adm_rest = isset($_GET['isar'])?1:0;

        $rest_xwh_tags_list = RestHelper::get_rest_xwh_tags_list();
        if($rest_xwh_tags_list && in_array($name, $rest_xwh_tags_list)){
            $has_tags = 0;
        }

        $wh_uid = RestHelper::isAdmin() && $is_adm_rest ? "1=1" : "t1.uid=$uid";
        if($has_tags){
            $tags = get('tags');
            if($tags && is_array($tags) && count($tags)==1 && !$tags[0]){
                $tags = '';
            }

            $where_tags = '';
            $in = 'not in';
            if($tags){
                if(is_string($tags)){
                    $tags = [$tags];
                }
                $tags_implode = implode("','", $tags);
                $where_tags = "and `name` in ('$tags_implode')";
                $in = 'in';
                $sql_from =  " from $name t1
                               join tag_items t on t1.id=t.`oid`
                               $join_add
                               where $wh_uid and t._st=1  and t.tagid $in
                               (select id from tags where type='$name' $where_tags )
                               $order";
            }else{
                $sql_from = " from $name t1
                              $join_add
                              where $wh_uid and t1.id not in
                              (select oid from tag_items where type='$name')
                              $order";
            }

        }else{
            //$where_uid = "1=1";
            $where_uid = $wh_uid;
            if(RestHelper::isAdmin()){
                //$where_uid = "t1.uid=$uid";
                if($name == RestHelper::user_tbl()){
                    $where_uid = "t1.id=$uid";
                }
            }
            $sql_from = "from $name t1 $join_add where $where_uid $where_str $order";
        }

        return $sql_from;
    }

    public static function getSql($name) {
        $uid = RestHelper::uid();

        // Sort
        $sort = get('sort', '_intm');
        $asc = get('asc', -1);
        if(!db::valid_table_col($name, $sort)){
            $sort = '_intm';
        }

        $asc = $asc>0?'asc':'desc';
        $order = " order by t1.$sort $asc";

        // Where
        $get_data = RestHelper::gets();
        $get_data = un_select_keys(['sort', 'asc'], $get_data);

        $st = RestHelper::get('_st', 1);
        $where = dissoc($get_data, ['token', '_st']);
        if($st!='all'){
            $where['_st'] = $st;
        }
        $where_str = self::whereStr($where, $name);

        // search
        $search = RestHelper::get('search', '');
        $search_key = RestHelper::get('search-key', '');
        if($search && $search_key){
            $where_str .= " and $search_key like '%$search%'";
        }


        // Join
        $select_add = RestHelper::select_add();
        $join_add = RestHelper::join_add();

        // Sql
        $sql_from  = self::getSqlFrom($name, $join_add, $uid, $where_str, $order);
        $sql = "select t1.* $select_add $sql_from";
        $sql_cnt = "select count(*) cnt $sql_from";

        $offset = RestHelper::offset();
        $pagesize = RestHelper::pagesize();
        $sql .= " limit $offset,$pagesize";

        // info("---GET---: $name $sql");
        // info("---GET---: $name $sql_cnt");

        return [$sql, $sql_cnt];
    }

    public static function getResName($name){
        $res_id_key = RestHelper::get('res_id_key', '');
        if($res_id_key){
            $res_id = RestHelper::get($res_id_key);
            $name .= '_' . $res_id;
        }
        return $name;
    }

    /*
        $rows = rest::getList($tblname, ['join_cols'=> [
            'bom_hot_part_opt' => [
                'on' => 'pid',
                'jkeys' => [
                    'id' => 'opt_id',
                ]
            ],
            'bom_hot_part_opt_ext' =>[
                'on' => ['opt_id' => 'pid'],
                'jtype' => '1-n',
                'jkey' => 'option',
            ]
        ]]);
        $rows = rest::getList('task_list', ['join_cols'=>[
            'task_item' => [
                'on' => 'list_id',
                'jtype'=> '1-n-o',
                'jkey' => 'items'
            ]
        ]]);
    */
    public static function getList($name, $opts=[]){
        $uid = RestHelper::uid();

        list($sql, $sql_cnt) = self::getSql($name);
        $rows = db::query($sql);
        $count = (int)db::queryOne($sql_cnt);
        $join_tags_list = RestHelper::get_rest_join_tags_list();
        if($join_tags_list && in_array($name, $join_tags_list)){
            $ids = getKeyValues($rows, 'id');
            $tags = RestHelper::get_tags_by_oid($uid, $ids, $name);
            info("get tags ok: $uid $name " . json_encode($ids));
            foreach($rows as $k=>$row){
//                $rows[$k]['tags'] = tag::getTagsByOid($uid, $row['id'], $name);
                if(isset($tags[$row['id']])){
                    $tag_item = $tags[$row['id']];
                    $rows[$k]['tags'] = getKeyValues($tag_item, 'name');
                }
            }
            info('set tags ok');
        }

        if(isset($opts['join_cols'])){
            foreach ($opts['join_cols'] as $jtbl => $jopt) {
                $jtype = getArg($jopt, 'jtype', '1-1');
                $jkeys = getArg($jopt, 'jkeys', []);
                $jwhe = getArg($jopt, 'jwhe', []);
                if(is_string($jopt['on'])){
                    $lon = 'id';
                    $ron = $jopt['on'];
                }else if(is_array($jopt['on'])){
                    $on_keys = array_keys($jopt['on']);
                    $lon = $on_keys[0];
                    $ron = $jopt['on'][$lon];

                }

                // info("------jopt: $jtype  $lon  $ron ");

                $ids = getKeyValues($rows, $lon);
                $jwhe[$ron] = $ids;
                $jrows = \db::all($jtbl, [
                    'AND' => $jwhe,
                ]);
                foreach ($jrows as $key => $jrow) {
                    // info($jrow);
                    foreach($rows as $k=>&$row){
                        if(isset($row[$lon]) && isset($jrow[$ron]) && $row[$lon] == $jrow[$ron]){
                            if($jtype == '1-1'){
                                foreach ($jkeys as $jkey => $jaskey) {
                                    $row[$jaskey] = $jrow[$jkey];
                                }
                            }
                            $jkey = isset($jopt['jkey']) ? $jopt['jkey'] : $jtbl;
                            if($jtype == '1-n'){
                                $row[$jkey][] = $jrow[$jkey];
                            }

                            if($jtype == '1-n-o'){
                                $row[$jkey][] = $jrow;
                            }

                            if($jtype == '1-1-o'){
                                $row[$jkey] = $jrow;
                            }
                        }
                    }
                }
            }
        }

        $res_name = self::getResName($name);

        return ['data'=>$rows, 'res-name'=>$res_name, 'count'=>$count];
    }

    public static function renderList($name){
        ret(self::getList($name));
    }

    public static function getItem($name, $id){
        $uid = RestHelper::uid();
        info("---GET---: $name/$id");
        $res_name = "$name-$id";

        if($name=='colls'){
            $item = db::row( $name, ["$name.id"=>$id],
                [ "$name.id", "$name.title", "$name.from_url", "$name._intm", "$name._uptm", "posts.content"],
                ['[>]posts' => ['uuid'=>'uuid']]
            );
        }else{
            if($name=='feeds'){
                $type = RestHelper::get('type');
                $rid = RestHelper::get('rid');
                $item = db::row($name, ['AND' => [ 'uid'=>$uid, 'rid'=>$id, 'type'=>$type ]]);

                if(!$item){
                    $item = [ 'rid' => $id, 'type' => $type, 'excerpt' => '', 'title' => '', ];
                }
                $res_name = "{$res_name}-$type-$id";
            }else{
                $item = db::row($name, ['id'=>$id]);
            }
        }
        if($extra_data = RestHelper::rest_extra_data()){
            $item = array_merge($item, $extra_data);
        }

        return ['data'=>$item, 'res-name'=>$res_name, 'count'=>1];
    }

    public static function renderItem($name, $id){
        ret(self::getItem($name, $id));
    }

    public static function postData($name){
        $data = db::tbl_data($name, RestHelper::data());
        $uid = RestHelper::uid();

        $tags = [];
        if($name=='tags'){
            $tags = RestHelper::get_tag_by_name($uid, $data['name'], $data['type']);
        }

        if($tags && $name=='tags'){
            $data = $tags[0];
        }else{
            info("---POST---: $name ".json_encode($data));
            unset($data['token']);
            $data['_intm'] = date('Y-m-d H:i:s');
            if(!isset($data['uid'])){
                $data['uid'] = $uid;
            }

            $data = db::tbl_data($name, $data);
            // \vld::test($name, $data);
            $data = db::save($name, $data);
        }

        return $data;
    }

    public static function renderPostData($name){
        $data = self::postData($name);
        ret($data);
    }

    public static function putData($name, $id){
        if($id==0 || $id=='' || trim($id)==''){
            info(" PUT ID IS EMPTY !!!");
            ret();
        }
        $uid = RestHelper::uid();
        $data = RestHelper::data();
        #$data = \db::tbl_data($name, \ctx::data());
        unset($data['token']);
        unset($data['uniqid']);

        self::checkOwner($name, $id, $uid);

        if(isset($data['inc'])){
            $field = $data['inc'];
            unset($data['inc']);
            db::exec("UPDATE $name SET $field = $field + 1 WHERE id=$id");
        }
        if(isset($data['dec'])){
            $field = $data['dec'];
            unset($data['dec']);
            db::exec("UPDATE $name SET $field = $field - 1 WHERE id=$id");
        }

        if(isset($data['tags'])){
            // info("up tags");
            RestHelper::del_tag_by_name($uid, $id, $name);
            $tags = $data['tags'];
            foreach($tags as $tag_name){
                $tag = RestHelper::get_tag_by_name($uid, $tag_name, $name);
                // info($tag);
                if ($tag) {
                    $tag_id = $tag[0]['id'];
                    RestHelper::save_tag_items($uid, $tag_id, $id, $name);
                }
                #info("tags: $name $id ". $tag_name);
            }
        }

        //Fixme: should not exec when inc or dec
        info("---PUT---: $name/$id ".json_encode($data));
        $data = db::tbl_data($name, RestHelper::data());
        $data['id'] = $id;
        db::save($name, $data);

        return $data;
    }

    public static function renderPutData($name, $id){
        $data = self::putData($name, $id);
        ret($data);
    }

    public static function delete($req, $name, $id){
        $uid = RestHelper::uid();

        self::checkOwner($name, $id, $uid);

        db::save($name,['_st'=>0, 'id'=>$id]);
        ret([]);
    }

    public static function checkOwner($name, $id, $uid){
        //$item = \db::row($name, ['id'=>$id]);
        $where = [
            'AND' => ['id'=>$id],
            'LIMIT' => 1,
        ];
        $rows = db::obj()->select($name, '*', $where);
        if($rows){
            $item =  $rows[0];
        }else{
            $item = null;
        }

        if($item){
            if(array_key_exists('uid', $item)){
                $db_uid = $item['uid'];
                if($name==RestHelper::user_tbl()){
                    $db_uid = $item['id'];
                }
                if($db_uid!=$uid && (!RestHelper::isAdmin() || !RestHelper::isAdminRest())){
                    ret(311, 'owner error');
                }
            }else if(!RestHelper::isAdmin()){
                ret(311, 'owner error');
            }
        }else{
            ret(312, 'not found error');
        }
    }

}

}
