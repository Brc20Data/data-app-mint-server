<?php
namespace app\controller;

use app\BaseController;
use think\cache\driver\Redis;
use think\facade\Db;

class Hotmint extends BaseController
{
    /**
     * 获取数据
     * @return \think\response\Json
     */
    public function getDataRecordList()
    {
        $status = $this->request->param('status');// 1 热门 2 进行中 3已完成 4 全部
        $page = $this->request->param('page') ? (int)$this->request->param('page') :1;// 页码
        $search_name = $this->request->param('search_name');// 搜索内容
        $res_data=array();
        $total=0;
        if($search_name){
            $sql="select (mint_value/max_value*100) as mint_progress,max_value,mint_value,tick_value,start_timestamp,mint_address 
                          from brc20_progress  where tick_value like '%".$search_name."%'  limit 1";
            $mint_data = Db::query($sql);
            if($mint_data) {
                foreach ($mint_data as $keys => $vals) {
                    $res_data[$keys]['tick_name'] = $vals['tick_value'];
                    $res_data[$keys]['deployment_time'] = date('Y/m/d H:i:s', $vals['start_timestamp']);
                    $res_data[$keys]['cast_number'] = $vals['mint_address'];
                    if($vals['mint_progress']>'0.99'){
                        $res_data[$keys]['schedule'] =substr($vals['mint_progress'], 0, 5).'%';
                    }else{
                        $res_data[$keys]['schedule'] =substr($vals['mint_progress'], 0, 5).'%';
                    }
                }
            }
            $total=10;
        }else{
            $limit =  ($page-1)*10;
            $redis = new Redis();
            switch ($status)
            {
                case 1:
                    $cache_key='crontab:mint:data';
                    $mint_data = json_decode($redis->get($cache_key),true);
                    foreach ($mint_data as $keys=>$vals){
                        $res_data[$keys]['tick_name']=$vals['tick_value'];
                        $data_vals = json_decode($redis->get('mint:brc-20:'.$vals['tick_value']),true);
                        $res_data[$keys]['deployment_time']=date('Y/m/d H:i:s',$data_vals['startTimestamp']);
                        $res_data[$keys]['holders_number']=$vals['times'];
                        $res_data[$keys]['cast_number']=$data_vals['mintAddress'];
                        if($data_vals['maxValue']<=0){
                            $res_data[$keys]['schedule']='100%';
                        }else{
                            $schedule = $data_vals['mintValue']/$data_vals['maxValue'];
                            if($schedule>'0.99'){
                                $res_data[$keys]['schedule']='100%';
                            }else{
                                $res_data[$keys]['schedule']=(substr($schedule, 0, 5)*100).'%';
                            }
                        }
                    }
                    $total=10;
                    break;
                case 2:
                    $total_sql="select count(tick_value)as count_tick  from brc20_progress where max_value!=mint_value and max_value>0 and mint_value>0 and mint_value/max_value<'0.99' ";
                    $total_data = Db::query($total_sql);
                    $total=(int)$total_data[0]['count_tick'];
                    $sql="select (mint_value/max_value*100) as mint_progress,max_value,mint_value,tick_value,start_timestamp,mint_address 
                          from brc20_progress 
                          where max_value!=mint_value and max_value>0 and mint_value>10  and mint_value/max_value<'0.99' order by mint_progress desc 
                          limit $limit, 10";
                    $mint_data = Db::query($sql);
                    foreach ($mint_data as $keys=>$vals){
                        $res_data[$keys]['tick_name']=$vals['tick_value'];
                        $res_data[$keys]['deployment_time']=date('Y/m/d H:i:s',$vals['start_timestamp']);
                        $res_data[$keys]['cast_number']=$vals['mint_address'];
                        $res_data[$keys]['schedule']=substr($vals['mint_progress'], 0, 5).'%';
                    }
                    break;
                case 3:
                    $total_sql="select count(tick_value)as count_tick  from brc20_progress where  max_value>0 and mint_value>0 and mint_value/max_value>='0.99' ";
                    $total_data = Db::query($total_sql);
                    $total=(int)$total_data[0]['count_tick'];
                    $sql="select max_value,mint_value,tick_value,start_timestamp,mint_address 
                          from brc20_progress 
                          where   max_value>0 and mint_value>0   and mint_value/max_value>='0.99'
                          limit $limit, 10";
                    $mint_data = Db::query($sql);
                    foreach ($mint_data as $keys=>$vals){
                        $res_data[$keys]['tick_name']=$vals['tick_value'];
                        $res_data[$keys]['deployment_time']=date('Y/m/d H:i:s',$vals['start_timestamp']);
                        $res_data[$keys]['cast_number']=$vals['mint_address'];
                        $res_data[$keys]['schedule']='100%';
                    }
                    break;
                case 4:
                    $total_sql="select count(tick_value)as count_tick  from brc20_progress";
                    $total_data = Db::query($total_sql);
                    $total=(int)$total_data[0]['count_tick'];
                    $sql="select (mint_value/max_value*100) as mint_progress,max_value,mint_value,tick_value,start_timestamp,mint_address 
                          from brc20_progress                           
                          limit $limit, 10";
                    $mint_data = Db::query($sql);
                    foreach ($mint_data as $keys=>$vals){
                        $res_data[$keys]['tick_name']=$vals['tick_value'];
                        $res_data[$keys]['deployment_time']=date('Y/m/d H:i:s',$vals['start_timestamp']);
                        $res_data[$keys]['cast_number']=$vals['mint_address'];
                        if($vals['mint_progress']>='99'){
                            $res_data[$keys]['schedule']='100%';
                        }else {
                            $res_data[$keys]['schedule'] = substr($vals['mint_progress'], 0, 5) . '%';
                        }
                    }
                    break;
                default:
            }
        }
        $res['list']=array_values($res_data);
        $res['total']=$total;
        $res['page_total']=$total/10;
        return $this->success($res);
    }


}
