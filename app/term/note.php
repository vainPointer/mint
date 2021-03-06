<?php
require_once "../public/_pdo.php";
require_once "../public/function.php";
require_once "../channal/function.php";
require_once "../path.php";

$_channal = new Channal();

$_data = array();
if(isset($_POST["data"])){
	$_data = json_decode($_POST["data"],true);
}
else{
	if(isset($_GET["id"])){
		$id=$_GET["id"];
	}
	else{
		echo "error: no id";
		return;
	}
	if(isset($_GET["info"])){
		$info=$_GET["info"];
	}
	else{
		echo "error: no info";
		return;
	}
	$_data[] = array("id"=>$id,"data"=>$info);
}

if(isset($_POST["setting"])){
	$_setting = json_decode($_POST["setting"],true);
}
else{
	$_setting["lang"] = "";
	$_setting["channal"] = "";
}

$dns = "sqlite:"._FILE_DB_PALI_SENTENCE_;
$db_pali_sent = new PDO($dns, "", "",array(PDO::ATTR_PERSISTENT=>true));
$db_pali_sent->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);  

$dns = "sqlite:"._FILE_DB_SENTENCE_;
$db_trans_sent = new PDO($dns, "", "",array(PDO::ATTR_PERSISTENT=>true));
$db_trans_sent->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);  

$output = array();

foreach ($_data as $key => $value) {
	# code...
	$id = $value["id"];
	$arrInfo = explode("@",$value["data"]);
	if(isset($arrInfo[1])){
		$sentChannal = $arrInfo[1];
	}
	else{
		$sentChannal = "";
	}
	if(isset($arrInfo[0])){
		$arrSent = str_getcsv($arrInfo[0],"-");
		$bookId=$arrSent[0];
		$para=$arrSent[1];
		$begin=$arrSent[2];
		$end=$arrSent[3];
	}
	else{
		$bookId=0;
		$para=0;
		$begin=0;
		$end=0;
	}

	$query="SELECT html FROM 'pali_sent' WHERE book = ? AND paragraph = ? AND begin = ? AND end = ? ";
	$sth = $db_pali_sent->prepare($query);
	$sth->execute(array($bookId,$para,$begin,$end));
	$row = $sth->fetch(PDO::FETCH_NUM);
	if ($row) {
		$palitext= $row[0];
	} else {
		$palitext="";
	}

	//find out translation 查询译文
	$tran="";
	$translation = array();
	$tran_channal = array();
	try{
		if(empty($_setting["channal"])){
			if($sentChannal==""){
				$query="SELECT * FROM sentence WHERE book= ? AND paragraph= ? AND begin= ? AND end= ?  AND strlen >0   order by modify_time DESC limit 0 ,1 ";
				$channal = "";				
			}
			else{
				$query="SELECT * FROM sentence WHERE book= ? AND paragraph= ? AND begin= ? AND end= ?  AND strlen >0  AND channal = ?  limit 0 ,1 ";
			}
		}
		else{
			$query="SELECT * FROM sentence WHERE book= ? AND paragraph= ? AND begin= ? AND end= ?  AND strlen >0  AND channal = ?  limit 0 ,1 ";
			$channal = $_setting["channal"];
		}
		
		$stmt = $db_trans_sent->prepare($query);
		if(empty($_setting["channal"])){
			if($sentChannal==""){
				$stmt->execute(array($bookId,$para,$begin,$end));
				$Fetch = $stmt->fetch(PDO::FETCH_ASSOC);
				if($Fetch){
					$tran = $Fetch["text"];
					$translation[]=array("id"=>$Fetch["id"],"text"=>$Fetch["text"],"lang"=>$Fetch["language"],"channal"=>$Fetch["channal"],"editor"=>$Fetch["editor"]);
					if(!empty($Fetch["channal"])){
						$tran_channal[$Fetch["channal"]] = $Fetch["channal"];
					}
				}
			}
			else{
				$stmt->execute(array($bookId,$para,$begin,$end,$sentChannal));
				$Fetch = $stmt->fetch(PDO::FETCH_ASSOC);
				if($Fetch){
					$tran = $Fetch["text"];
					$translation[]=array("id"=>$Fetch["id"],"text"=>$Fetch["text"],"lang"=>$Fetch["language"],"channal"=>$Fetch["channal"],"editor"=>$Fetch["editor"]);
					$tran_channal[$Fetch["channal"]] = $Fetch["channal"];
				}
			}
		}
		else{
			$arrChannal = explode(",",$_setting["channal"]);
			foreach ($arrChannal as $key => $value) {
				# code...
				$stmt->execute(array($bookId,$para,$begin,$end,$value));
				$Fetch = $stmt->fetch(PDO::FETCH_ASSOC);
				if($Fetch){
					$translation[]=array("id"=>$Fetch["id"],"text"=>$Fetch["text"],"lang"=>$Fetch["language"],"channal"=>$value,"editor"=>$Fetch["editor"]);
					
				}
				else{
					$translation[]=array("id"=>"","text"=>"","lang"=>"","channal"=>$value);
				}
				$tran_channal[$value] = $value;
			}
		}
		$tran_count = 1;
	}
	catch (Exception $e) {
		$tran = $e->getMessage();
		//echo 'Caught exception: ',  $e->getMessage(), "\n";
	}

	foreach ($tran_channal as $key => $value) {
		# code...
		$tran_channal[$key] = $_channal->getChannal($key);
	}
	foreach ($translation as $key => $value) {
		# code...
		$translation[$key]["channalinfo"]=$tran_channal[$value["channal"]];
	}

	
	$para_path=_get_para_path($bookId,$para);

	$output[]=array("id"=>$id,
							   "palitext"=>$palitext,
							   "tran"=>$tran,
							   "translation"=>$translation,
							   "ref"=>$para_path,
							   "tran_count"=>$tran_count,
							   "book"=>$bookId,
							   "para"=>$para,
							   "begin"=>$begin,
							   "end"=>$end
							);

}

echo json_encode($output, JSON_UNESCAPED_UNICODE);

?>