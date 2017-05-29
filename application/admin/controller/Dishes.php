<?php
namespace app\admin\controller;

use base\Base;
use \app\admin\model\DishesModel;
use \app\admin\model\TastesModel;
use \app\admin\model\ClassifyModel;

class Dishes extends Base
{
    /**
     * 添加菜肴
     */
    public function addDishes(){
        $info = array();
        $list = array();
        //获取添加店铺信息
        $dishid = input('dishid');
        $adduser = input('adduser');
        if(empty($adduser)){
            return json($this->errjson(-20001));
        }
        $dishname = input('dishname');
        if(empty($dishname)){
            return json($this->errjson(-80007));
        }
        $dishdesc = input('dishdesc');
        $price = input('price');
        if(empty($price)){
            return json($this->erres('菜肴价格不能为空'));
        }
        $discount = input('discount');
        $tastesid = input('tastesid');
        if(empty($tastesid)){
            return json($this->erres('菜肴口味不能为空'));
        }
        $classid = input('classid');
        if(empty($classid)){
            return json($this->erres('菜肴分类不能为空'));
        }
        $shopid = input('shopid');
        if(empty($shopid)){
            return json($this->erres('菜肴所属店铺不能为空'));
        }
        $dishicon = input('dishicon');
        if(empty($dishicon)){
            return json($this->erres('菜肴图片不能为空'));
        }
        $dishiconurl = str_replace("upload","public/static/images", $dishicon);
        if(is_file(ROOT_PATH.$dishicon)){
            try{
                copy(ROOT_PATH.$dishicon, ROOT_PATH.$dishiconurl); //拷贝到新目录
            }catch (\Exception $e) {
                return json($this->erres("文件传输错误"));
            }
        }
        $cuisineid = input('cuisineid');
        if(empty($cuisineid)){
            return json($this->errjson(-80009));
        }
        //判断登录
        if(!$this->checkAdminLogin()){
            return json($this->errjson(-10001));
        }
        $DishesModel = new DishesModel();
        if($dishid){
            $res = $DishesModel->modDishes($dishid, $dishname, $dishdesc, $dishicon, $price, $discount, $tastesid, $cuisineid, $classid);
        }else{
            $res = $DishesModel->addDishes($dishname, $dishdesc, $dishicon, $price, $discount, $tastesid, $cuisineid, $classid, $shopid, $adduser);
        }
        if($res){
            return json($this->sucjson($info, $list));
        }else{
            return json($this->errjson(-1));
        }
    }
    /**
     * 获取口味信息列表
     */
    public function getTastesList(){
        $info = array();
        $list = array();
        $tasteslist = input('tasteslist');
        $TastesModel = new TastesModel();
        $list = $TastesModel->getTastesList($tasteslist);
        return json($this->sucjson($info, $list));
    }
    /**
     * 获取分类信息列表
     */
    public function getClassifyList(){
        $info = array();
        $list = array();
        $tasteslist = input('tasteslist');
        $ClassifyModel = new ClassifyModel();
        $list = $ClassifyModel->getClassifyList();
        return json($this->sucjson($info, $list));
    }
    /**
     * 修改菜肴状态
     */
    public function modDishestatus(){
        //获取添加店铺信息
        $dishid = input('dishid');
        $key = input('key');
        if(empty($dishid) || empty($key)){
            return json($this->erres('参数错误'));
        }
        //判断登录
        if(!$this->checkAdminLogin()){
            return json($this->errjson(-10001));
        }
        $status = '';
        if($key == '审核'){
            $status = 1;
        }else if($key == '通过审核'){
            $status = 100;
        }else if($key == '审核不通过'){
            $status = -100;
        }else if($key == '删除'){
            $status = -300;
        }
        $res = false;
        $DishesModel = new DishesModel();
        if($status != ''){
            $res = $DishesModel->modDishestatus($dishid, $status);
        }
        if($res){
            return json($this->sucjson());
        }else{
            return json($this->errjson(-1));
        }
    }
    
    /**
     * 获取菜肴信息
     */
    public function getDishesInfo(){
        $info = array();
        $list = array();
        $dishid = input('dishid');
        if(empty($dishid)){
            return json($this->erres('参数错误'));
        }
        //判断登录
        if(!$this->checkAdminLogin()){
            return json($this->errjson(-10001));
        }
        $DishesModel = new DishesModel();
        $info = $DishesModel->getDishesInfo($dishid);
        return json($this->sucjson($info, $list));
    }
}