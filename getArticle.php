<?php
/**
 * 通过关键字抓取文章
 * User: liuzhengyong
 * Date: 2016/10/31
 * Time: 17:33
 */
set_time_limit(120);    //设定为两分钟超时
header("Content-Type:text/html;charset=utf-8");
$type_arr = array(
    "mafengwo" =>  array(
                        "info"=>array(
                                    "title" =>  array("rule"=>"<h3>(.*?)<\/h3>","key"=>1),
                                    "author" =>  array("rule"=>"作者:(.*?)<\/a>","key"=>1),
                                    "time" =>  array("rule"=>"<li>\s+\d{4}年\d{1,2}月\d{1,2}日\s+<\/li>","key"=>0),
                                    "link"  =>  array("rule"=>"<h3>(.*?)href=\"(.*?)\"(.*?)<\/a>","key"=>2),
                                ),
                        "name"=>"马蜂窝",
                        "url"=> "http://www.mafengwo.cn/group/s.php?q=%E7%A7%9F%E8%BD%A6&p=yeshu&t=info&kt=1",
                    ),

    "xiecheng"  =>  array(
                        "info"=>array(
                                        "title" =>  array("rule"=>"<dt><a(.*?)<\/dt>","key"=>0),
                                        "author" =>  array("rule"=>'<dd class="color-999">\s+<a(.*?)>(.*?)<\/a>(.*?)<\/dd>',"key"=>2),
                                        "time" =>  array("rule"=>"<dd class=\"color-999\">(.*?)(\d{4}-\d{1,2}-\d{1,2})(.*?)<\/dd>","key"=>2),
                                        "link"  =>  array("rule"=>"<dt><a href=\"(.*?)\"(.*?)<\/dt>","key"=>1),
                                    ),
                        "name"=>"携程",
                        "url"=> "http://you.ctrip.com/searchsite/travels/?query=%E7%A7%9F%E8%BD%A6&isAnswered=&isRecommended=&publishDate=7&PageNo=yeshu",
                     ),
);

$param = array();
$t = $_INPUT['type'];
IF( !ISSET($type_arr[$t]) ){
    die("频道有错！");
}

$type = $type_arr[$t];
//马蜂窝只有50页
for ($i=1;$i<=51;$i++){
    $url = str_replace("yeshu",$i,$type['url']);
    $result = file_get_contents($url);
    $rs = array();
    foreach ($type['info'] as $k => $v){
        preg_match_all( '/'.$v['rule'].'/is', $result, $rs );
        $keys = $v['key'];
        if( isset($rs[$keys])  && !empty($rs[$keys]) ){
            foreach ($rs[$keys] as $key => $item){
                $param[$i][$key][$k] = trim(strip_tags($item));
            }
        }
    }//foreach
    
    //如果不是马蜂窝，监控到没有下一页的链接，跳出抓取
    if( $t != "mafengwo" ){
        if( !substr_count($result,">下一页</a>") ) {
            break;
        }
    }

}//for

$up_num = 0;
//携程的链接没有host，需要补全
$xiechenghost = "http://you.ctrip.com";
foreach ($param as $items){
    foreach ($items as $item){
        $item['time'] =  strtotime( preg_replace("/\D/","",$item['time']) );
        //过滤移动端的表情
        $item['title'] = preg_replace_callback('/[\xf0-\xf7].{3}/', function($r) { return '';}, $item['title']);
        $item['author'] = preg_replace_callback('/[\xf0-\xf7].{3}/', function($r) { return '';}, $item['author']);
        if( $t == "xiecheng" ){
            $item['link'] = $xiechenghost.$item['link'];
        }
        $item['from'] = $type['name'];
        //写入数据库
        $result = $db->insert($item);
        if(!$result) die("更新失败，请联系管理员");
        else if( is_numeric($result) ) $up_num++;
    }
}
echo "更新了{$up_num}篇文章！";
