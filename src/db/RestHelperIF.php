<?php

namespace cm\db{

interface RestHelperIF
{
    public function get_rest_xwh_tags_list();
    public function get_rest_join_tags_list();
    public function rest_extra_data($item);
    public function get_tags_by_oid($uid, $ids, $name);
    public function get_tag_by_name($uid, $name, $type);
    public function del_tag_by_name($uid, $id, $name);
    public function save_tag_items($uid, $tag_id, $id, $name);
    public function isAdmin();
    public function isAdminRest();
    public function user_tbl();
    public function data();
    public function uid();
    public function get($k, $def);
    public function gets();
    public function select_add();
    public function join_add();
    public function offset();
    public function pagesize();
}

}
