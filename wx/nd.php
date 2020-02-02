<?php
/*-------------------------------------------------
|   nd.php [ 微信公众平台签到 ]
+--------------------------------------------------
|   作者: szl
+------------------------------------------------*/

//封装成一个微信接口类

$wechatObj = new WeixinApi();

$wechatObj->responseMsg();

class WeixinApi {
	private $appid;
	private $appsecret;

	//构造方法 初始化赋值
	public function construct($appid = "wxc756cd53e21da343", $appsecret =
					   "c09d50333b25fa5df40eb508b6d9e446") {
		$this->appid     = $appid;
		$this->appsecret = $appsecret;
	}

	//响应消息
	public function responseMsg() {
		$adminOpenid = "o6JTTjrNjfzDvEulrVL46lk5uJVY"; //管理员openid

		$tip =
"输入\"BDXH你的学号\"进行学号绑定\n绑定成功后输入\"KQ签到码\"进行考勤\n输入\"CKXS\"查看所有学生列表\n输入\"CKQQ\"查看缺勤学生列表\n输入\"KSKQ\"生成签到码,开始考勤(管理员)"
								; //提示信息

		date_default_timezone_set("Asia/Shanghai"); //设置时区
		//接收微信服务器发送POST请求到开发者服务器，携带的XML数据包
		$postData = $GLOBALS['HTTP_RAW_POST_DATA'];

		//处理xml数据包
		$xmlObj = simplexml_load_string($postData, "SimpleXMLElement",
								LIBXML_NOCDATA);

		if (!$xmlObj) {
			echo "";
			exit;
		}

		//获取接收消息中的参数内容
		$toUserName   = $xmlObj->ToUserName; //开发者微信号
		$fromUserName = $xmlObj->FromUserName; //发送方的微信号（openid）
		$msgType      = $xmlObj->MsgType; //消息类型
		$openid       = $fromUserName;
		$kqm          = $this->kqm($xmlObj);
		switch ($msgType) {
			//接收文本消息
			case 'text':
				//获取文本消息的关键字
				$keyword = $this->receiveText($xmlObj);
				//进行关键字回复
				//      if(strlen($keyword) == 14 || strlen($keyword) == 6){
				switch (substr($keyword, 0, 4)) {
					case "BDXH":
					case "bdxh":
						echo $this->BDXH($xmlObj,
						       substr($keyword, 4, 10));
						break;
					case "CKQQ":
					case "ckqq":

						echo $this->CKQQ($xmlObj, $kqm)
									       ;
						break;
					case "CKXS":
					case "ckxs":
						echo $this->CKXS($xmlObj);
						break;
					case "KSKQ":
					case "kskq":
						echo $this->KSKQ($xmlObj,
								  $adminOpenid);
						break;

					default:

						if ($keyword == "KQ" . $kqm) {
							echo $this->KQ($xmlObj,
							   $fromUserName, $kqm);
						} else if (substr($keyword, 0,
								   2) == "KQ") {
							echo $this->replyText(
				    $xmlObj, "签到码不正确！ \n" . $tip);
						}
						echo $this->replyText($xmlObj,
				      "输入格式不正确 code:1 \n" . $tip);
						break;
				}

				break;
			/*    }else{
			echo $this->replyText($xmlObj,"格式不正确1");}*/

			//接收图片消息
			case 'image':
				echo $this->receiveImage($xmlObj);
				break;
			// //接收事件推送
			case 'event':
				echo $this->replyText($xmlObj,
"欢迎关注本公众号！先绑定学号后进行签到,签到码由老师公布\n"
									. $tip);
				break;
		}
		echo $this->replyText($xmlObj,
				      "输入格式不正确 code:2 \n" . $tip);
	}

	public function kqm($xmlObj) { //获取最新考勤码
		$db = $this->ConnectSqlite();
		if ($db) {
			$sql = "select qdm,savetime from code ";

			$ret = $db->query($sql);

			foreach ($ret as $row) {
				$kqm = $row['qdm'];
			}
			if (strlen($kqm) > 1) {
				return $kqm;
			} else {
				return $this->replyText($xmlObj,
						       "获取签到码失败");
			}
		} else {
			return $this->replyText($xmlObj,
						      "数据库错误 code:3");
		}
	}

	public function KSKQ($xmlObj, $adminOpenid) { //开始签到
		$openid = $xmlObj->FromUserName;
		if ($openid == $adminOpenid) {
			$db = $this->ConnectSqlite();
			if ($db) {
				$kqm = mt_rand(1000, 9999);
				$sql = "update code set qdm = '" . $kqm .
				    "',savetime='" . date("Y-m-d H:i:s") . "'";

				$ret = $db->query($sql);

				return $this->replyText($xmlObj,
				  "签到码： " . $kqm . "\n时间： 90秒");
			} else {
				return $this->replyText($xmlObj,
						"数据库错误 code:3" . $xh);
			}
		} else {
			return $this->replyText($xmlObj,
		 "你不是管理员,无法使用开始考勤功能" . $openid);
		}
	}

	public function CKXS($xmlObj) { //查看最新一次缺勤记录
		$db = $this->ConnectSqlite();
		if ($db) {
			$qqList = "";
			$sql    = "select xuehao,name from student ";

			$ret = $db->query($sql);

			foreach ($ret as $row) {
				$qqList .= "学号:" . $row['xuehao'] .
					       " 姓名:" . $row['name'] . "\n";
			}
			if (strlen($qqList) > 1) {
				return $this->replyText($xmlObj,
						   "学生列表:\n" . $qqList);
			} else {
				return $this->replyText($xmlObj,
						"学生列表为空" . $qqList);
			}
		} else {
			return $this->replyText($xmlObj,
						"数据库错误 code:3" . $xh);
		}
	}

	public function CKQQ($xmlObj, $kqm) { //查看最新一次缺勤记录
		$db = $this->ConnectSqlite();
		if ($db) {
			$qqList = "";
			$sql    =
		  "select xuehao,name from student where qdm !='" . $kqm . "'";

			$ret = $db->query($sql);

			foreach ($ret as $row) {
				$qqList .= "学号:" . $row['xuehao'] .
					       " 姓名:" . $row['name'] . "\n";
			}
			if (strlen($qqList) > 1) {
				return $this->replyText($xmlObj, "缺勤:\n" .
								       $qqList);
			} else {
				return $this->replyText($xmlObj,
				    "学生列表里无缺勤记录" . $qqList);
			}
		} else {
			return $this->replyText($xmlObj,
						"数据库错误 code:3" . $xh);
		}
	}

	public function KQ($xmlObj, $openid, $kqm) { //考勤码考勤
		$db = $this->ConnectSqlite();
		if ($db) {
			$sql  = "select qdm from student where openid ='" .
								 $openid . "'";
			$sql2 = "update student set qdm = '" . $kqm .
       "',qdtime='" . date("Y-m-d H:i:s") . "' where openid='" . $openid . "'";
			$sql3 = "select savetime from code ";

			$ret  = $db->query($sql);
			$ret2 = $db->query($sql3);
			foreach ($ret2 as $row) {
				$savetime = $row['savetime'];
			}
			foreach ($ret as $row) {
				$qdm = $row['qdm'];
			}
			$as = strtotime(date("Y-m-d H:i:s")) - strtotime(
								     $savetime);
			if ($qdm == $kqm || $as > 90) {
				return $this->replyText($xmlObj,
			"签到失败,不能重复签到或签到时段已过");
			} else if (strlen($qdm) > 0 && $qdm != $kqm) {
				$ret2 = $db->query($sql2);
				return $this->replyText($xmlObj, $xh .
					       "签到成功！ ：" . $openid);
			} else if (strlen($qdm) == 0) {
				return $this->replyText($xmlObj,
				"签到失败,你没有绑定学号" . $openid);
			}
		} else {
			return $this->replyText($xmlObj,
						"数据库错误 code:3" . $xh);
		}
	}

	public function BDXH($xmlObj, $xh) { //绑定学号
		$db = $this->ConnectSqlite();
		if ($db) {
			$sql  = "select openid from student where openid ='" .
						   $xmlObj->FromUserName . "'";
			$sql2 = "update student set openid = '" . $xmlObj->
FromUserName . "' ,bdtime='" . date("Y-m-d H:i:s") . "'  where xuehao='" . $xh .
									   "'";
			$sql3 = "select id,openid from student where xuehao ='"
								   . $xh . "'";
			$ret  = $db->query($sql);
			$ret2 = $db->query($sql3);
			foreach ($ret as $row) {
				$openid = $row['openid'];
			}
			foreach ($ret2 as $row2) {
				$id      = $row2['id'];
				$openid2 = $row2['openid'];
			}
			if (strlen($id) == 0) {
				return $this->replyText($xmlObj,
"绑定错误,你不在学生列表里,如需加入学生列表请联系管理员"
							     . strlen($openid));
			} else
			if (strlen($openid) > 1 || strlen($openid2) > 1) {
				return $this->replyText($xmlObj,
  "绑定错误,你已经绑定过学号,不能重复绑定" . strlen($openid));
			} else {
				$ret2 = $db->query($sql2);
				return $this->replyText($xmlObj, $xh .
						   "绑定成功！" . $openid);
			}
		} else {
			return $this->replyText($xmlObj,
						"数据库错误 code:3" . $xh);
		}
	}

	public function ConnectSqlite() { //sqlite 连接函数

		$db = new PDO("sqlite:./kaoqin.db3");
		if ($db) {
			return $db;
		} else {
			return "sqlite connection Error";
		}
	}

	public function receiveText($obj) //接收微信发来的信息
	{
		$content = trim($obj->Content); //文本消息的内容
		return $content;
	}
	public function replyText($obj, $content) {
		$replyXml = "<xml>
              <ToUserName><![CDATA[%s]]></ToUserName>
              <FromUserName><![CDATA[%s]]></FromUserName>
              <CreateTime>%s</CreateTime>
              <MsgType><![CDATA[text]]></MsgType>
              <Content><![CDATA[%s]]></Content>
               <FuncFlag>0</FuncFlag>
            </xml>";
		return sprintf($replyXml, $obj->FromUserName, $obj->ToUserName,
							      time(), $content);
	}

	//接收图片消息
	public function receiveImage($obj) {
		$picUrl  = $obj->PicUrl; //图片的链接
		$mediaId = $obj->MediaId; //图片消息媒体id
		return $this->replyImage($obj, $mediaId);
	}

	//回复图片消息
	public function replyImage($obj, $mediaId) {
		$replyXml = "<xml>
              <ToUserName><![CDATA[%s]]></ToUserName>
              <FromUserName><![CDATA[%s]]></FromUserName>
              <CreateTime>%s</CreateTime>
              <MsgType><![CDATA[image]]></MsgType>
              <Image>
                <MediaId><![CDATA[%s]]></MediaId>
              </Image>
            </xml>";
		return sprintf($replyXml, $obj->FromUserName, $obj->ToUserName,
							      time(), $mediaId);
	}
	//
	//   //回复文本消息

	//验证服务器地址有效性
	// public function valid()
	// {
	//
	//   if($this->checkSignature())
	//   {
	//     $echostr = $_GET['echostr'];//随机的字符串
	//
	//     return $echostr;
	//
	//   }
	//   else
	//   {
	//     return "Error";
	//   }
	// }
	//
	// //检查签名
	// private function checkSignature()
	// {
	//   //一、接收微信服务器GET方式提交过来的4个参数数据
	//
	//   $signature = $_GET['signature'];//微信加密签名
	//
	//   $timestamp = $_GET['timestamp'];//时间戳
	//
	//   $nonce = $_GET['nonce'];//随机数
	//
	//   //二、加密/校验过程
	//   // 1. 将token、timestamp、nonce三个参数进行字典序排序；
	//   // bool sort ( array &$array [, int $sort_flags = SORT_REGULAR ] ) 对数组排序
	//
	//   $tmpArr = array(TOKEN,$timestamp,$nonce);//将上面三个参数放到一个数组里面
	//   sort($tmpArr,SORT_STRING);
	//
	//   // 2. 将三个参数字符串拼接成一个字符串进行sha1加密；
	//   $tmpStr = implode($tmpArr); //将数组转化成字符串
	//
	//   $signatureStr = sha1($tmpStr);
	//
	//   // 3. 开发者获得加密后的字符串与signature对比。
	//   if($signatureStr == $signature)
	//   {
	//     return true;
	//   }
	//   else
	//   {
	//     return false;
	//   }
	// }
}

?>
