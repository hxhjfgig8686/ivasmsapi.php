<?php

/* =========================
   إعدادات
========================= */

$API_KEY = "sk_ef44bbb0c8f04a621c056c44a0d75848869f65f63a2c4ef6d4e727ee9844602c";

$email = "hiahemdhh@gmail.com";
$password = "Hh1234Hh";

$login_url = "https://www.ivasms.com/login";
$sms_url   = "https://www.ivasms.com/portal/sms/received";

$cookie_file = "session_cookie.txt";
$cache_file  = "sms_cache.json";

/* =========================
   حماية API
========================= */

if(!isset($_GET['key']) || $_GET['key'] !== $API_KEY){

    echo json_encode([
        "success"=>false,
        "error"=>"invalid api key"
    ]);

    exit;
}

/* =========================
   CURL Request
========================= */

function request($url,$cookie,$post=false,$data=null){

    $ch = curl_init();

    curl_setopt_array($ch,[
        CURLOPT_URL=>$url,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_COOKIEJAR=>$cookie,
        CURLOPT_COOKIEFILE=>$cookie,
        CURLOPT_USERAGENT=>"Mozilla/5.0",
        CURLOPT_FOLLOWLOCATION=>true
    ]);

    if($post){
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    }

    $result = curl_exec($ch);

    curl_close($ch);

    return $result;
}

/* =========================
   تسجيل الدخول
========================= */

function login($email,$password,$login_url,$cookie){

    $page = request($login_url,$cookie);

    preg_match('/name="_token" value="(.*?)"/',$page,$m);

    $token = $m[1] ?? "";

    $data = http_build_query([
        "email"=>$email,
        "password"=>$password,
        "_token"=>$token
    ]);

    request($login_url,$cookie,true,$data);
}

/* =========================
   استخراج الرسائل
========================= */

function parse_sms($html){

    $messages = [];

    preg_match_all('/<tr.*?>(.*?)<\/tr>/s',$html,$rows);

    foreach($rows[1] as $row){

        preg_match('/<span.*?>(.*?)<\/span>/',$row,$sender);
        preg_match('/class="message".*?>(.*?)<\/td>/s',$row,$msg);
        preg_match('/class="time".*?>(.*?)<\/td>/',$row,$time);

        $message = trim(strip_tags($msg[1] ?? ""));

        preg_match('/\b\d{4,8}\b/',$message,$otp);

        if(!empty($otp)){

            $messages[]=[
                "sender"=>trim(strip_tags($sender[1] ?? "")),
                "message"=>$message,
                "otp"=>$otp[0],
                "time"=>trim(strip_tags($time[1] ?? "")),
                "timestamp"=>time()
            ];
        }
    }

    return $messages;
}

/* =========================
   Cache
========================= */

function load_cache($file){

    if(file_exists($file))
        return json_decode(file_get_contents($file),true);

    return [];
}

function save_cache($file,$data){

    file_put_contents($file,json_encode($data));
}

/* =========================
   تشغيل النظام
========================= */

if(!file_exists($cookie_file)){

    login($email,$password,$login_url,$cookie_file);

}

$html = request($sms_url,$cookie_file);

$sms_list = parse_sms($html);

$cache = load_cache($cache_file);

$new_sms = [];

foreach($sms_list as $sms){

    if(!in_array($sms["otp"],$cache)){

        $new_sms[] = $sms;
        $cache[] = $sms["otp"];
    }
}

save_cache($cache_file,$cache);

/* =========================
   JSON OUTPUT
========================= */

header("Content-Type: application/json");

echo json_encode([
    "success"=>true,
    "count"=>count($new_sms),
    "data"=>$new_sms
],JSON_PRETTY_PRINT);
