<?php
    /*
		Рефакторинг и комментарии от AntifreeZZe
	*/
	
	//Если не определить, connect.php кинет ошибку
	define('INCLUDE_CHECK',true);
	
	//А вот и сам connect.php
	include("connect.php");
	include_once("ecodes.php");
	include_once("loger.php");
	include_once("security.php");
	
	//Ставим тип ответа и кодировку (UTF-8)
	header('Content-Type : text/plain; charset=utf-8'); 
	//parse_str($_SERVER['QUERY_STRING'],$_POST);
	//Получаем данные и расшифровываем
    $x  = rawurldecode($_POST['action']);
    //@$x = str_replace(" ", "+", $x);
    $yd = Security::decrypt($x, $key2);
	#echo $yd;
	
	//Парсим расшифрованное
	$json = json_decode($yd,true);
	$action = $json['action'];
	$client = $json['client'];
	$login = $json['login'];
	$postPass = $json['pass'];
	$launchermd5 = $json['md5'];
	//@list($action, $client, $login, $postPass, $launchermd5) = explode(':', $yd);

	//Проверяем соответствие лаунчера
    if($checklauncher)
    {
	    /*if($launchermd5 != null)
	    {
		    if($launchermd5 == @$md5launcherexe)
		    {
		       $check = "1";
		    }
		    if($launchermd5 == @$md5launcherjar)
		    {
		       $check = "1";
		    }
		}*/
		if(/*@$check !== "1"*/ $launchermd5 !== @$md5launcherexe and $launchermd5 !== @$md5launcherjar)
		{
			exit(Security::encrypt(json_encode(array("error"=>true, "code"=>STATUS_BAD_LAUNCHER,"text"=>"Outdated launcher")), $key1));
		}
	}

	if(!file_exists($uploaddirs)) die (Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,'text'=>'Skin upload directory is invalid')),$key1));
	if(!file_exists($uploaddirp)) die (Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,'text'=>'Cloak upload directory is invalid')),$key1));
	
	try {
	
	if (!preg_match("/^[a-zA-Z0-9_-]+$/", $login) || !preg_match("/^[a-zA-Z0-9_-]+$/", $action)) {
	
		exit(Security::encrypt(json_encode(array('error'=>true,'code'=>STATUS_BAD_LOGIN,'text'=>'Bad params',"file"=>__FILE__,"line"=>__LINE__,
			"login"=>$login,"pass"=>$postPass,"action"=>$action
		)), $key1));
    }	
	
	if($crypt === 'hash_md5' || $crypt === 'hash_authme' || $crypt === 'hash_xauth' || $crypt === 'hash_cauth' || $crypt === 'hash_joomla' || $crypt === 'hash_joomla_new' || $crypt === 'hash_wordpress' || $crypt === 'hash_dle' || $crypt === 'hash_launcher' || $crypt === 'hash_drupal' || $crypt === 'hash_imagecms')
	{
		$stmt = $db->prepare("SELECT $db_columnUser,$db_columnPass FROM $db_table WHERE $db_columnUser= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$stmt->bindColumn($db_columnPass, $realPass);
		$stmt->bindColumn($db_columnUser, $realUser);
		$stmt->fetch();
	} else if ($crypt === 'hash_ipb' || $crypt === 'hash_vbulletin' || $crypt === 'hash_punbb')
	{
		
		$stmt = $db->prepare("SELECT $db_columnUser,$db_columnPass,$db_columnSalt FROM $db_table WHERE $db_columnUser= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$stmt->bindColumn($db_columnPass, $realPass);
		$stmt->bindColumn($db_columnSalt, $salt);
		$stmt->bindColumn($db_columnUser, $realUser);
		$stmt->fetch();
	} else if($crypt == 'hash_xenforo')
	{
		
		$stmt = $db->prepare("SELECT scheme_class, $db_table.$db_columnId,$db_table.$db_columnUser,$db_tableOther.$db_columnId,$db_tableOther.$db_columnPass FROM $db_table, $db_tableOther WHERE $db_table.$db_columnId = $db_tableOther.$db_columnId AND $db_table.$db_columnUser= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$stmt->bindColumn($db_columnPass, $salt);
		$stmt->bindColumn($db_columnUser, $realUser);
		$stmt->fetch();
		$stmt->execute();
		$stmt->bindColumn($db_columnPass, $realPass);
		$stmt->bindColumn('scheme_class', $scheme_class);
		$stmt->fetch();	
		$realPass = substr($realPass,22,64);
		if($scheme_class==='XenForo_Authentication_Core') {
			$salt = substr($salt,105,64);
		} else $salt = false;
	} else die(Security::encrypt(json_encode(array('error'=>true,'code'=>STATUS_INTERNAL_ERROR,'text'=>'Bad hash!')), $key1));

	$checkPass = hash_name($crypt, $realPass, $postPass, @$salt);

	if($useantibrut)
	{	
		$ip  = getenv('REMOTE_ADDR');	
		$time = time();
		$bantime = $time+(10);
		$stmt = $db->prepare("Select sip,time From sip Where sip='$ip' And time>'$time'");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$real = $row['sip'];
		if($ip == $real)
		{
			$stmt = $db->prepare("DELETE FROM sip WHERE time < '$time';");
			$stmt->execute();
			exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_BAD_LOGIN,"text"=>"Timeout")), $key1));
		}
		
		if ($login !== $realUser)
		{
			$stmt = $db->prepare("INSERT INTO sip (sip, time)VALUES ('$ip', '$bantime')");
			$stmt->execute();
			exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_BAD_LOGIN,"text"=>"Bad login")), $key1));
		}
		if(!strcmp($realPass,$checkPass) == 0 || !$realPass) {
			$stmt = $db->prepare("INSERT INTO sip (sip, time)VALUES ('$ip', '$bantime')");
			$stmt->execute();
			exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_BAD_LOGIN,"text"=>"Bad password")), $key1));
		}

    } else {
		if ($login !== $realUser)
		{
			exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_BAD_LOGIN,"text"=>"Bad login")), $key1));
		}
		if(!strcmp($realPass,$checkPass) == 0 || !$realPass) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_BAD_LOGIN,"text"=>"Bad password")), $key1));
    }
	
if($useban)
{
    $time = time();
    $tipe = '2';
	$stmt = $db->prepare("Select name From $banlist Where name= :login And type<'$tipe' And temptime>'$time'");
	$stmt->bindValue(':login', $login);
	$stmt->execute();
    if($stmt->rowCount())
	{
		$stmt = $db->prepare("Select name,temptime From $banlist Where name= :login And type<'$tipe' And temptime>'$time'");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		exit(Security::encrypt(json_encode(array('error'=>true,'code'=>STATUS_BAN,"text"=>'Временный бан до '.date('d.m.Yг. H:i', $row['temptime'])." по времени сервера")), $key1));
    }
		$stmt = $db->prepare("Select name From $banlist Where name= :login And type<'$tipe' And temptime='0'");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
	if($stmt->rowCount())
    {
      exit(Security::encrypt(json_encode(array('error'=>true,'code'=>STATUS_BAN,"text"=>'Вечный бан')), $key1));
    }
}

	//Да, это тоже быдлокод, но все же...
	if(($action == 'getpersonal' && !$usePersonal) ||
		($action == 'uploadskin' && !$canUploadSkin) ||
		($action == 'uploadskin' && !$canUploadCloak) ||
		($action == 'buyvip' && !$canBuyVip) ||
		($action == 'buypremium' && !$canBuyVip) ||
		($action == 'buyunban' && !$canBuyUnban) ||
		($action == 'exchange' && !$canExchangeMoney) ||
		($action == 'activatekey' && !$canActivateVaucher))
		 exit(Security::encrypt(json_encode(array('error'=>true,'code'=>STATUS_INTERNAL_ERROR,'text'=>'Функция недоступна','hash'=>hash("md5",$x))), $key1)); //Поле hash, теоретически, повысит криптостойкость
	
	
	/*if($action == 'uploadskin' && !$canUploadSkin) die ("Функция недоступна");
	if($action == 'uploadcloak' && !$canUploadCloak) die("Функция недоступна");
	if($action == 'buyvip' && !$canBuyVip) die("Функция недоступна");
	if($action == 'buypremium' && !$canBuyPremium) die("Функция недоступна");
	if($action == 'buyunban' && !$canBuyUnban) die("Функция недоступна");
	if($action == 'exchange' && !$canExchangeMoney) die("Функция недоступна");
	if($action == 'activatekey' && !$canActivateVaucher) die("Функция недоступна");*/

	if($action == 'exchange' || $action == 'getpersonal')
	{
			$stmt = $db->prepare("SELECT username,balance FROM iConomy WHERE username= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$rowicon = $stmt->fetch(PDO::FETCH_ASSOC);
			$iconregistered = true;
		
		if(!$rowicon['balance'])
		{
			$stmt = $db->prepare("INSERT INTO `iConomy` (`username`, `balance`, `status`) VALUES (:login, '$initialIconMoney.00', '0');");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$iconregistered = false;
		}
	}
    
	if($action == 'auth')
	{


		//Проверка файлов клиента
        /*if($assetsfolder)
        { $z = "/"; } else { $z = ".zip"; }
		*/
		
		$files = array(
			/*"{$DirClient}/{$client}/bin/client.zip",
			"{$DirClient}/{$client}/bin/minecraft.jar",
			"{$DirClient}/{$client}/bin/libraries.jar",
			"{$DirClient}/{$client}/bin/Forge.jar",
			"{$DirClient}/{$client}/bin/extra.jar",
			"{$DirClient}/{$client}/mods/",
			"{$DirClient}/{$client}/coremods",
			"{$DirClient}/{$client}/bin/assets.zip"*/
			"/bin/minecraft.jar",
			"/bin/natives.zip",
			"/client.zip",
			"/assets.zip"
		);
		if(file_exists("{$DirClient}/{$client}/mods")){
			$f = scandir("{$DirClient}/{$client}/mods/");
			foreach($f as $fn){
				if(preg_match("/\.jar$/",$fn))
					array_push($files,"/mods/{$fn}");
			}
		}
		foreach($files as $f){
			if(!file_exists("{$DirClient}/{$client}/{$f}")) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Missing file {$f}","line"=>__LINE__,"file"=>__FILE__)),$key1));
        }


		//Кажется, это генерация Session ID...
	    $chars="0123456789abcdef";
        $max=32;
        $size=StrLen($chars)-1;
        $password=null;
        while($max--)
        $password.=$chars[rand(0,$size)];
	    $chars2="0123456789abcdef";
        $max2=32;
        $size2=StrLen($chars)-1;
        $password2=null;
        while($max2--)
        $password2.=$chars2[rand(0,$size2)];
		
		$sessid = "token:".$password.":".$password2; 
        $stmt = $db->prepare("SELECT id,user FROM usersession WHERE user= :login");
		$stmt->bindValue(':login', $realUser);
		$stmt->execute();
		$realUs = $stmt->fetch(PDO::FETCH_ASSOC);
		if($login == $realUs['user'])
		{
			$stmt = $db->prepare("UPDATE usersession SET session = '$sessid' WHERE user= :login");
			$stmt->bindValue(':login', $realUser);
			$stmt->execute();
		}
		else
		{
			$stmt = $db->prepare("INSERT INTO usersession (user, session, md5) VALUES (:login, '$sessid', :md5)");
			$stmt->bindValue(':login', $login);
			$stmt->bindValue(':md5', md5($realUser));
			$stmt->execute();
		}

    	$md5us = md5($realUser);
        $md5user  = strtoint(xorencode($md5us, $protectionKey));
        /*$md5zip	  = @md5_file("clients/".$client."/config.zip");
        $md5ass	  = @md5_file("clients/assets.zip");
        $sizezip  = @filesize("clients/".$client."/config.zip");
        $sizeass  = @filesize("clients/assets.zip");*/
        $finfo = array();
        foreach($files as $f){
			array_push($finfo,array(
				"path"=>$f,
				"check"=>(preg_match("/\.jar$/i",$f)),
				"md5"=>(preg_match("/\.jar$/i",$f) ? md5_file($f) : null), 
				"size"=>@filesize($f)
			));
		}
        
		exit(Security::encrypt(json_encode(array("error"=>false,"code"=>STATUS_OK,"text"=>"Success","line"=>__LINE__,"file"=>__FILE__,
			"files"=>$finfo,"sid"=>$sessid,"version"=>$masterversion
		)),$key1));
  
	} else
  
	if($action == 'getpersonal')
	{
		$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$realmoney = $row['realmoney'];

		if($iconregistered)
		{	
			$stmt = $db->prepare("SELECT username,balance FROM iConomy WHERE username= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$iconmoney = $row['balance'];
		} else $iconmoney = "0.0";
		
		if($canBuyVip || $canBuyPremium)
		{
			
			$stmt = $db->prepare("SELECT name,permission,value FROM permissions WHERE name= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$datetoexpire = 0;
			if(!$stmt) $ugroup = 'User'; else
			{
				$group = $row['permission'];
				if($group == 'group-premium-until')
				{
					$ugroup = 'Premium';
					$datetoexpire = $row['value'];
				} else if($group == 'group-vip-until')
				{
					$ugroup = 'VIP';
					$datetoexpire = $row['value'];
				} else $ugroup = 'User';
			}
		} else
		{
			$datetoexpire = 0;
			$ugroup = 'User';
		}
	
		if($canUseJobs)
		{
			$stmt = $db->prepare("SELECT job FROM jobs WHERE username= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$sql = $stmt->fetch(PDO::FETCH_ASSOC);
			$query = $sql['job'];
			if($query == '') { $jobname = "Безработный"; $joblvl = 0; $jobexp = 0; } else
			{
				$stmt = $db->prepare("SELECT * FROM jobs WHERE username= :login");
				$stmt->bindValue(':login', $login);
				$stmt->execute();
				
				while($data = $stmt->fetch(PDO::FETCH_ASSOC))
				{
					if ($data["job"] === 'Miner') $data["job"] = 'Шахтер';
					if ($data["job"] === 'Woodcooter') $data["job"] = 'Лесоруб';
					if ($data["job"] === 'Builder') $data["job"] = 'Строитель';
					if ($data["job"] === 'Digger') $data["job"] = 'Дигер';
					if ($data["job"] === 'Farmer') $data["job"] = 'Фермер';
					if ($data["job"] === 'Hunter') $data["job"] = 'Охотник';
					if ($data["job"] === 'Fisherman') $data["job"] = 'Рыбак';
					if ($data["job"] === 'Weaponsmith') $data["job"] = 'Оружейник';
					
					$jobname = $data['job'];
					$joblvl = $data["level"];
					$jobexp = $data["experience"];
				}
			}
		} else { $jobname = "nojob"; $joblvl = -1; $jobexp = -1; }
		
		/*$canUploadSkin 		= (int)$canUploadSkin;
		$canUploadCloak		= (int)$canUploadCloak;
		$canBuyVip	   		= (int)$canBuyVip;
		$canBuyPremium 		= (int)$canBuyPremium;
		$canBuyUnban   		= (int)$canBuyUnban;
		$canActivateVaucher = (int)$canActivateVaucher;
		$canExchangeMoney	= (int)$canExchangeMoney;*/ 
	
		if($canBuyUnban)
		{
		    $ty = 2;
			$stmt = $db->prepare("SELECT name,type FROM $banlist WHERE name= :login and type<'$ty'");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$sql2 = $stmt->fetch(PDO::FETCH_ASSOC);
			$query2 = $sql2['name'];
			if(strcasecmp($query2, $login) == 0) $ugroup = "Banned";
		}
		
		//echo "$canUploadSkin$canUploadCloak$canBuyVip$canBuyPremium$canBuyUnban$canActivateVaucher$canExchangeMoney<:>$iconmoney<:>$realmoney<:>$cloakPrice<:>$vipPrice<:>$premiumPrice<:>$unbanPrice<:>$exchangeRate<:>$ugroup<:>$datetoexpire<:>$jobname<:>$joblvl<:>$jobexp";
		exit(Security::encrypt(json_encode(array(
			"abilities"	=> array(
				"skin"			=> $canUploadSkin,
				"cloak"			=> $canUploadCloak,
				"vip" 			=> $canBuyVip,
				"premium" 		=> $canBuyPremium,
				"unban" 		=> $canBuyUnban,
				"activate"		=> $canActivateVaucher,
				"exchange"		=> $canExchangeMoney
			),
			"money"		=> $realmoney,
			"imoney"	=> $iconmoney,
			"price"		=> array(
				"vip"			=> $vipPrice,
				"premium"		=> $premiumPrice,
				"unban"			=> $unbanPrice,
				"exchange"		=> $exchangeRate,
				"cloak"			=> $cloakPrice
			),
			"until"		=> $datetoexpire,
			"group" 	=> $ugroup,
			"job"		=> array(
				"name"			=> $jobname,
				"level"			=> $joblvl,
				"experience"	=> $jobexp
			),
			"skin"		=> (file_exists("{$uploaddirs}/{$login}.png")? $login : ".default"),
			"cloak"		=> (file_exists("{$uploaddirp}/{$login}.png")? $login : ".default")
		)),$key1));
	} else
//============================================Функции ЛК====================================//

	if($action == 'activatekey')
	{
		$key = $_POST['key'];
		$stmt = $db->prepare("SELECT * FROM `$db_tableMoneyKeys` WHERE `$db_columnKey` = '$key'");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$amount = $row[$db_columnAmount];
		if($amount)
		{
			$stmt = $db->prepare("UPDATE usersession SET realmoney = realmoney + $amount WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$stmt = $db->prepare("DELETE FROM `$db_tableMoneyKeys` WHERE `$db_columnKey` = '$key'");
			$stmt->execute();	
			$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);	
			$money = $row['realmoney'];
			exit(Security::encrypt(json_encode(array("error"=>false,"code"=>STATUS_OK,"money"=>$money,"text"=>"Success")),$key1));
		} else exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Wrong key")),$key1));
	} else

	if($action == 'uploadskin')
	{
		if(!is_uploaded_file($_FILES['ufile']['tmp_name'])) 
			exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"No file")),$key1));
		$imageinfo = getimagesize($_FILES['ufile']['tmp_name']);
		if($imageinfo['mime'] != 'image/png' || $imageinfo["0"] != '64' || $imageinfo["1"] != '32') 
			exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Wrong image")),$key1));
		$uploadfile = "".$uploaddirs."/".$login.".png";
		if(move_uploaded_file($_FILES['ufile']['tmp_name'], $uploadfile)) 
			exit(Security::encrypt(json_encode(array("error"=>false,"code"=>STATUS_OK,"text"=>"Success")),$key1));
		else exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"File error")),$key1));
	} else
	
	if($action == 'uploadcloak')
	{
		$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$query = $row['realmoney']; if($query < $cloakPrice) 
		
		if(!is_uploaded_file($_FILES['ufile']['tmp_name'])) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Insufficient funds","line"=>__LINE__,"file"=>__FILE__)),$key1));
			
		$imageinfo = getimagesize($_FILES['ufile']['tmp_name']);
		
		if(($imageinfo['mime'] != 'image/png' || $imageinfo["0"] == '64' || $imageinfo["1"] == '32')){
			$uploadfile = "".$uploaddirp."/".$login.".png";
			
			if(!move_uploaded_file($_FILES['ufile']['tmp_name'], $uploadfile)) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"File error","line"=>__LINE__,"file"=>__FILE__)),$key1));
			
			$stmt = $db->prepare("UPDATE usersession SET realmoney = realmoney - $cloakPrice WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			
			exit(Security::encrypt(json_encode(array("error"=>false,"code"=>STATUS_OK,"text"=>"Success")),$key1));
		} else exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Cloak error","file"=>__FILE__,"line"=>__LINE__)),$key1));
	} else
	
	if($action == 'buyvip')
	{
		$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$query = $row['realmoney']; if($query < $vipPrice) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Insufficient funds","line"=>__LINE__,"file"=>__FILE__)),$key1));
	    $stmt = $db->prepare("SELECT name,permission FROM permissions WHERE name= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$group = $row['permission'];
		$pexdate = time() + 2678400;
		if($group == 'group-vip-until')
		{	
			$stmt = $db->prepare("UPDATE usersession SET realmoney=realmoney-$vipPrice WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$stmt = $db->prepare("UPDATE permissions SET value=value+2678400 WHERE name= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
		} else
		{
			$stmt = $db->prepare("INSERT INTO permissions (id, name, type, permission, world, value) VALUES (NULL, :login, '1', 'group-vip-until', ' ', '$pexdate')");
			$stmt->bindValue(':login', $login);
			$stmt->execute();	
			$stmt = $db->prepare("INSERT INTO permissions_inheritance (id, child, parent, type, world) VALUES (NULL, :login, 'vip', '1', NULL)");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$stmt = $db->prepare("UPDATE usersession SET realmoney=realmoney-$vipPrice WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
		}
			$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$money = $row['realmoney'];
			$stmt = $db->prepare("SELECT name,permission,value FROM permissions WHERE name= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			exit(Security::encrypt(json_encode(array("error"=>false,"code"=>STATUS_OK,"text"=>"Success","line"=>__LINE__,"file"=>__FILE__,"money"=>$money,"perm"=>$row['value'])),$key1));
	} else
	
	if($action == 'buypremium')
	{
		$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$query = $row['realmoney']; if($query < $premiumPrice) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Insufficient funds","line"=>__LINE__,"file"=>__FILE__)),$key1));
		$stmt = $db->prepare("SELECT name,permission FROM permissions WHERE name= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$group = $row['permission'];
		$pexdate = time() + 2678400;
		if($group == 'group-premium-until')
		{
			$stmt = $db->prepare("UPDATE usersession SET realmoney=realmoney-$premiumPrice WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$stmt = $db->prepare("UPDATE permissions SET value=value+2678400 WHERE name= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
		} else
		{
			$stmt = $db->prepare("INSERT INTO permissions (id, name, type, permission, world, value) VALUES (NULL, :login, '1', 'group-premium-until', ' ', '$pexdate')");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$stmt = $db->prepare("INSERT INTO permissions_inheritance (id, child, parent, type, world) VALUES (NULL, :login, 'premium', '1', NULL)");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$stmt = $db->prepare("UPDATE usersession SET realmoney=realmoney-$premiumPrice WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
		}
			$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$money = $row['realmoney'];
			$stmt = $db->prepare("SELECT name,permission,value FROM permissions WHERE name= :login");
			$stmt->bindValue(':login', $login);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			exit(Security::encrypt(json_encode(array("error"=>false,"code"=>STATUS_OK,"text"=>"Success","line"=>__LINE__,"file"=>__FILE__,'perm'=>$row['value'],'money'=>$money)),$key1));
	} else
	
	if($action == 'buyunban')
	{
		$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$sql1 = $stmt->fetch(PDO::FETCH_ASSOC);
		$query1 = $sql1['realmoney'];
		$stmt = $db->prepare("SELECT name FROM $banlist WHERE name= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$sql2 = $stmt->fetch(PDO::FETCH_ASSOC);
		$query2 = $sql2['name'];
		if(strcasecmp($query2, $login) == 0)
		{
			if($query1 >= $unbanPrice)
			{
				if($canBuyVip || $canBuyPremium)
				{
					$stmt = $db->prepare("SELECT name,permission,value FROM permissions WHERE name= :login");
					$stmt->bindValue(':login', $login);
					$stmt->execute();
					$row = $stmt->fetch(PDO::FETCH_ASSOC);
					$group = $row['permission'];
					if(!$stmt) $ugroup = 'User'; else
					{
						if($group == 'group-premium-until') $ugroup = 'Premium';
						else if($group == 'group-vip-until') $ugroup = 'VIP';
						else $ugroup = 'User';
					}
				} else $ugroup = 'User';
					$stmt = $db->prepare("DELETE FROM $banlist WHERE name= :login");
					$stmt->bindValue(':login', $login);
					$stmt->execute();
					$stmt = $db->prepare("UPDATE usersession SET realmoney=realmoney-$unbanPrice WHERE user= :login");
					$stmt->bindValue(':login', $login);
					$stmt->execute();
					$stmt = $db->prepare("SELECT $db_columnUser,realmoney FROM usersession WHERE user= :login");
					$stmt->bindValue(':login', $login);
					$stmt->execute();
					$row = $stmt->fetch(PDO::FETCH_ASSOC);
				//echo "success:".$row['realmoney'].":".$ugroup;
				exit(Security::encrypt(json_encode(array(
					"error"=>false,
					"text"=>"Success",
					"code"=>STATUS_OK,
					"money"=>$row['realmoney'],
					"group"=>$ugroup
				)),$key1));
			} else exit(Security::encrypt(json_encode(array(
					"error"=>true,
					"text"=>"Insufficient funds",
					"code"=>STATUS_INTERNAL_ERROR,
					"hash"=>hash("md5",microtime()) //чтобы повысить сложность взлома
			)),$key1));
		} else exit(Security::encrypt(json_encode(array(
					"error"=>true,
					"text"=>"You are not banned",
					"code"=>STATUS_INTERNAL_ERROR,
					"hash"=>hash("md5",microtime())
			)),$key1));
	} else

	if($action == 'exchange')
	{
		$wantbuy =$_POST ['buy'];
		$gamemoneyadd = ($wantbuy * $exchangeRate);
		$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$query = $row['realmoney'];
		if($wantbuy == '' || $wantbuy < 1) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Wrong query")),$key1));
		if(!$iconregistered) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Function is not available")),$key1));
		if($query < $wantbuy) exit(Security::encrypt(json_encode(array("error"=>true,"code"=>STATUS_INTERNAL_ERROR,"text"=>"Insufficient funds")),$key1));
		$stmt = $db->prepare("UPDATE iConomy SET balance = balance + $gamemoneyadd WHERE username= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$stmt = $db->prepare("UPDATE usersession SET realmoney = realmoney - $wantbuy WHERE user= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$stmt = $db->prepare("SELECT user,realmoney FROM usersession WHERE user= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$money = $row['realmoney'];
		$stmt = $db->prepare("SELECT username,balance FROM iConomy WHERE username= :login");
		$stmt->bindValue(':login', $login);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$iconmoney = $row['balance'];
		//echo "success:".$money.":".$iconmoney;
		exit(Security::encrypt(json_encode(array(
			"error"=>false,
			"code"=>STATUS_OK,
			"text"=>"Success",
			"money"=>$money,
			"imoney"=>$iconmoney
		)),$key1));
	} else exit(Security::encrypt(json_encode(array(
		"error"=>true,
		"code"=>STATUS_INTERNAL_ERROR,
		"text"=>"Wrong query"
	)),$key1));
	
	} catch(PDOException $pe) {
		die(Security::encrypt(json_encode(array(
			"error"=>true,
			"code"=>STATUS_SQL_ERROR,
			"text"=>"SQL Error",
			"edata"=>$pe
		)), $key1));
		$logger->WriteLine($log_date.$pe);  //вывод ошибок MySQL в m.log
	}
	//===================================== Вспомогательные функции ==================================//

	function xorencode($str, $key)
	{
		while(strlen($key) < strlen($str))
		{
			$key .= $key;
		}
		return $str ^ $key;
	}

	function strtoint($text)
	{
		$res = "";
		for ($i = 0; $i < strlen($text); $i++) $res .= ord($text{$i}) . "-";
		$res = substr($res, 0, -1);
		return $res;
	}

	function hash_name($ncrypt, $realPass, $postPass, $salt) {
		$cryptPass = false;
		
		if ($ncrypt === 'hash_xauth')
		{
				$saltPos = (strlen($postPass) >= strlen($realPass) ? strlen($realPass) : strlen($postPass));
				$salt = substr($realPass, $saltPos, 12);
				$hash = hash('whirlpool', $salt . $postPass);
				$cryptPass = substr($hash, 0, $saltPos) . $salt . substr($hash, $saltPos);
		}

		if ($ncrypt === 'hash_md5' or $ncrypt === 'hash_launcher')
		{
				$cryptPass = md5($postPass);
		}

		if ($ncrypt === 'hash_dle')
		{
				$cryptPass = md5(md5($postPass));
		}

		if ($ncrypt === 'hash_cauth')
		{
				if (strlen($realPass) < 32)
				{
						$cryptPass = md5($postPass);
						$rp = str_replace('0', '', $realPass);
						$cp = str_replace('0', '', $cryptPass);
						(strcasecmp($rp,$cp) == 0 ? $cryptPass = $realPass : $cryptPass = false);
				}
				else $cryptPass = md5($postPass);
		}

		if ($ncrypt === 'hash_authme')
		{
				$ar = preg_split("/\\$/",$realPass);
				$salt = $ar[2];
				$cryptPass = '$SHA$'.$salt.'$'.hash('sha256',hash('sha256',$postPass).$salt);
		}

		if ($ncrypt === 'hash_joomla')
		{
				$parts = explode( ':', $realPass);
				$salt = $parts[1];
				$cryptPass = md5($postPass . $salt) . ":" . $salt;
		}
				
		if ($ncrypt === 'hash_imagecms')
		{
		        $majorsalt = '';
				if ($salt != '') {
					$_password = $salt . $postPass;
				} else {
					$_password = $postPass;
				}
				
				$_pass = str_split($_password);
				
				foreach ($_pass as $_hashpass) {
					$majorsalt .= md5($_hashpass);
				}
				
				$cryptPass = crypt(md5($majorsalt), $realPass);
		}

		if ($ncrypt === 'hash_joomla_new' or $ncrypt === 'hash_wordpress' or $ncrypt === 'hash_xenforo')
		{
		
				if($ncrypt === 'hash_xenforo' and $salt!==false) {
					return $cryptPass = hash('sha256', hash('sha256', $postPass) . $salt);
				}
				
				$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
				$cryptPass = '*0';
				if (substr($realPass, 0, 2) == $cryptPass)
					$cryptPass = '*1';

				$id = substr($realPass, 0, 3);
				# We use "$P$", phpBB3 uses "$H$" for the same thing
				if ($id != '$P$' && $id != '$H$')
					return $cryptPass = crypt($postPass, $realPass);

				$count_log2 = strpos($itoa64, $realPass[3]);
				if ($count_log2 < 7 || $count_log2 > 30)
					return $cryptPass = crypt($postPass, $realPass);

				$count = 1 << $count_log2;

				$salt = substr($realPass, 4, 8);
				if (strlen($salt) != 8)
					return $cryptPass = crypt($postPass, $realPass);

					$hash = md5($salt . $postPass, TRUE);
					do {
						$hash = md5($hash . $postPass, TRUE);
					} while (--$count);

				$cryptPass = substr($realPass, 0, 12);
				
				$encode64 = '';
				$i = 0;
				do {
					$value = ord($hash[$i++]);
					$encode64 .= $itoa64[$value & 0x3f];
					if ($i < 16)
						$value |= ord($hash[$i]) << 8;
					$encode64 .= $itoa64[($value >> 6) & 0x3f];
					if ($i++ >= 16)
						break;
					if ($i < 16)
						$value |= ord($hash[$i]) << 16;
					$encode64 .= $itoa64[($value >> 12) & 0x3f];
					if ($i++ >= 16)
						break;
					$encode64 .= $itoa64[($value >> 18) & 0x3f];
				} while ($i < 16);
				
				$cryptPass .= $encode64;

				if ($cryptPass[0] == '*')
					$cryptPass = crypt($postPass, $realPass);
		}
		
		if ($ncrypt === 'hash_ipb')
		{
				$cryptPass = md5(md5($salt).md5($postPass));
		}
		
		if ($ncrypt === 'hash_punbb')
		{
				$cryptPass = sha1($salt.sha1($postPass));
		}

		if ($ncrypt === 'hash_vbulletin')
		{
				$cryptPass = md5(md5($postPass) . $salt);
		}

		if ($ncrypt === 'hash_drupal')
		{
				$setting = substr($realPass, 0, 12);
				$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
				$count_log2 = strpos($itoa64, $setting[3]);
				$salt = substr($setting, 4, 8);
				$count = 1 << $count_log2;
				$input = hash('sha512', $salt . $postPass, TRUE);
				do $input = hash('sha512', $input . $postPass, TRUE);
				while (--$count);

				$count = strlen($input);
				$i = 0;
		  
				do
				{
						$value = ord($input[$i++]);
						$cryptPass .= $itoa64[$value & 0x3f];
						if ($i < $count) $value |= ord($input[$i]) << 8;
						$cryptPass .= $itoa64[($value >> 6) & 0x3f];
						if ($i++ >= $count) break;
						if ($i < $count) $value |= ord($input[$i]) << 16;
						$cryptPass .= $itoa64[($value >> 12) & 0x3f];
						if ($i++ >= $count) break;
						$cryptPass .= $itoa64[($value >> 18) & 0x3f];
				} while ($i < $count);
				$cryptPass =  $setting . $cryptPass;
				$cryptPass =  substr($cryptPass, 0, 55);
		}
		
		return $cryptPass;
	}

	    function checkfiles($path) {
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
        $massive = "";
		    foreach($objects as $name => $object) {
			    $basename = basename($name);
			    $isdir = is_dir($name);
			    if ($basename!="." and $basename!=".." and !is_dir($name)){
			     	$str = str_replace('clients/', "", str_replace($basename, "", $name));
			     	$massive = $massive.$str.$basename.':>'.md5_file($name).':>'.filesize($name).'<:>';
			    }
		    }
		    return $massive;
        }

?>
