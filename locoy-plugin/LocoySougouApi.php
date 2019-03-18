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

//图床图床API，免登陆

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

function SougouApiUpload($file,$referer){
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

    $data = base64_decode("LS0tLS0tV2ViS2l0Rm9ybUJvdW5kYXJ5R0xmR0IwSGdVTnRwVFQxaw0KQ29udGVudC1EaXNwb3NpdGlvbjogZm9ybS1kYXRhOyBuYW1lPSJwaWNfcGF0aCI7IGZpbGVuYW1lPSIxMS5wbmciDQpDb250ZW50LVR5cGU6IGltYWdlL3BuZw0KDQo=").$img.base64_decode("DQotLS0tLS1XZWJLaXRGb3JtQm91bmRhcnlHTGZHQjBIZ1VOdHBUVDFrLS0NCg==");

    $url = "http://pic.sogou.com/pic/upload_pic.jsp";


    $ch = curl_init();
    $headers=array(
        "Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryGLfGB0HgUNtpTT1k",
        "Content-Length: ".strlen($data)
    );

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


    $result=curl_exec($ch);
    curl_close($ch);
    return array("code"=>"1","msg"=>"上传成功","img"=>$result);

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

                    $result = SougouApiUpload($file,$biaoqianDownImageReferer);
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