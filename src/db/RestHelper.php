<?php

namespace cm\db{

class RestHelper {
    private static $_ins = null;

    public static function ins($ins=null){
        if($ins){
            self::$_ins = $ins;
        }
        if(!self::$_ins && class_exists('\db\RestHelperIns')){
            self::$_ins = new \db\RestHelperIns();
        }
        return self::$_ins;
    }

    public static function get_rest_xwh_tags_list(){
        return self::ins()->get_rest_xwh_tags_list();
    }
    public static function get_rest_join_tags_list(){
        return self::ins()->get_rest_join_tags_list();
    }

    public static function rest_extra_data($item){
        return self::ins()->rest_extra_data($item);
    }

    public static function get_tags_by_oid($uid, $ids, $name){
        return self::ins()->get_tags_by_oid($uid, $ids, $name);
    }
    public static function get_tag_by_name($uid, $name, $type){
        return self::ins()->get_tag_by_name($uid, $name, $type);
    }
    public static function del_tag_by_name($uid, $id, $name){
        return self::ins()->del_tag_by_name($uid, $id, $name);
    }
    public static function save_tag_items($uid, $tag_id, $id, $name){
        return self::ins()->save_tag_items($uid, $tag_id, $id, $name);
    }

    public static function isAdmin(){
        return self::ins()->isAdmin();
    }
    public static function isAdminRest(){
        return self::ins()->isAdminRest();
    }
    public static function user_tbl(){
        return self::ins()->user_tbl();
    }

    public static function data(){
        return self::ins()->data();
    }
    public static function uid(){
        return self::ins()->uid();
    }

    public static function get($k, $def=''){
        return self::ins()->get($k, $def);
    }
    public static function gets(){
        return self::ins()->gets();
    }

    public static function select_add(){
        return self::ins()->select_add();
    }
    public static function join_add(){
        return self::ins()->join_add();
    }
    public static function offset(){
        return self::ins()->offset();
    }
    public static function pagesize(){
        return self::ins()->pagesize();
    }
}

}
