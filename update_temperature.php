<?php

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
            $retMsg = "出错了(101)！！！";
        }
        pg_free_result($result);
        pg_close($con);
        return $retMsg;
    }

    # Get temperature value from postgresql db.
    private function pg_set_temperature($id, $cur_temperature) {
        # connect to postgresql db
        $con = pg_connect(self::pg_conn_string());
        if ($con) {
        	$result = pg_query($con, "SELECT * FROM sensor where id = $id") or die('Query failed: ' . pg_last_error());;
            while($arr = pg_fetch_array($result)){
                if ($arr['id'] == $id) {
                    $tempr = $arr['data'];
                    $result = pg_query($con, "UPDATE sensor SET update_timestamp = now(), data = $cur_temperature where id = $id") or die('Query failed: ' . pg_last_error());
                    $retMsg = "No.". $id . ": 温度值设定：" . $tempr . "摄氏度 ---> " . $cur_temperature . "摄氏度";
                    break;
                } else {
                    $retMsg = "出错了(102)！！！";
                }
            }            
            $retMsg .= "{".$state."}";
        } else {
            $retMsg = "出错了(103)！！！";
        }
        pg_free_result($result);
        pg_close($con);
        return $retMsg;
    }

if ($_GET['data'] && $_GET['id'] && ($_GET['token'] == "arduinoyun")) {//可以改token,这相当于密码，在Arduino端改成相应的值即可
	$data = $_GET['data'];
	$id = $_GET['id'];
	echo self::pg_set_temperature(1, 7);
}else{
	echo "Permission Denied";//请求中没有type或data或token或token错误时，显示Permission Denied
}
?>