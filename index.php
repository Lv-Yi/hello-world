<?php
include_once('weixin.class.php');//引用刚定义的微信消息处理类
define("TOKEN", "arduinoyun");
$wechatObj = new wechatCallbackapiTest();
$wechatObj->responseMsg();
?>