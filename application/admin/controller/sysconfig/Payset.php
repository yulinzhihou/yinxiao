<?php
namespace app\admin\controller\sysconfig;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use app\admin\model\sysconfig\Pay as PayModel;
use app\admin\model\sysconfig\Xpay as XPayModel;

/**
 * 支付管理
 *
 * @icon fa fa-circle-o
 */
class Payset extends Backend
{
    
    /**
     * Payset模型对象
     * @var \app\admin\model\sysconfig\Payset
     */
    protected $model = null;
    protected $payModel = null;
    protected $xpayModel = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\sysconfig\Payset;
        $this->payModel = new PayModel();
        $this->xpayModel = new XPayModel();

    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->where('team_id',$this->adminInfo['team_id'])
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->where('team_id',$this->adminInfo['team_id'])
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 同步支付配置
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function sync()
    {
        $uid = $this->adminInfo['id'];
        //查找出当前团队所选择的产品模板数据
        $payData = $this->payModel->where(['team_id'=>$this->adminInfo['team_id'],'is_forbidden'=>0])->select();
        $xpayata = $this->xpayModel->where(['team_id'=>$this->adminInfo['team_id'],'status'=>0])->select();
        $params = [];
        foreach ($payData as $value) {
            $params[] = [
                'type'              =>  0,
                'pay_id'            =>  $value['id'],
                'pay_channel'       =>  $value['pay_name'],
                'team_id'           =>  $this->adminInfo['team_id'],
                'team_name'         =>  $this->adminInfo['team_name'],
            ];
        }

        foreach ($xpayata as $value) {
            $params[] = [
                'type'              =>  1,
                'pay_id'            =>  $value['id'],
                'pay_channel'       =>  $value['pay_name'],
                'team_id'           =>  $this->adminInfo['team_id'],
                'team_name'         =>  $this->adminInfo['team_name'],
            ];
        }
        //对比当前用户已经生成了几个支付数据
        $existsPayData = $this->model->where(['team_id'=>$this->adminInfo['team_id']])->select();
        $newParams = [];
        //判断
        if (count($params) > count($existsPayData)) {
            //表示商品数量大于已经选择的数量。
            foreach ($params as $key => $value) {
                foreach ($existsPayData as $val) {
                    if ($value['type'] == $val['type'] && $value['pay_id'] == $val['pay_id']) {
                        //表示已经该支付配置已经生成过该记录，删除记录
                        $newParams[] = $value;
                        break;
                    }
                }
            }
            //求出未同步的数据
            foreach ($existsPayData as $k1 => $v1) {
                if (in_array($v1,$newParams)) {
                    unset($params[$k1]);
                }
            }
            //整理数据
            sort($params);
            //更新数据表
            if ($params) {
                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->allowField(true)->saveAll($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));

        } elseif (count($params) ==  count($existsPayData)) {
            //表示商品数量与链接种类数量一致，不需要操作。
            $this->error('商品数量与链接种类数量一致，不需要操作，直接获取链接即可');
        } else {
            //表示老板删除了或者关闭了某个支付通道，同步时需要将其去掉
            foreach ($params as $key => $value) {
                foreach ($existsPayData as $kel => $val) {
                    if ($value['type'] == $val['type'] && $value['pay_id'] == $val['pay_id']) {
                        //表示已经该支付配置已经生成过该记录，删除记录
                        unset($existsPayData[$kel]);
                        break;
                    }
                }
            }
            //整理数据,剩下的这个，表示现有不存在这个支付通道了，可以删除了
            sort($existsPayData);
            foreach ($existsPayData as $va) {
                $this->del($va->id);
            }

        }
    }

}