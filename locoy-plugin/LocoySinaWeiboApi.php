<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
/*
*外部编程接口处理标签内容示范文件	,火车头插件																										
*该文件内自动系统的三个参数$LabelArray $LabelCookie,$LabelUrl
*对任意采集的标签都适用请对标签内容处理后直接将该数组serialize($LabelArray)输出，
*采集器内部即可接收到该标签的内容，对比以前的接口规则，新规则可以实现标签之间的数据调用和处理														
*参数说明：																																			
  *$LabelArray    -  标签名及标签内容集合 结构如：Array('栏目id' => 2,'出处'=>  '新浪微博','内容'=>'<center><b>暴笑短信')  ##
  *$LabelCookie   -  对应采集中用到的Cookie值							
  *$LabelUrl      -  当前采集的页面的Url地址 
  * 特别注意:如果是处理列表页,默认页,多页时会有以下两个标签
    $LabelArray['Html']       网页的源代码,没有经过采集器处理的,直接下载后的数据.修改这里的数据,请将新值赋予$LabelArray['Html']
    $LabelArray['PageType']   值可能为 List, Pages, Content 分别代表处理列表页,多页,默认页																				
*以上语句建议不更改,以下为用户操作区域  该区域只限对数组值进行操作，不得有打印输出产生，不得直接增加或删除相应标签名
*/


/**
 * Created by Evlon.
 * User: evlon
 * Date: 2019/3/18
 * Time: 1:39
 */


class Config{
    function __construct($file)
    {
        $this->configFile = $file;
        $this->reload();
    }

    public function reload()
    {
        $configText=  file_get_contents($this->configFile);
		$this->configText = $configText;
		
        $this->config = json_decode($configText,true);
		

    }
	
	public function getConfig(){
		return $this->configText;
	}

    public function get($key)
    {

        return $this->config[$key];
    }

    public function set($key,$val)
    {
        return $this->config[$key] = $val;
    }

    public function save(){

		$text = json_encode($this->config);
		//echo($text);
        file_put_contents($this->configFile,$text);
    }
 
    function __destruct()
    {
        $this->connection=null;
    }
};


//$SinaConfig = new Config(__DIR__."/SinaConfig.json");
// echo($SinaConfig->getConfig());
// echo($SinaConfig->get("SinaUser"));
// echo("SinaCookie:".$SinaConfig->get("SinaCookie"));
// $SinaConfig->set("SinaCookie","111111");
// echo($SinaConfig->get("SinaCookie"));
// $SinaConfig->save();

SinaApi::$SinaConfig = new Config(__DIR__."/SinaConfig.json");

//新浪图床API，需要登陆授权
class SinaApi{
	static $SinaConfig;
    public static function Upload($img) {

		$SinaConfig = self::$SinaConfig;
		
        $url = "http://picupload.weibo.com/interface/pic_upload.php?cb=https%3A%2F%2Fweibo.com%2Faj%2Fstatic%2Fupimgback.html%3F_wv%3D5%26callback%3DSTK_ijax_1551096206285100&mime=image%2Fjpeg&data=base64&url=weibo.com%2Fu%2F5734329255&markpos=1&logo=1&nick=&marks=0&app=miniblog&s=rdxt&pri=0&file_source=2";


        $post='b64_data='.urlencode(base64_encode($img));

        if(!$SinaConfig->get("SinaCookie")){
			//echo("login.......");
            self::sinaLogin($SinaConfig->get("SinaUser"),$SinaConfig->get("SinaPass"));
        }
		each($SinaConfig);
        if (!$SinaConfig->get("SinaUpdateTime")){
            $SinaConfig->set("SinaUpdateTime",time());
            $SinaConfig->save();
        }
        $UpdateTime = time() - $SinaConfig->get("SinaUpdateTime");
        //1小时自动更新一次cookie
		//echo("updatetime:".$UpdateTime);
        if($UpdateTime > 3600){
            self::sinaLogin($SinaConfig->get("SinaUser"),$SinaConfig->get("SinaPass"));
        }

        // Curl提交
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_VERBOSE => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array("Cookie:" . $SinaConfig->get("SinaCookie")),
            CURLOPT_POSTFIELDS => $post,
        ));
        $output = curl_exec($ch);
        curl_close($ch);

        $pid = self::getSubstr($output,"pid=","\r");

        if($pid=="") {
            return array("code"=>"-1","msg"=>"服务器繁忙","img"=>null);
        }
        $size = 0;      //图片尺寸 0-7(数字越大尺寸越大)
        $https = true;  //是否使用 https 协议
        $sizeArr = array('large', 'mw1024', 'mw690', 'bmiddle', 'small', 'thumb180', 'thumbnail', 'square');
        $pid = trim($pid);
        $size = $sizeArr[$size];

        if (preg_match('/^[a-zA-Z0-9]{32}$/', $pid) === 1) {
            $imgUrl =  ($https ? 'https' : 'http') . '://' . ($https ? 'ws' : 'ww')
                . ((crc32($pid) & 3) + 1) . ".sinaimg.cn/" . $size
                . "/$pid." . ($pid[21] === 'g' ? 'gif' : 'jpg');
        }else{
            $url = $pid;
            $imgUrl = preg_replace_callback('/^(https?:\/\/[a-z]{2}\d\.sinaimg\.cn\/)'
                . '(large|bmiddle|mw1024|mw690|small|square|thumb180|thumbnail)'
                . '(\/[a-z0-9]{32}\.(jpg|gif))$/i', function ($match) use ($size) {
                return $match[1] . $size . $match[3];
            }, $url, -1, $count);
            if ($count === 0) {
                $imgUrl = '';
            }
        }

        return array("code"=>"1","msg"=>"上传成功","img"=>$imgUrl);

    }


    public static function sinaLogin($u,$p){
		$SinaConfig = self::$SinaConfig;
		
        $url = 'https://login.sina.com.cn/sso/login.php?client=ssologin.js(v1.4.15)&_=';
        $post = 'entry=sso&gateway=1&from=null&savestate=30&useticket=0&pagerefer=&vsnf=1'
               .'&su='.base64_encode($u).'&service=sso&sp='.$p
               .'&sr=1024*768&encoding=UTF-8&cdult=3&domain=sina.com.cn&prelt=0&returntype=TEXT';

        $ret = self::sendPost($url,$post);
        $tmp = explode("\n\r",$ret);
        $res = json_decode($tmp[1]);
        if ($res->retcode=="0"){
            $cookie = 'SUB' . self::getSubstr($tmp[0],"Set-Cookie: SUB",'; ') . ';';
            $SinaConfig->set("SinaCookie", $cookie);
			$SinaConfig->set("SinaUpdateTime",time());
            $SinaConfig->save();
            return "登陆成功";
        }else{
            return "登陆失败";
        }



    }


    private static function sendPost($url,$data){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_HEADER,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        $return = curl_exec($ch);
        curl_close($ch);
        return $return;
    }


    public static function getSubstr($str,$leftStr,$rightStr){
        $left = strpos($str, $leftStr);
        //echo '左边:'.$left;
        $right = strpos($str, $rightStr,$left);
        //echo '<br>右边:'.$right;
        if($left <= 0 or $right < $left) return '';
        return substr($str, $left + strlen($leftStr), $right-$left-strlen($leftStr));
    }
}

function GetRemoteImage($url,$referer){
    $ch = curl_init($url); 
    curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36');
    curl_setopt($ch, CURLOPT_REFERER, $referer); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出

    // curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC); //代理认证模式  
    // curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1"); //代理服务器地址  
    // curl_setopt($ch, CURLOPT_PROXYPORT, 8888); //代理服务器端口

    $FH= curl_exec($ch);
    curl_close($ch);
    return $FH;
}

function ImageApiUpload($file,$referer){
    if((strpos($file,"http://") === 0) or (strpos($file,"https://")===0)){
        $img = GetRemoteImage($file,$referer);
    }
    else{
        $fp = fopen($file,"r");
        $img = fread($fp,filesize($file));
        fclose($fp);
    }

    #$img = base64_decode($img);
    if(empty($img)){
        return array("code"=>"0","msg"=>"无数据","img"=>$result);

    }

    $result = SinaApi::Upload($img);
    return $result;

}

if($LabelArray['Html'])
{
	$LabelArray['Html']='当前页面的网址为:'.$LabelUrl."\r\n页面类型为:".$LabelArray['PageType']."\r\nCookies数据为:$LabelCookie\r\n接收到的数据是:".$LabelArray['Html'];
}
else
{
	
    $biaoqianFields = $LabelArray["图床图片"];
    $biaoqianDownImageReferer = $LabelArray["图床图片_引用地址"];
    $biaoqianDownImageSpan = $LabelArray["图床图片_地址分隔符"];
    $LabelArray['图床图片日志'] = '';
    if(!empty($biaoqianFields)){
        $biaoqianArray = explode(',',$biaoqianFields);

        foreach($biaoqianArray as $fileBiaoqian){
            $file = $LabelArray[$fileBiaoqian];
            
            if(!empty($file)){
                if(empty($biaoqianDownImageSpan)){

                    $result = ImageApiUpload($file,$biaoqianDownImageReferer);
                    $LabelArray['图床图片日志'] = $LabelArray['图床图片日志'].serialize($result);
                    #$LabelArray['图床图片日志'] = $result["imgBase64"];
                    if(($result["code"] === '1') and !empty($result["img"])){
                        $LabelArray[$fileBiaoqian] = $result['img'];
                    }
                }
                else{
                    $files = explode($biaoqianDownImageSpan,$file);
                    $resultFiles = '';
                    foreach($files as $f){
                        $result = SougouApiUpload($f,$biaoqianDownImageReferer);
                        $LabelArray['图床图片日志'] = $LabelArray['图床图片日志'].$result['msg'];
                        #$LabelArray['图床图片日志'] = $result["imgBase64"];
                        if(($result["code"] === '1') and !empty($result["img"])){
                            $resultFiles = $resultFiles.$biaoqianDownImageSpan.$result["img"];
                        }
                        else{
                            $resultFiles = $resultFiles.$biaoqianDownImageSpan.$f;
                        }
                    }
                    $resultFiles = substr($resultFiles,1);
                    $LabelArray[$fileBiaoqian] = $resultFiles;
                }
            }
        }
    }	
	
	#isset($LabelArray['内容']) && $LabelArray['内容'] = $LabelArray['标题'].$LabelArray['内容'];  //★★★★★★注意这句。V2009SP2版后可实现多标签之间的相互调用★★★★★★
	#isset($LabelArray['内容']) && $LabelArray['内容'] = str_replace('旧字符串','新字符串',$LabelArray['内容']); //简单替换一下

	#isset($LabelArray['标题']) && $LabelArray['标题'] =  '【给标题标签加个前缀】'.$LabelArray['标题'];

	#isset($LabelArray['时间']) && $LabelArray['时间'] =date('Y-m-d H:i:s',time()); //不用标签内容，直接获取time()函数得到的当前时间，用Y-m-d H:i:s格式输出，如2008-05-28 00:12:23
}
//#############以上为用户操作区域#############################################################################################################################
//#############以下语句必须保留，建议不更改###################################################################################################################
//ob_clean();
echo serialize($LabelArray);
?> 