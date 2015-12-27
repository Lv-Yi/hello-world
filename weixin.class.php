<?php
class wechatCallbackapiTest
{
    # constent var
    const wx_url_req_new_at = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential'; //&appid=APPID&secret=APPSECRET';
    const wx_url_upload_temp_pic = 'https://api.weixin.qq.com/cgi-bin/media/upload?';   //access_token=ACCESS_TOKEN&type=TYPE';

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

    # CURL get HTTPS content
    private function curl_get_https($https_url) {
        // get https content by curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $https_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $info = curl_exec($ch);
        // 关闭cURL资源，并且释放系统资源
        curl_close($ch);
        return $info;
    }

    # CURL get HTTP content
    private function curl_get_http($http_url) {
        // get http content by curl
        //$info = "http_url: " . $http_url;        return $info;
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_URL, $http_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_TIMEOUT,60000);
        ob_start();
        curl_exec($ch);
        $info = ob_get_contents();
        ob_end_clean();
        // 关闭cURL资源，并且释放系统资源
        curl_close($ch);
        // $return_code = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
        return $info;
    }

    # Get a valid access_token
    # If the stored access_token is still valid, use it; Or request a new one and store in DB.
    private function pg_get_wx_access_token() {
        $contentStr = date("Y-m-d H:i:s: ",time());
        //$arr_config = self::pg_get_wx_config_all();
        $con = pg_connect(self::pg_conn_string());
        if ($con) {
            $result = pg_query($con, "SELECT *, extract(epoch from (now()-access_token_timestamp)) >= (access_token_expires_in - 30) as is_at_expired FROM wx_config");
            if ($result) {
                while($arr = pg_fetch_array($result)){
                    if ($arr['id'] == 1) {
                        // only id = 1 row is valid record.
                        $ret = $arr;
                        break;
                    }
                }
                // check a.t. expired or not
                if ($ret['is_at_expired'] == 't') {
                    // at expired, get a new one
                    //$ret = self::wx_url_req_new_at . '&appid=' . $ret['app_id'] . '&secret=' . $ret['app_secret'];
                    //$ret = self::curl_get_https(self::wx_url_req_new_at . '&appid=' . $ret['app_id'] . '&secret=' . $ret['app_secret']);
                    //return $ret;
                    $at_resp = json_decode(self::curl_get_https(self::wx_url_req_new_at . '&appid=' . $ret['app_id'] . '&secret=' . $ret['app_secret']));   //&appid=APPID&secret=APPSECRET';
                    if (!array_key_exists("errcode", $at_resp)) {
                        //$ret = $at_resp->{'expires_in'};
                        //return $ret;
                        // got new access token
                        $result1 = pg_query($con, "UPDATE wx_config SET access_token='" . $at_resp->{'access_token'} . "', access_token_timestamp=now(), access_token_expires_in=" . $at_resp->{'expires_in'} . " WHERE id=1");
                        if ($result1) {
                            // new a.t. saved to db
                            $ret = $at_resp->{'access_token'};
                        } else {
                            echo '出错了(002)！！！';
                            exit;
                        }
                    }
                    //$access_token = $at_resp['']
                    //$ret .= $at_resp;
                } else {
                    $ret = $arr['access_token'];
                }
                // should go to return
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

    # Upload pic to weixin and get its media_id; Store the id into DB.
    # Note: the URL of the pic should be accessible from weixin!
    private function curl_upload_wx_pic($pic_url) {
        $ret = NULL;
        // TODO: 
        if (isset($pic_url)) {
            // input valid
            

            //====================================================
            /*
            $url = $pic_url;
            //去除URL连接上面可能的引号 
            $url = preg_replace( '/(?:^[\'"]+|[\'"\/]+$)/', '', $url ); 
            if (!extension_loaded('sockets')) exit; 
            //获取url各相关信息 
            preg_match( '/http:\/\/([^\/\:]+(\:\d{1,5})?)(.*)/i', $url, $matches ); 
            if (!$matches) exit;//return false; 
            //return substr($matches[2], 1 );
            $sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP ); 
            if ( !@socket_connect( $sock, $matches[1], $matches[2] ? substr($matches[2], 1 ) : 80 ) ) { 
                exit;
                //return false; 
            } 
            //图片的相对地址 
            $msg = 'GET ' . $matches[3] . " HTTP/1.1\r\n"; 
            //主机名称 
            $msg .= 'Host: ' . $matches[1] . "\r\n"; 
            $msg .= 'Connection: Close' . "\r\n\r\n"; 
            socket_write( $sock, $msg ); 
            $bin = '';
            $tmp_cnt = 0;
            while ( $tmp = socket_read( $sock, 10 ) ) { 
                $bin .= $tmp;
                $tmp_cnt = $tmp_cnt + count($tmp, COUNT_RECURSIVE);
                $tmp = '';
            }
            $bin = explode("\r\n\r\n", $bin); 
            $img = $bin[1]; 
            @socket_close( $sock ); 
            $pic_data = $img;
            //----------------------------------------------------
            */
            //$pic_data = self::curl_get_http($pic_url);

            // test: curl remote jpg file and save to local file first
            $pic_data = file_get_contents($pic_url);
            $fp = fopen('shot.jpg','w+');
            fwrite($fp,$pic_data);
            $save_file = realpath('shot.jpg');
            return $save_file;
            $pic_data = array("media" => "@".$save_file);
            //$pic_data['media'] = new CurlFile(@pic_url, 'image/jpg');
            //$pic_data = array("media" => $pic_data);
            //print_r($pic_data);
            //return $pic_data;
            //return strlen($pic_data);
            $access_token = self::pg_get_wx_access_token();
            $url = self::wx_url_upload_temp_pic . "access_token=" . $access_token . "&type=image";   //access_token=ACCESS_TOKEN&type=TYPE';
            //$ret = $url;
            //$ret = count($pic_data, COUNT_RECURSIVE); return $ret . ", " . count($pic_data[0], COUNT_RECURSIVE);
            //return $tmp_cnt . ", " . count($bin[0], COUNT_RECURSIVE) . ", " . count($bin[1], COUNT_RECURSIVE);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $pic_data);
            //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            $result = curl_exec($ch);
            curl_close($ch);
            //$ret = $result;
            //return $ret;
            $result = json_decode($result, true);
            //$result->url;//即为上传图片的URL;
            if (array_key_exists("media_id", $result)) {
                // got media_id
                $ret = $result->{"media_id"};
            } else {
                $ret = "invalid media_id";
            }
        } else {
            // should not come here, input error
            echo '出错了(201)！！！';
            exit;
        }
        return $ret;
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
                //$contentStr .= "id: ". $arr_config['id']. "; app_id: " . $arr_config['app_id'];
                //$contentStr .= "; access_token: " . $arr_config['access_token'];
                /*
                $contentStr .= "; access_token_timestamp: " . $arr_config['access_token_timestamp'];
                $contentStr .= "; access_token expires in: " . $arr_config['access_token_expires_in'];
                $contentStr .= "; host_ext_ip: " . $arr_config['host_ext_ip'];
                $contentStr .= "; is_at_expired: " . $arr_config['is_at_expired'];
                if ($arr_config['is_at_expired'] == 't') {
                    $contentStr .= "; A.T expired!";
                    $contentStr .= " The new a.t. is " . self::pg_get_wx_access_token();
                } else {
                    $contentStr .= "; A.T is not expired.";
                    $contentStr .= " Still use the existing A.T. " . self::pg_get_wx_access_token();
                }*/
                // try to upload pic
                //$contentStr .= ", upload pic url: " . "http://".$arr_config['host_ext_ip'].":8112/shot.jpg";
                //$contentStr .= self::curl_upload_wx_pic("http://".$arr_config['host_ext_ip'].":8112/shot.jpg");
                $contentStr .= self::curl_upload_wx_pic("http://ooopic.assetsdelivery.com/168nwm/carodi/carodi1011/carodi101100034.jpg");

            } else if ($keyword == "~")
            {
                $msgType = "image";
                $textTpl = $picRpl;
                $arr_config = self::pg_get_wx_config_all();
                $media_id = self::curl_upload_wx_pic("http://ooopic.assetsdelivery.com/168nwm/carodi/carodi1011/carodi101100034.jpg");
                //$media_id = self::curl_upload_wx_pic(self::pg_get_wx_access_token(), "http://".$arr_config['host_ext_ip'].":8112/shot.jpg");
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