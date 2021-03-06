<?php
/*
向句子库中插入或更新数据
*/
require_once "../path.php";
require_once "../public/_pdo.php";
require_once "../public/function.php";


$aData=json_decode($_POST["data"],TRUE);

PDO_Connect("sqlite:"._FILE_DB_SENTENCE_);

//查询没有id的哪些是数据库里已经存在的，防止多次提交同一条记录造成一个句子 多个channal
$newList = array();
$new_id = array();
$oldList = array();
$query = "SELECT id FROM sentence WHERE book = ? and paragraph = ? and  begin = ? and end = ? and channal = ? limit 0 , 1 ";
foreach ($aData as $data) {
	if(!isset($data["id"]) || empty($data["id"])){
		$id = PDO_FetchOne($query,array($data["book"],
																	   $data["paragraph"],
																	   $data["begin"],
																	   $data["end"],
																	   $data["channal"]
																	   ));
		if(empty($id)){
			$newList[] = $data;
		}
		else{
			$data["id"] = $id;
			$oldList[] = $data;
		}
	}
	else{
		$oldList[] = $data;
	}
}
$update_list = array(); //已经成功修改数据库的数据 回传客户端

/* 修改现有数据 */
$PDO->beginTransaction();
$query="UPDATE sentence SET text= ?  , status = ? , strlen = ? , receive_time= ?  , modify_time= ?   where  id= ?  ";
$sth = $PDO->prepare($query);


foreach ($oldList as $data) {
	if(isset($data["id"])){
		if(isset($data["time"])){
			$modify_time = $data["time"];
		}
		else{
			$modify_time = mTime();
		}
		$sth->execute(array($data["text"], $data["status"], mb_strlen($data["text"],"UTF-8"), mTime(),$modify_time,$data["id"]));
	} 
}

$PDO->commit();

$respond=array("status"=>0,"message"=>"","insert_error"=>"","new_list"=>array());

if (!$sth || ($sth && $sth->errorCode() != 0)) {
	/*  识别错误且回滚更改  */
	$PDO->rollBack();
	$error = PDO_ErrorInfo();
	$respond['status']=1;
	$respond['message']=$error[2];
}
else{
	$respond['status']=0;
	$respond['message']="成功";
	foreach ($oldList as $key => $value) {
		$update_list[] =  array("id" => $value["id"],"book"=>$value["book"],"paragraph"=>$value["paragraph"],"begin"=>$value["begin"],"end"=>$value["end"],"channal"=>$value["channal"],"text" => $value["text"]);

	}
}


/* 插入新数据 */
$PDO->beginTransaction();
$query = "INSERT INTO sentence (id, 
														parent,
														book,
														paragraph,
														begin,
														end,
														channal,
														tag,
														author,
														editor,
														text,
														language,
														ver,
														status,
														strlen,
														modify_time,
														receive_time,
														create_time
														) 
										VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
$sth = $PDO->prepare($query);

foreach ($newList as $data) {
		$uuid = UUID::v4();
		if($data["parent"]){
			$parent = $data["parent"];
		}
		else{
			$parent  = "";
		}
		$sth->execute(array($uuid,
										  $parent,
										  $data["book"], 
										  $data["paragraph"], 
										  $data["begin"], 
										  $data["end"], 
										  $data["channal"], 
										  $data["tag"], 
										  $data["author"], 
										  $_COOKIE["userid"],
										  $data["text"],
										  $data["language"],
										  1,
										  7,
										  mb_strlen($data["text"],"UTF-8"),
										  mTime(),
										  mTime(),
										  mTime()
										));
		$new_id[] = array($uuid,$data["book"],$data["paragraph"],$data["begin"],$data["end"],$data["channal"],$data["text"]);
}
$PDO->commit();


if (!$sth || ($sth && $sth->errorCode() != 0)) {
	/*  识别错误且回滚更改  */
	$PDO->rollBack();
	$error = PDO_ErrorInfo();
	$respond['insert_error']=$error[2];
	$respond['new_list']=array();
}
else{
	$respond['insert_error']=0;
	foreach ($new_id as $key => $value) {
		$update_list[] =  array("id" => $value[0],"book"=>$value[1],"paragraph"=>$value[2],"begin"=>$value[3],"end"=>$value[4],"channal"=>$value[5],"text" => $value[6]);
	}
}
$respond['update']=$update_list;

echo json_encode($respond, JSON_UNESCAPED_UNICODE);
?>