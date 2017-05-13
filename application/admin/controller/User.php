<?php
namespace app\admin\controller;

use base\Base;
use \app\admin\model\AdminUserModel;
use think\Request;

class User extends Base
{
    private $uid = -1;
    private $ck = '';
    private $model = null;

    /**
     * 控制器初始化
     */
    public function __construct(){
        parent::__construct();
        $this->uid = input('uid');
        $this->ck = input('ck');
        $this->model = new AdminUserModel();
        //检查用户是否登录
        $request = Request::instance();
        if(!in_array($request->action(),array('login'))){
            if(!self::checkAdminLogin($this->uid,$this->ck)){
                die(json_encode(self::erres("用户未登录，请先登录")));
            }
        }
    }

    /**
     * 检测用户名是否可用
     */
    public function checkUserName()
    {
        $username = input('username');
        if(!checkUserName($username)){
            return json(self::erres("用户名不符合规则"));
        }
        if(false !== $this->model->checkUserName($username)){
            return json(self::erres("用户名已存在"));
        }
        return json(self::sucres());
    }

    /**
     * 获取登录用户管理菜单(树状)
     */
    public function getMenuList(){
        //获取用户所有可用模块信息
        $resinfo = self::packMenuInfo(0,$this->uid);
        return json(self::sucres($resinfo));
    }

    /**
     * 获取所有管理菜单(树状)
     */
    public function getAllMenuList(){
        //获取所有模块信息
        $resinfo = self::packMenuInfo();
        return json(self::sucres($resinfo));
    }

    /**
     * 新增用户
     * @return \think\response\Json
     */
    public function addUser()
    {
        $username = input('username');
        $password = trim(input('password'));
        $realname = input('realname');

        //检测用户名是否可用
        if(false !== $this->model->checkUserName($username)){
            return json(self::erres("该用户名已被使用"));
        }

        //密码不能为空
        if (empty($password)) {
            return json(self::erres("密码不能为空"));
        }

        $uid = $this->model->addUser($username,$password,$realname);
        if ($uid === false) {
            return json(self::erres("新增用户失败"));
        }
        return json(self::sucres());
    }

    /**
     * 修改用户信息
     */
    public function updateUserInfo(){
        $userid = intval(input('userid',0));
        if($userid <= 0){
            return json(self::erres("用户ID不能为空"));
        }
        $userinfo = array(
            'username' => input('username'),
            'password' => input('password'),
            'realname' => input('realname'),
            'userstatus' => intval(input('userstatus',100)),
            'rolelist' => explode(',',trim(input('rolelist'))),
        );
        $ori_userinfo = $this->model->getUserInfoByUid($userid);

        //修改用户名时需检测新用户名是否可用
        if((!empty($userinfo['username']) && $userinfo['username'] != $ori_userinfo['username'])){
            //检测用户名是否可用
            if(false !== $this->model->checkUserName($userinfo['username'])){
                return json(self::erres("该用户名已被使用"));
            }
        }

        //修改密码时
        if((!empty($userinfo['password']) && $userinfo['password'] != $ori_userinfo['password'])){
            //密码不能为空
            if (empty($userinfo['password'])) {
                return json(self::erres("密码不能为空"));
            }
        }

        //更新用户信息
        if($this->model->updateUserInfo($userid,$userinfo)){
            return json(self::sucres());
        }else{
            return json(self::erres("修改用户信息失败"));
        }
    }

    /**
     * 删除用户信息(禁止删除自己)
     * 一并删除用户角色关联信息
     * 一并删除用户登录信息
     */
    public function delUser(){
        $uidlist = explode(',',trim(input('uidlist')));
        if(empty($uidlist)){
            return json(self::erres("待删除用户ID列表为空"));
        }
        if(in_array($this->uid,$uidlist)){
            return json(self::erres("不能删除自己"));
        }
        if($this->model->delUser($uidlist)){
            return json(self::sucres());
        }else{
            return json(self::erres("删除用户信息失败"));
        }
    }


    /**
     * 获取单个用户信息
     * 含所在角色组
     */
    public function getUserInfo()
    {
        $userid = intval(input('userid',0));
        if($userid <= 0){
            return json(self::erres("用户ID不能为空"));
        }
        //获取用户信息
        $userinfo = $this->model->getUserInfoByUid($userid);
        if(empty($userinfo)){
            return json(self::erres("用户信息不存在"));
        }
        $userinfo['roleinfo'] = $this->model->getUserRoleList($userid);
        $resinfo = $userinfo;
        return json(self::sucres($resinfo));
    }

    /**
     * 根据角色ID获取用户列表(默认查全部用户)
     */
    public function getUserList(){
        $rid = intval(input('rid',0));
        $userlist = $this->model->getUserList($rid);
        $resinfo = array();
        $reslist = $userlist;
        return json(self::sucres($resinfo,$reslist));
    }

    /**
     * 用户登录
     * @return \think\response\Json
     */
    public function login()
    {
        $username = trim(input('username'));
        $password = trim(input('password'));
        $ip = trim(input('ip'));

        if(empty($username) || empty($password)){
            return json(self::erres("用户名或密码为空"));
        }
        $ret_user = $this->model->checkUserName($username);
        if(false === $ret_user){
            return json(self::erres("用户名不存在"));
        }
        $this->uid = $ret_user['uid'];
        $userinfo = $this->model->getUserInfoByUid($this->uid);
        if(empty($userinfo)){
            return json(self::erres("用户ID不存在"));
        }

        if(strtoupper($password) !== $userinfo['password']){
            return json(self::erres("登录密码不正确"));
        }

        if(!$this->model->checkUserStatus($this->uid)){
            return json(self::erres("该用户已被禁用"));
        }

        //写登录信息
        $ck = 'ck_' . strtoupper(base64_encode(md5($this->uid.$username.time())));
        $ret_login = $this->model->addUserLogin($ck,$this->uid,$ip);
        if ($ret_login === false) {
            return json(self::erres("写登录信息失败"));
        }
        $resinfo = array(
            'ck' => $ret_login['ck'],
            'uid' => $ret_login['uid'],
        );
        return json(self::sucres($resinfo));
    }

    /**
     * 退出登录
     * @return \think\response\Json
     */
    public function logout()
    {
        if ($this->model->setCkExpired($$this->ck)) {
            return json(self::sucres());
        } else {
            return json(self::erres("退出登录失败"));
        }
    }

    /**
     * 新增角色信息
     */
    public function addRole(){
        $rolename = trim(input('rolename'));
        $describle = trim(input('describle'));

        //角色名不能为空
        if (empty($rolename)) {
            return json(self::erres("角色名不能为空"));
        }

        //检测角色名是否可用
        if(!$this->model->checkRoleName($rolename)){
            return json(self::erres("该角色名已被使用"));
        }

        $rid = $this->model->addRole($rolename,$describle);
        if ($rid === false) {
            return json(self::erres("新增角色信息失败"));
        }
        return json(self::sucres());
    }

    /**
     * 修改角色信息
     */
    public function updateRoleInfo(){
        $rid = intval(input('rid',0));
        if($rid <= 0){
            return json(self::erres("角色ID不能为空"));
        }
        $roleinfo = array(
            'rolename' => trim(input('rolename')),
            'describle' => trim(input('describle')),
            'modulelist' => explode(',',trim(input('modulelist'))),
        );

        //更新角色信息
        if($this->model->updatRoleInfo($rid,$roleinfo)){
            return json(self::sucres());
        }else{
            return json(self::erres("修改角色信息失败"));
        }
    }

    /**
     * 删除角色信息
     * 关联用户不为空时禁止删除
     * 一并删除角色模块关联信息
     * @return \think\response\Json
     */
    public function delRole(){
        $rid = intval(input('rid',0));
        if($rid <= 0){
            return json(self::erres("待删除角色ID为空"));
        }
        //检查角色是否可删除
        $userlist = $this->model->getUserList($rid);
        if(!empty($userlist)){
            return json(self::erres("角色关联用户不为空,禁止删除"));
        }
        if($this->model->delRole($rid)){
            return json(self::sucres());
        }else{
            return json(self::erres("删除角色信息失败"));
        }
    }

    /**
     * 获取角色列表
     */
    public function getRoleList(){
        $rolelist = $this->model->getRoleList();
        $resinfo = array();
        $reslist = $rolelist;
        return json(self::sucres($resinfo,$reslist));
    }

    /**
     * 获取单个角色信息
     * 1)角色基本信息
     * 2)使用该角色的用户信息列表
     * 3)该角色包含的模块ID信息列表
     */
    public function getRoleInfo(){
        $rid = intval(input('rid',0));
        if($rid <= 0){
            return json(self::erres("角色ID不能为空"));
        }
        $roleinfo = $this->model->getRoleInfo($rid);
        $roleinfo['userinfo'] = $this->model->getUserList($rid);
        $moduleinfo = $this->model->getModuleListByRid($rid);
        $modulelist = array();
        if(!empty($moduleinfo)){
            foreach($moduleinfo as $module){
                array_push($modulelist,$module['mid']);
            }
        }
        $roleinfo['modulelist'] = $modulelist;
        $resinfo = $roleinfo;
        return json(self::sucres($resinfo));
    }

    /**
     * 新增模块信息
     * @return \think\response\Json
     */
    public function addModule(){
        $modulename = trim(input('modulename'));
        $describle = trim(input('describle'));
        $moduletype = intval(input('moduletype',0));
        $xpath = trim(input('xpath'));
        $parentid = intval(input('parentid',0));
        $showorder = intval(input('showorder',0));

        //角色名不能为空
        if (empty($modulename)) {
            return json(self::erres("模块名称不能为空"));
        }

        //组装目录层级信息
        if($parentid > 0){
            $moduleinfo = $this->model->getModuleInfo($parentid);
            if(empty($moduleinfo)){
                return json(self::erres("父模块信息不存在"));
            }
        }
        $mid = $this->model->addModule($modulename,$describle,$moduletype,$xpath,$parentid,$showorder);
        if ($mid === false) {
            return json(self::erres("新增模块信息失败"));
        }
        return json(self::sucres());
    }

    /**
     * 修改模块信息
     */
    public function updateModuleInfo(){
        $mid = intval(input('mid',0));
        if($mid <= 0){
            return json(self::erres("模块ID不能为空"));
        }
        $moduleinfo = array(
            'modulename' => input('modulename'),
            'describle' => input('describle'),
            'moduletype' => input('moduletype'),
            'xpath' => input('xpath'),
            'parentid' => input('parentid'),
            'showorder' => input('showorder'),
        );

        //更新模块信息
        if($this->model->updateModuleInfo($mid,$moduleinfo)){
            return json(self::sucres());
        }else{
            return json(self::erres("修改模块信息失败"));
        }
    }

    /**
     * 删除模块信息
     * 虚节点子模块不为空时禁止删除
     * 一并删除模块角色关联信息
     * @return \think\response\Json
     */
    public function delModule(){
        $midlist = explode(',',trim(input('midlist')));
        if(empty($midlist)){
            return json(self::erres("待删除模块ID列表为空"));
        }
        //检查待删除模块是否子节点为空
        foreach($midlist as $mid){
            $moduleinfo = $this->model->getModuleInfo($mid);
            if($moduleinfo['moduletype'] == 0){
                $modulelist = $this->model->getModuleByParentid($mid);
                if(!empty($modulelist)){
                    return json(self::erres("待删除模块子模块不为空"));
                }
            }
        }
        if($this->model->delModule($midlist)){
            return json(self::sucres());
        }else{
            return json(self::erres("删除模块信息失败"));
        }
    }

    /**
     * 获取单个模块信息
     * @return \think\response\Json
     */
    public function getModuleInfo(){
        $mid = intval(input('mid',0));
        $moduleinfo = $this->model->getModuleInfo($mid);
        if(empty($moduleinfo)){
            return json(self::erres("模块信息不存在"));
        }
        $resinfo = $moduleinfo;
        return json(self::sucres($resinfo));
    }

    /**
     * 获取模块列表
     */
    public function getModuleList(){
        $modulelist = $this->model->getModuleList();
        $resinfo = array();
        $reslist = $modulelist;
        return json(self::sucres($resinfo,$reslist));
    }

    /**
     * 组装目录信息(多维数组)
     */
    public function packMenuInfo($parentid=0,$uid=0){
        $modulelist = $this->model->getModuleByParentid($parentid,$uid);
        $menuinfo = array();
        if(!empty($modulelist)){
            foreach($modulelist as $module){
                $mid = $module['mid'];
                $module['childinfo'] = self::packMenuInfo($mid,$uid);
                $menuinfo[$mid] = $module;
            }
        }
        return $menuinfo;
    }

}
