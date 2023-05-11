<?php

namespace app\admin\controller;
use think\Db;
use app\common\controller\Backend;

/**
 * 奖项管理
 *
 * @icon fa fa-circle-o
 */
class Gifts extends Backend
{

    /**
     * Gifts模型对象
     * @var \app\admin\model\Gifts
     */
    protected $model = null;

    public function _initialize()
    {
        $activitys=db('activity')->column('name','id');
        parent::_initialize();
        $this->model = new \app\admin\model\Gifts;
        $this->assignconfig("activitys",$activitys);
         $this->view->assign("activitys",$activitys);

    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
     
       /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        
        if (false === $this->request->isPost()) {
        return $this->view->fetch();
        }
        
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $length=count(db('gifts')->where('belongs_id',1)->select());
        //限制转盘游戏奖项上限
        if($params['belongsto']=='1'&&$length>6){
           $this->error('转盘奖项不可大于7！'); 
        }
        $belongsid=$params['belongsto'];
        $ratesum=array_sum(db('gifts')->where('belongs_id',$belongsid)->column('rate'))+$params['rate'];
        //限制概率上限
        if($ratesum>100){ $this->error('总概率不可大于100！'); }
       
        $params['belongs_id']=$belongsid;
        $belongsto=implode(db('activity')->where('id',$belongsid)->column('name'));
        $params['belongsto']=$belongsto;
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
      
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false){
            $this->error(__('No rows were inserted'));
        }
       
        $this->success();
        
    }

    /**
     * 编辑
     *
     * @param $ids
     * @return string
     * @throws DbException
     * @throws \think\Exception
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        $activity=implode(db('gifts')->where('id',$ids)->column('belongs_id'));
        $this->view->assign("activity",$activity);
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $belongsid=$params['belongsto'];
        //该奖品原本的概率
        $already=implode(db('gifts')->where('id',$ids)->column('rate'));
        $ratesum=array_sum(db('gifts')->where('belongs_id',$belongsid)->column('rate'))+$params['rate']-$already;
        //限制概率上限
        if($ratesum>100){ $this->error('总概率不可大于100！'); }
        $params['belongs_id']=$belongsid;
        $belongsto=implode(db('activity')->where('id',$belongsid)->column('name'));
        $params['belongsto']=$belongsto;
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        
        $this->success();
    }



}
