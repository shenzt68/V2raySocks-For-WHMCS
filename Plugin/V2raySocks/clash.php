<?php
require(dirname(dirname(dirname(dirname(__FILE__)))).'/init.php');
use WHMCS\Database\Capsule;
if(isset($_GET['sid']) && isset($_GET['token'])){
	$sid = $_GET['sid'];
	$token = $_GET['token'];
	$service = \WHMCS\Database\Capsule::table('tblhosting')->where('id', $sid)->where('username', $token)->first();
	if (empty($service)){
		die('Unisset or Uncorrect Token');
	}
	if ($service->domainstatus != 'Active' ) {
        die('Not Active');
    }
	$package = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
	$server = Capsule::table('tblservers')->where('id', $service->server)->first();

	$dbhost = $server->ipaddress ? $server->ipaddress : 'localhost';
	$dbname = $package->configoption1;
	$dbuser = $server->username;
	$dbpass = decrypt($server->password);
	$db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
	$usage = $db->prepare('SELECT * FROM `user` WHERE `sid` = :sid');
	$usage->bindValue(':sid', $sid);
	$usage->execute();
	$usage = $usage->fetch();
	$servers = $package->configoption4;
    if($servers == ""){
        $servers = \WHMCS\Database\Capsule::table('tblservers')->where('id', $service->server)->get();
        $servers = V2raySocks_OS_QueryToArray($servers);
        $servers = $servers[0]['assignedips'];
    }
	$noder = explode("\n",$servers);
	$ProxyContent = "";
    $i = 0;
	foreach($noder as $nodee){
		$nodee = explode('|', $nodee);
        $remark[$i] = trim($nodee[0]);
		$ProxyContent .= make_vmess($nodee,$usage['uuid']) . PHP_EOL;
        $i++;
	}

    $groupName = trim($package->name);
    //$groupName = trim("Proxyed");

    $ProxyGroup = json_encode($remark, JSON_UNESCAPED_UNICODE);
    $ProxyGroup = str_replace("{", "", $ProxyGroup);
    $ProxyGroup = str_replace("}", "", $ProxyGroup);
    $ProxyGroup = str_replace("[", "", $ProxyGroup);
    $ProxyGroup = str_replace("]", "", $ProxyGroup);
    $ProxyGroup = str_replace(",", ", ", $ProxyGroup);
    if( file_exists(__DIR__ . "/clash.yml") ) 
    {
        $ClashConf = file_get_contents(__DIR__ . "/clash.yml");
    }
    if( empty($ClashConf) ) 
    {
        throw new \Exception("无法读取Clash配置文件", 301);
    }

    $ClashConf = str_replace("{\$Proxy}", $ProxyContent, $ClashConf);
    $ClashConf = str_replace("{\$proxies}", $ProxyGroup, $ClashConf);
    $ClashConf = str_replace("{\$groupname}", $groupName, $ClashConf);
    if( preg_match("/clash/", $_SERVER["HTTP_USER_AGENT"]) ) 
    {

        header("Content-type:application/octet-stream; charset=utf-8");
        header("Content-Disposition: attachment; filename=" . $groupName);
    }
    else
    {
        if( $_REQUEST["dl"] == "true" ) 
        {
            header("Content-type:application/octet-stream; charset=utf-8");
            header("Content-Disposition: attachment; filename=config.yml");
        }
        else
        {
            header("Content-Type: text/plain; charset=utf-8");
        }

    }

    exit( $ClashConf );

}else{
	die('Invaild');
}

function V2raySocks_OS_QueryToArray($query){
    $products = array();
    foreach ($query as $product) {
        $producta = array();
        foreach($product as $k => $produc){
            $producta[$k] = $produc;
        }
        $products[] = $producta;
    }
    return $products;
}

function make_vmess($nodee,$uuid){
    $ProxyContent = "- { name: \"" . trim($nodee[0]) . "\",";
    $ProxyContent .= "type: vmess,";
    $ProxyContent .= "server: " . $nodee[1] . ",";
    $ProxyContent .= "port: " . $nodee[2] . ",";
    $ProxyContent .= "uuid: " . $uuid . ",";
    if ($nodee[9]){
        $ProxyContent .= "alterId: " . intval($nodee[9]) . ",";
    }else{
        $ProxyContent .= "alterId: 64,";
    }
    $ProxyContent .= "cipher: auto";
    if($nodee[7] != "tcp"){
        $ProxyContent .= ", network: " . $nodee[7];
    }
    if($nodee[6]){
        $ProxyContent .= ", ws-path: " . $nodee[6];
    }
    if($nodee[5]){
        $ProxyContent .= ", ws-headers: { Host: " . $nodee[5] . " }";
    }
    if($nodee[4]){
        $ProxyContent .= ", tls: true";
    }
    $ProxyContent .= "}";

    return $ProxyContent;  
}