<?php

namespace db{

use \db\Tagx as tag;

class RestHelper {
    public static function get_rest_xwh_tags_list(){
        return \cfg::rest('rest_xwh_tags_list');
    }
    public static function get_rest_join_tags_list(){
        return \cfg::rest('rest_join_tags_list');
    }

    public static function rest_extra_data($item){
        return array_merge($item, \ctx::rest_extra_data());
    }

    public static function get_tags_by_oid($uid, $ids, $name){
        return tag::getTagsByOids($uid, $ids, $name);
    }
    public static function get_tag_by_name($uid, $name, $type){
        return tag::getTagByName($uid, $name, $type);
    }
    public static function del_tag_by_name($uid, $id, $name){
        return tag::delTagByOid($uid, $id, $name);
    }
    public static function save_tag_items($uid, $tag_id, $id, $name){
        return tag::saveTagItems($uid, $tag_id, $id, $name);
    }

    public static function isAdmin(){
        return \ctx::isAdmin();
    }
    public static function isAdminRest(){
        return \ctx::isAdminRest();
    }
    public static function user_tbl(){
        return \ctx::user_tbl();
    }

    public static function data(){
        return \ctx::data();
    }
    public static function uid(){
        \ctx::uid();
    }

    public static function get($k, $def){
        return get($k, $def);
    }
    public static function gets(){
        return gets();
    }

    public static function select_add(){
        return \ctx::rest_select_add();
    }
    public static function join_add(){
        return \ctx::rest_join_add();
    }
    public static function offset(){
        return \ctx::offset();
    }
    public static function pagesize(){
        return \ctx::pagesize();
    }
}

}
