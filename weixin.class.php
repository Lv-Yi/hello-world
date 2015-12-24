<?php
class wechatCallbackapiTest
{
    # This function reads your DATABASE_URL config var and returns a connection
    # string suitable for pg_connect.
    private function pg_conn_string() {
        extract(parse_url($_ENV["DATABASE_URL"]));
        return "host=$host port=$port user=$user password=$pass dbname=" . substr($path, 1); # <- you may want to add sslmode=require there too
        
        //create a connection string from the PG database URL and then use it to connect
        /*
        $url=parse_url(getenv("DATABASE_URL"));
        $host = $url["host"];
        $port = $url["port"];
        $user = $url["user"];
        $password = $url["pass"];
        $dbname = substr($url["path"],1);
        $connect_string = "host='" . $host . "' ";
        $connect_string = $connect_string . "port=" . $port . " ";
        $connect_string = $connect_string . "user='" . $user . "' ";
        $connect_string = $connect_string . "password='" . $password . "' ";
        $connect_string = $connect_string . "dbname='" . $dbname . "' ";
        
        return $connect_string;*/
    }
    
    # Get temperature value from postgresql db.
    private function pg_get_temperature() {
        # connect to postgresql db
        $con = pg_connect(self::pg_conn_string());
        if ($con) {
            $result = pg_query($con, "SELECT * FROM sensor") or die('Query failed: ' . pg_last_error());;
            while($arr = pg_fetch_array($result)){
                if ($arr['id'] == 1) {
                    $tempr = $arr['data'];
                    $retMsg .= "报告大王："."\n"."主人房间的室温为".$tempr."℃，感谢您对主人的关心";
                    break;
                }
            }
        } else {
            $retMsg = "出错了(001)！！！";
        }
        pg_free_result($result);
        pg_close($con);
        return $retMsg;
    }
    
    # Get existing wx access token stored in postgresql and check if expire
    private function pg_get_wx_config_all() {
        $con = pg_connect(self::pg_conn_string());
        if ($con) {
            $result = pg_query($con, "select *, extract(epoch from (now()-access_token_timestamp)) >= (access_token_expires_in - 30) as is_at_expired from wx_config");
            if ($result) {
                while($arr = pg_fetch_array($result)){
                    if ($arr['id'] == 1) {
                        // only id = 1 row is valid record.
                        $ret = $arr;
                        break;
                    }
                }
            } else {
                echo '出错了(002)！！！';
                exit;
            }
         } else {
            echo '出错了(003)！！！';
            exit;
        }
        pg_free_result($result);
        pg_close($con);
        return $ret;
    }

    # Get a valid access_token
    # If the stored access_token is still valid, use it; Or request a new one and store in DB.
    private function pg_get_wx_access_token() {
        $con = pg_connect(self::pg_conn_string());
        // TODO :
    }

    # Upload pic to weixin and get its media_id; Store the id into DB.
    # Note: the URL of the pic should be accessible from weixin!
    private function pg_upload_wx_pic() {
        // TODO: 
    }

    # Get IP:Port address for given id.
    private function pg_get_url() {
        // TODO: get video url per id
    }
    
    public function responseMsg()
    {
        # correct timezone at very beginning
        date_default_timezone_set('prc');

        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $keyword = trim($postObj->Content);
            $time = time();
            $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";
            $picRpl = "<xml>
                       <ToUserName><![CDATA[%s]]></ToUserName>
                       <FromUserName><![CDATA[%s]]></FromUserName>
                       <CreateTime>%s</CreateTime>
                       <MsgType><![CDATA[%s]]></MsgType>
                       <Image>
                       <MediaId><![CDATA[%s]]></MediaId>
                       </Image>
                       </xml>";

            if($keyword == "?")
            {
                $msgType = "text";
                $contentStr = date("Y-m-d H:i:s: ",time());
                $contentStr .= self::pg_get_temperature();
            } else if ($keyword == "#")
            {
                $msgType = "text";
                $contentStr = date("Y-m-d H:i:s: ",time());
                $arr_config = self::pg_get_wx_config_all();
                $contentStr .= "id: ". $arr_config['id']. "; app_id: " . $arr_config['app_id'];
                $contentStr .= "; access_token: " . $arr_config['access_token'];
                $contentStr .= "; access_token_timestamp: " . $arr_config['access_token_timestamp'];
                $contentStr .= "; access_token expires in: " . $arr_config['access_token_expires_in'];
                $contentStr .= "; host_ext_ip: " . $arr_config['host_ext_ip'];
                $contentStr .= "; is_at_expired: " . $arr_config['is_at_expired'];
                if ($arr_config['is_at_expired'] == 't') {
                    $contentStr .= "; A.T expired";
                } else {
                    $contentStr .= "; A.T is not expired";
                }

                # check wx access_token timestamp
                //if (self::isWXAccessTokenExpire()) {}

            } else if ($keyword == "~")
            {
                $msgType = "image";
                $textTpl = $picRpl;
                $media_id = "msBC3R-gpoajjxMncY8s1Ryw26xqTB467RWlR3ta6cGFbZj-kU1UPBR4-hpLK7cJ";
                $contentStr = $media_id;
            } else if ($keyword == "!")
            {
                $msgType = "text";
                $contentStr = date("Y-m-d H:i:s: ",time());
                $arr_config = self::pg_get_wx_config_all();
                //$contentStr .= self::pg_get_temperature();
                $contentStr .= "http://".$arr_config['host_ext_ip'].":8112/";
            } 
            $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
            echo $resultStr;
            return;
        }else{
            echo "";
            exit;
        }
    }
}
?>