<?php

namespace app\index\controller;
use think\Request;
use think\Db;
use addons\wechat\model\WechatCaptcha;
use app\common\controller\Frontend;
use app\common\library\Ems;
use app\common\library\Sms;
use app\common\model\Attachment;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Validate;


// 允许全局跨域
header('Access-Control-Allow-Origin: *');
header('Access-Control-Max-Age: 1800');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: *');
if (strtoupper($_SERVER['REQUEST_METHOD']) == "OPTIONS") {
    http_response_code(204);
    exit;
}



/**
 * 会员中心
 */
class User extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['login', 'register', 'third'];
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $auth = $this->auth;

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'), '/');
        }

        //监听注册登录退出的事件
        Hook::add('user_login_successed', function ($user) use ($auth) {
            $expire = input('post.keeplogin') ? 30 * 86400 : 0;
            Cookie::set('uid', $user->id, $expire);
            Cookie::set('token', $auth->getToken(), $expire);
        });
        Hook::add('user_register_successed', function ($user) use ($auth) {
            Cookie::set('uid', $user->id);
            Cookie::set('token', $auth->getToken());
        });
        Hook::add('user_delete_successed', function ($user) use ($auth) {
            Cookie::delete('uid');
            Cookie::delete('token');
        });
        Hook::add('user_logout_successed', function ($user) use ($auth) {
            Cookie::delete('uid');
            Cookie::delete('token');
        });
    }

    /**
     * 会员中心
     */
    public function index()
    {      $this->layout = 'Default';  
        $this->view->assign('title', __('User center'));
        return $this->view->fetch();
    }
    
    
        /**
     * 待呼列表页
     */
    public function userlist()
    {
         parent::_initialize();
         
 
        $MKT_SITS=array(0=>'其他情况',1=>'考虑要跟进',2=>'坚决不要',3=>'办理成功',4=>'已加微信',5=>'通话中',6=>'无人接听',7=>'停机空号',8=>'已经转网');
    $departmentid=implode(db('user')->where('id',Cookie::get('uid'))->column('group_id')); 
    $userid=implode(db('user')->where('id',Cookie::get('uid'))->column('userid'));
    $data=db('missionlist')->where('staff_id',$userid)->where('already',0)->select();

        $missiontype=implode(db('user')->where('id',Cookie::get('uid'))->column('mission_ids'));
        $missiontype=json_decode($missiontype);
        $typename=db('missions')->where('id','in', $missiontype)->select();
        $username=db('user')->where('id',Cookie::get('uid'))->column('nickname');
        $username=implode($username);
        $missionid=implode(db('missionlist')->where('staff_id',$userid)->column('missionid'));
     
   
        $this->view->assign('missionid',$missionid);
        $this->view->assign('missiontype',$typename);
        $this->view->assign('departmentid',$departmentid);
        $this->view->assign('username',$username);
         $this->view->assign('userid',$userid);
        $this->view->assign('missionlist',$data);
        $this->assignconfig("MKT_SITS",$MKT_SITS);
        $this->view->assign('title', __('User center'));
        return $this->view->fetch();
    }
    
  /**
     * 已呼列表页
     */
    public function missionlist()
    {
        parent::_initialize();
         $userid=Cookie::get('uid');
          $usersid=implode(db('user')->where('id',$userid)->column('userid'));
        $data=db('missionlist')->where('staff_id',$usersid)->where('already',1)->order('id','asc')->select();
        
 $this->layout = 'default';  
        $username=db('user')->where('id',Cookie::get('uid'))->column('nickname');
        $username=implode($username);
       
        $groupid=implode(db('user')->where('id',$userid)->column('group_id'));
        $this->view->assign('username',$username);
         $this->view->assign('userid',$userid);
         $this->view->assign('groupid',$groupid);
         $this->view->assign('missionlist',$data);
        $this->view->assign('title', __('User center'));
        return $this->view->fetch();
    }

    /**
     * 注册会员
     */
    public function register()
    {
         
        $url = $this->request->request('url', '', 'trim');
        if ($this->auth->id) {
            $this->success(__('You\'ve logged in, do not login again'), $url ? $url : url('user/index'));
        }
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $mobile = $this->request->post('mobile');
            $captcha = $this->request->post('captcha');
            $token = $this->request->post('__token__');
            $rule = [
                'username'  => 'require|length:3,30',
                'password'  => 'require|length:6,30',
                'mobile'    => 'regex:/^1\d{10}$/',
                '__token__' => 'require|token',
            ];

            $msg = [
                'username.require' => 'Username can not be empty',
                'username.length'  => 'Username must be 3 to 30 characters',
                'password.require' => 'Password can not be empty',
                'password.length'  => 'Password must be 6 to 30 characters',
                'mobile'           => 'Mobile is incorrect',
            ];
            $data = [
                'username'  => $username,
                'password'  => $password,
                'mobile'    => $mobile,
                '__token__' => $token,
            ];
            //验证码
            $captchaResult = true;
            $captchaType = config("fastadmin.user_register_captcha");
            if ($captchaType) {
                if ($captchaType == 'mobile') {
                    $captchaResult = Sms::check($mobile, $captcha, 'register');
                } elseif ($captchaType == 'wechat') {
                    $captchaResult = WechatCaptcha::check($captcha, 'register');
                } elseif ($captchaType == 'text') {
                    $captchaResult = \think\Validate::is($captcha, 'captcha');
                }
            }
            if (!$captchaResult) {
                $this->error(__('Captcha is incorrect'));
            }
            $validate = new Validate($rule, $msg);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
            }
            if ($this->auth->register($username, $password, $mobile)) {
                $this->success(__('Sign up successful'), $url ? $url : url('user/index'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }
        //判断来源
        $referer = $this->request->server('HTTP_REFERER');
        if (!$url && (strtolower(parse_url($referer, PHP_URL_HOST)) == strtolower($this->request->host()))
            && !preg_match("/(user\/login|user\/register|user\/logout)/i", $referer)) {
            $url = $referer;
        }
        $this->view->assign('captchaType', config('fastadmin.user_register_captcha'));
        $this->view->assign('url', $url);
        $this->view->assign('title', __('Register'));
        return $this->view->fetch();
    }

    /**
     * 会员登录
     */
    public function login()
    {    
        $url = $this->request->request('url', '', 'trim');
        if ($this->auth->id) {
            $this->success(__('You\'ve logged in, do not login again'), $url ? $url : url('user/index'));
        }
        if ($this->request->isPost()) {
            $account = $this->request->post('account');
            $password = $this->request->post('password');
            $keeplogin = (int)$this->request->post('keeplogin');
            $token = $this->request->post('__token__');
            $rule = [
                'account'   => 'require|length:3,50',
                'password'  => 'require|length:6,30',
                '__token__' => 'require|token',
            ];

            $msg = [
                'account.require'  => 'Account can not be empty',
                'account.length'   => 'Account must be 3 to 50 characters',
                'password.require' => 'Password can not be empty',
                'password.length'  => 'Password must be 6 to 30 characters',
            ];
            $data = [
                'account'   => $account,
                'password'  => $password,
                '__token__' => $token,
            ];
            $validate = new Validate($rule, $msg);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
                return false;
            }
            if ($this->auth->login($account, $password)) {
                $this->success(__('Logged in successful'), $url ? $url : url('user/index'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }
        //判断来源
        $referer = $this->request->server('HTTP_REFERER');
        if (!$url && (strtolower(parse_url($referer, PHP_URL_HOST)) == strtolower($this->request->host()))
            && !preg_match("/(user\/login|user\/register|user\/logout)/i", $referer)) {
            $url = $referer;
        }
        $this->view->assign('url', $url);
        $this->view->assign('title', __('Login'));
        return $this->view->fetch();
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        if ($this->request->isPost()) {
            $this->token();
            //退出本站
            $this->auth->logout();
            $this->success(__('Logout successful'), url('user/index'));
        }
        $html = "<form id='logout_submit' name='logout_submit' action='' method='post'>" . token() . "<input type='submit' value='ok' style='display:none;'></form>";
        $html .= "<script>document.forms['logout_submit'].submit();</script>";

        return $html;
    }

    /**
     * 个人信息
     */
    public function profile()
    {
         
        $this->view->assign('title', __('Profile'));
        return $this->view->fetch();
    }

    /**
     * 修改密码
     */
    public function changepwd()
    {
        if ($this->request->isPost()) {
            $oldpassword = $this->request->post("oldpassword");
            $newpassword = $this->request->post("newpassword");
            $renewpassword = $this->request->post("renewpassword");
            $token = $this->request->post('__token__');
            $rule = [
                'oldpassword'   => 'require|regex:\S{6,30}',
                'newpassword'   => 'require|regex:\S{6,30}',
                'renewpassword' => 'require|regex:\S{6,30}|confirm:newpassword',
                '__token__'     => 'token',
            ];

            $msg = [
                'renewpassword.confirm' => __('Password and confirm password don\'t match')
            ];
            $data = [
                'oldpassword'   => $oldpassword,
                'newpassword'   => $newpassword,
                'renewpassword' => $renewpassword,
                '__token__'     => $token,
            ];
            $field = [
                'oldpassword'   => __('Old password'),
                'newpassword'   => __('New password'),
                'renewpassword' => __('Renew password')
            ];
            $validate = new Validate($rule, $msg, $field);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
                return false;
            }

            $ret = $this->auth->changepwd($newpassword, $oldpassword);
            if ($ret) {
                $this->success(__('Reset password successful'), url('user/login'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }
        $this->view->assign('title', __('Change password'));
        return $this->view->fetch();
    }

    public function attachment()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $mimetypeQuery = [];
            $where = [];
            $filter = $this->request->request('filter');
            $filterArr = (array)json_decode($filter, true);
            if (isset($filterArr['mimetype']) && preg_match("/(\/|\,|\*)/", $filterArr['mimetype'])) {
                $this->request->get(['filter' => json_encode(array_diff_key($filterArr, ['mimetype' => '']))]);
                $mimetypeQuery = function ($query) use ($filterArr) {
                    $mimetypeArr = array_filter(explode(',', $filterArr['mimetype']));
                    foreach ($mimetypeArr as $index => $item) {
                        $query->whereOr('mimetype', 'like', '%' . str_replace("/*", "/", $item) . '%');
                    }
                };
            } elseif (isset($filterArr['mimetype'])) {
                $where['mimetype'] = ['like', '%' . $filterArr['mimetype'] . '%'];
            }

            if (isset($filterArr['filename'])) {
                $where['filename'] = ['like', '%' . $filterArr['filename'] . '%'];
            }

            if (isset($filterArr['createtime'])) {
                $timeArr = explode(' - ', $filterArr['createtime']);
                $where['createtime'] = ['between', [strtotime($timeArr[0]), strtotime($timeArr[1])]];
            }
            $search = $this->request->get('search');
            if ($search) {
                $where['filename'] = ['like', '%' . $search . '%'];
            }

            $model = new Attachment();
            $offset = $this->request->get("offset", 0);
            $limit = $this->request->get("limit", 0);
            $total = $model
                ->where($where)
                ->where($mimetypeQuery)
                ->where('user_id', $this->auth->id)
                ->order("id", "DESC")
                ->count();

            $list = $model
                ->where($where)
                ->where($mimetypeQuery)
                ->where('user_id', $this->auth->id)
                ->order("id", "DESC")
                ->limit($offset, $limit)
                ->select();
            $cdnurl = preg_replace("/\/(\w+)\.php$/i", '', $this->request->root());
            foreach ($list as $k => &$v) {
                $v['fullurl'] = ($v['storage'] == 'local' ? $cdnurl : $this->view->config['upload']['cdnurl']) . $v['url'];
            }
            unset($v);
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        $mimetype = $this->request->get('mimetype', '');
        $mimetype = substr($mimetype, -1) === '/' ? $mimetype . '*' : $mimetype;
        $this->view->assign('mimetype', $mimetype);
        $this->view->assign("mimetypeList", \app\common\model\Attachment::getMimetypeList());
        return $this->view->fetch();
    }
    
     /**
     * APP会员中心
     */
    public function userpage()
    {
       
        return $this->view->fetch();
    }
    
         
    /**
     * 用户备注接口
    */
    public function remarks(Request$request)
    {   
      $today = date("Y-m-d H:i:s");
      $tell=$request->post("tell");
      $missionid=$request->post("missionid");
      $remarks=$request->post("remarks");
      $MKT_SIT=$request->post("MKT_SIT");
      $userid=$request->post("userid");
      $username=$request->post("nickname");
      $insertdata =array('mission_id'=>$missionid,'username'=>$username,'datetime'=>$today,'mobile'=>$tell,'userid'=>$userid,'remarks'=>$remarks,'MKT_SIT'=>$MKT_SIT); 
      $update=DB::name('missionlist')->where('tell',$tell)->update(['remarks'=>$remarks,'MKT_SIT'=>$MKT_SIT]);
      $insert=db('remark')->insert($insertdata);
      $this->success('更新成功！', url('user/missionlist'));
        
    }
    
         
    /**
     * 申请任务接口
    */
    public function requestmission()
    {   
       
        $ids=$this->request->param("ids");
   
        // $ids=$_POST['ids'];
        $uid=Cookie::get('uid');
        $staffid=implode(db('user')->where('id',$uid)->column('userid'));
        $groupid=implode(db('user')->where('id',$staffid)->column('group_id'));
      
      $today = date("Y-m-d H:i:s");
      //取未分配任务
    //   $missids=db('missionlist')->where('missionid','in',$ids)->where('staff_id',0)->column('id');
      $missids=db('missionlist')->where('missionid',$ids)->where('staff_id',0)->column('id');
      $length=count($missids);
  

    
      //此账号已分配且未呼任务数
      $doesnot=db('missionlist')->where('staff_id',$staffid)->where('already',0)->select();
      //此账号未呼任务数
      $doesnotnum=count($doesnot);
    
      if($doesnotnum>=5){
      //$this->success('你的未呼任务还很多！', url('user/userlist'));
      header ( "Status: 400 Bad Request" );   
      }else{
      $update=db('missionlist')->where('id','in',$missids)->where('missionid',$ids)->limit(20)->update(['staff_id'=>$staffid,'insert_date'=>$today,'departmentid'=>$groupid]);

      }
        $this->success('任务状态更新成功');    
       return json($missids);

       
        
    }
    
    
  
public function history(Request$request)
    {   
        
        $id=$request->get("id");
        $tell=$request->get("tell");
        $userid=Cookie::get('uid');
        $remark=db('remark')->where('userid',Cookie::get('uid'))->where('mobile',$tell)->select();
        // return  $tell;
        $this->view->assign('remark',$remark);
        return $this->view->fetch();
    }
    
    
    
    
    
    
        /**
     * 待呼列表页
     */
    public function demo()
    {
         parent::_initialize();
         
         
     $groupid=implode(db('user')->where('id',Cookie::get('uid'))->column('group_id'));
        //  if($groupid=='2'){
             
        //      return "<h1 style='font-size:112px;margin-top:35%' >请重启手机后重新进入APP！</h1>";
             
        //  }
        $MKT_SITS=array(0=>'其他情况',1=>'考虑要跟进',2=>'坚决不要',3=>'办理成功',4=>'已加微信',5=>'通话中',6=>'无人接听',7=>'停机空号',8=>'已经转网');
    $departmentid=implode(db('user')->where('id',Cookie::get('uid'))->column('group_id')); 
    $userid=implode(db('user')->where('id',Cookie::get('uid'))->column('userid'));
    $data=db('missionlist')->where('staff_id',$userid)->where('already',0)->select();
   
        $canuse=count(db('missionlist')->where('staff_id',$userid)->where('already',0)->select());
        $missiontype=implode(db('user')->where('id',Cookie::get('uid'))->column('mission_ids'));
        $missiontype=json_decode($missiontype);
        $typename=db('missions')->where('id','in', $missiontype)->select();
        $username=db('user')->where('id',Cookie::get('uid'))->column('nickname');
        $username=implode($username);
        // $missionid=implode(db('user')->where('id',Cookie::get('uid'))->column('mission_ids'));
        //  $missionid=json_decode($missionid);
        // $missionid=end($missionid);
         $missionid=implode(db('missionlist')->where('staff_id',$userid)->where('already',0)->column('missionid'));
      
    $this->view->engine->layout(false);
   
        $this->view->assign('canuse',$canuse);
        $this->view->assign('missionid',$missionid);
        $this->view->assign('missiontype',$typename);
        $this->view->assign('departmentid',$departmentid);
        $this->view->assign('username',$username);
        $this->view->assign('userid',$userid);
        $this->view->assign('missionlist',$data);
        $this->assignconfig("MKT_SITS",$MKT_SITS);
        // $this->view->assign('title', __('User center'));
        return $this->view->fetch();
    }
    
     
    
    
    
}
