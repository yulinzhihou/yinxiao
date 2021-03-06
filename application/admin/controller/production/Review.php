<?php

namespace app\admin\controller\production;

use app\common\controller\Backend;

/**
 * 文案预览
 *
 * @icon fa fa-cubes
 */
class Review extends Backend
{

    protected $noNeedRight = ['index'];
    /**
     * Production模型对象
     * @var \app\admin\model\production\Production
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\production\Production;
    }

    /**
     * 查看
     */
    public function index()
    {
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $total = $this->model
            ->where($where)
            ->order($sort, $order)
            ->count();

        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->limit($offset, $limit)
            ->select();

        $list = collection($list)->toArray();
        //临时取消某个文案对某个团队显示的问题
        $total = $total-1;
        foreach ($list as $key => $item) {
            if ($item['module_name'] == '0515_DianFanBao_1' && $this->adminInfo['team_id'] == 6) {
                unset($list[$key]);
            }
        }
        $this->assign('count',$total);
        $this->assign('list',$list);
        return $this->view->fetch();
    }
}
