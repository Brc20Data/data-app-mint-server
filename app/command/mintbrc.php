<?php
declare (strict_types = 1);

namespace app\command;

use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class mintbrc extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('mintbrc')
            ->setDescription('the mintbrc command');
    }

    /**
     * 热门min
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    protected function execute(Input $input, Output $output)
    {
        $cache_key='crontab:mint:data';
        $redis = new Redis();
        $sql="select tick_value,count(tick_value)as times from brc20_mint_records WHERE block_height > 790682 - 10
            GROUP BY tick_value ORDER BY times DESC LIMIT 10";
        $data_list = Db::query($sql);
        if($data_list){
            $tick_value=[];
            foreach ($data_list as $key=>$val){
                $tick_value[$key]['tick_value']=$val['tick_value'];
                $tick_value[$key]['times']=$val['times'];
            }
            $tick_value=array_values($tick_value);
            $redis->set($cache_key,json_encode($tick_value));
        }
        $output->writeln(date("Y-m-d H:i:s"));
    }
}
