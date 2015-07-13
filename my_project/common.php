<?php
/**
 * VIPSHOP 公共类
 */
class App_Common
{
    static $total = 0;

	/**
     * MC分布hot key配置
     */
	static $mcDiverConfig = array(
		0 => '20dcf4ea1e53484fde0e43e79bc1afd1',   //md5('we4@#83lkkkkbbb*&^')
		1 => '1537fdadd4f635290d8dd1420308c6f0',   //md5(')(&^%sdfa@!$#%#$%sdfas')
		2 => '4c2b862c1e57a9c5fc9ce3f76756bd19',   //md5('^&_)sdf%%asdf{}asdf')
		3 => 'f5c79e7bde3d0138a3eb91b0b8e5a96e',   //md5('%2452354$#78fasdfJBF')
		4 => '07f948197e552c77c167bac57ce803ed',   //md5('&(()&^%ASDFffadf*())')
		5 => 'bbfcf7571326e88b33ff9979d9dcdd6f',   //md5('!32423DFa9FASd332@^^&&*')
		6 => '50340a931a4ea0b04df159b3f8382707',   //md5('@$%^asdfahbzergwrtdf')
		7 => 'fb1e2a3380871f283520c61cecb5a825',   //md5('7422@@343&&54dfadsdf')
		8 => '2298ca265ad2e5085e977c5fe505d2f2',   //md5('wfffsss44232#$@43nn^')
		9 => 'f2c0d95d5f1d96a911b4ac6b92dc162a',   //md5('323sdfkbffff3333a^')
	);
    
    /**
     * 返回缓存过期时间
     * 
     */
    public static function getCacheTime(
        $maxCacheTime = null, $minCacheTime = null)
    {
         global $config;
         
         $maxCacheTime = $maxCacheTime
            ? $maxCacheTime : $config['app']['maxCacheTime'];
         $minCacheTime = $minCacheTime
            ? $minCacheTime : $config['app']['minCacheTime'];
         
         $requestTime = $_SERVER['REQUEST_TIME'];
         $result = $maxCacheTime - ($requestTime) % $maxCacheTime;
         return $result > $minCacheTime ? $result : $minCacheTime;
    }

	
    public static function getMemValFromSql($mem_key) {
	    $local_db = Vipcore_Db::factory('slave');
	    $select = $local_db->select()
		        ->from("mem_meta","mem_value")
		        ->where("mem_key = ?", $mem_key);
		$rs = $local_db->fetchOne($select);
		return json_decode($rs, true);
	}
	
    public static function SetMemValToSql($mem_key, $mem_val, $use_copy=false, $setMc=false) {
        $local_db = Vipcore_Db::factory('local');
        $replace_column = array(
            'mem_key'          => $mem_key,
            'mem_value'        => json_encode($mem_val),
            'expire_time'      => 864000,
            'last_modify_time' => $_SERVER['REQUEST_TIME']
        );
		$local_db->replace('mem_meta', $replace_column);
				
		$mem = Vipcore_Cache::factory('memcache');
		if ($setMc) {
			$mem->set($mem_key, $mem_val, 864000);
		}
		
		if ($use_copy) {
			foreach(self::$mcDiverConfig as $suffixKey) {
				$tmp_mem_key = $mem_key . $suffixKey;
				$replace_column = array(
					'mem_key'          => $tmp_mem_key,
					'mem_value'        => json_encode($mem_val),
					'expire_time'      => 864000,
					'last_modify_time' => $_SERVER['REQUEST_TIME']
				);
				$local_db->replace('mem_meta', $replace_column);
				if ($setMc) {
					$mem->set($tmp_mem_key, $mem_val, 864000);
				}
			}
		}

        return true; 
	}

	public static function getStaticVer() {
		$config = $GLOBALS['config'];
		$mem = Vipcore_Cache::factory('memcache');
		$prefix_key = $config['app']['preMemKey'].":static_ver";
		$static_ver_key = $prefix_key . self::getMcRandomSuffixKey();
		$static_ver = $mem->get($static_ver_key);

		if (empty($static_ver)) {
            $static_ver = App_Common::getMemValFromSql($static_ver_key);
			if (empty($static_ver)) {
				$static_ver = App_Common::getMemValFromSql($prefix_key);
			}
		    $mem->set($static_ver_key, $static_ver, 864000);
		}
		
		if (empty($static_ver)) {
			$static_ver['jsVer'] = date("Ymd");
			$static_ver['cssVer'] = $static_ver['jsVer'];
			$static_ver['specialVer'] = $static_ver['jsVer'];
		}
		return $static_ver;
	}

	public static function getMcRandomSuffixKey()
	{
		$ip = App_Tools::real_ip();
		$ipvalue = sprintf("%u", ip2long($ip));
		$randomNum = $ipvalue % 10;
		if (!array_key_exists($randomNum, self::$mcDiverConfig)) {
			$len = count(self::$mcDiverConfig);
			$randomNum = rand(0, $len - 1);
		}

		return self::$mcDiverConfig[$randomNum];
	}
	
	/**
	 *
	 * 获取cookie的域
	 *
	 * @return 返回主机名或者空
	 */
	public static function getCookieDomain() {
		$host = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'vip.com') !== false) 
					? '.vip.com' : '';
		return $host;
	}

	public static function getUserTypeTag($flip = false) {
		global $config;
		
		$userTypeTag = $config['app']['user_type_tag'];

		//默认交换数组中的键和值
		if ($flip) {
			$tmpUserTypeTag = $userTypeTag;
			$userTypeTag = array();
			foreach($tmpUserTypeTag as $key => $val) {
				$userTypeTag[$val['tag']] = array(
					'type' => $key, 
					'template' => $val['template'],	
					'sort' => isset($val['sort']) ? $val['sort'] : $val['tag'],	
					'hide_channel' => isset($val['hide_channel']) ? $val['hide_channel'] : false			
				);
			}
		}

		return $userTypeTag;
	}

	/**
	 *
	 * 获取配置用户类型数组信息
	 *
	 * @return 用户数组信息
	 */
	public static function getUserTypeTagInfo($pageCode = null) {
		$defaultCode = 'c';
		$pageCode = empty($pageCode) ? $defaultCode : $pageCode;
		$userTypeTag = self::getUserTypeTag(true);
		//默认值
		$rs = $userTypeTag[$defaultCode];
		if (array_key_exists($pageCode, $userTypeTag)) {
			$rs = $userTypeTag[$pageCode];
		}

		return $rs;
	}

	public static function getUserTypeInfo($pageCode = null, $uid = '',$warehouse = '') {
		global $config;

		$preview = false;
		$userClass = '';
        $warehouse = trim($warehouse);
		if (!empty($pageCode)) {
			$preview = true;
			$apiUserType = strtolower(trim($pageCode));
		} else {
			$uid = $uid ? $uid : (Vipcore_Cookie::get('VipRUID') !== false ? intval(Vipcore_Cookie::get('VipRUID')) : '');
			$apiUserType = self::getUserTypeFromApi($uid);		
		}		

		//如果从接口中取用户类型不成功则从cookie中取
		if (!$apiUserType && Vipcore_Cookie::get('user_class') !== false) {
			$apiUserType = Vipcore_Cookie::get('user_class');
			
			//如果出错则写日志
			$logResult = 'ID:2,api_user_type:' . $apiUserType;
			self::writeLog(1, $logResult);
		}
		$apiUserType = strtolower(trim($apiUserType));
		$userTypeTag = self::getUserTypeTag(true);
        if ($apiUserType) {
            if (array_key_exists($apiUserType, $userTypeTag)) {
                $userType = $userTypeTag[$apiUserType]['type'];
            } else {
                //如果出错则写日志-不支持的用户类型
                $logResult = 'ID:4,api_user_type:' . $apiUserType;
                self::writeLog(1, $logResult);

                //不支持的用户类型，默认c页面, 如果是已定义的异常类型则写相应值，否则都写c
				if (in_array($apiUserType, $config['app']['exceptionUserType'])) {
					$userClass = $apiUserType;
				}
                $userType = 0;
                $apiUserType = 'c';   
            }
        } else {
            //默认c页面
            $userType = 1;
            $apiUserType = 'a';
            
            //如果出错则写日志
            $logResult = 'ID:3,api_user_type:' . $apiUserType;
            self::writeLog(1, $logResult);
        }
        $userClass = $userClass ? $userClass : $apiUserType;
        Vipcore_Cookie::set('user_class', $userClass, array('expire' =>  time() + 28800, 'path' => '/', 'domain' => App_Common::getCookieDomain()));
		
		//重新设置旧版用来判断A,B,C页面cookie
		self::resetAbcCookie($apiUserType);

		$rs['userType'] = $userType;         //userType : 0 , 1, 2, 3, 4, 5
		$rs['apiUserType'] = $apiUserType;   //apiUserType : c, a, b, c1, c2, c3

		return $rs;
	}

	public static function getUserTypeFromApi($uid,$warehouseId = 0) {
		global $view;
		$warehouseId = intval($warehouseId);
        $requestUrl = "/user/?method=user.type&user_id=" . $uid;
        if($warehouseId > 0){
            $requestUrl .= '&warehouse_id=' . $warehouseId;
        }

        $mars_cid = Vipcore_Cookie::get('mars_cid') !== false ? trim(Vipcore_Cookie::get('mars_cid')) : '';
        if($mars_cid != ''){
            $requestUrl .= '&cid='.$mars_cid;
        }

		$curlUrl = $view['user_profile_api'] . $requestUrl;

		$returnVal = App_Tools::httpGet($curlUrl, 200);
		$returnData = json_decode($returnVal, true);
		$userType = '';
		// $vipUINFO = 'a|a';
		$vipUINFO = 'luc:a|suc:a|bct:c_new|kct:c_new|hct:c_new|bdts:0|bcts:0|kfts:0';
		$cookieDomain = self::getCookieDomain();
		if (!empty($returnData['data']['user_type'])) {
			$userType = $returnData['data']['user_type'];
			$channelType = isset($returnData['data']['channel_type']['beauty']) ? $returnData['data']['channel_type']['beauty'] : 0;
			$kid_channelType = isset($returnData['data']['channel_type']['kid']) ? $returnData['data']['channel_type']['kid'] : 0;
			$home_channelType = isset($returnData['data']['channel_type']['home']) ? $returnData['data']['channel_type']['home'] : 0;
			$beauty_channel_test = $returnData['data']['test_type']['beauty_channel'];
			$beauty_channel_test = $beauty_channel_test == 0 || $beauty_channel_test == 1 ? $beauty_channel_test : false;
			$beauty_detail_test = $returnData['data']['test_type']['beauty_detail'];
			$beauty_detail_test = $beauty_detail_test == 0 || $beauty_detail_test == 1 ? $beauty_detail_test : false;
			$kid_float_test = $returnData['data']['test_type']['kid_float'];
			$kid_float_test = $kid_float_test == 0 || $kid_float_test == 1 ? $kid_float_test : false;
			//用户类型大类
			if (isset($returnData['data']['cookie_type'])) {
				$cookieType = $returnData['data']['cookie_type'];
			} else {
				$cookieType = substr($userType, 0, 1);
			}
			// $vipUINFO = $cookieType . '|' . $userType;
			$vipUINFO = 'luc:'.$cookieType . '|suc:' . $userType . '|bct:' . $channelType 
			. '|kct:' . $kid_channelType . '|hct:' . $home_channelType . '|bcts:' . intval($beauty_channel_test) . '|bdts:' . intval($beauty_detail_test). '|kfts:' . intval($kid_float_test);

			if (empty($mars_cid)) {
				if (!empty($returnData['data']['mars_cid'])) {
					$expireTime = time() + 86400;
					Vipcore_Cookie::set('tmp_mars_cid', $returnData['data']['mars_cid'], array('expire' => $expireTime, 'path' => '/', 'domain' => $cookieDomain));
					//设置全局cookie变量供本次请求其他接口读取 不能删除
    				$_COOKIE['tmp_mars_cid'] = $returnData['data']['mars_cid'];
				} else {
					//如果返回出错则写日志
					$logResult = 'ID:5,user_id:' . $uid . ',reutrn_val:' . $returnVal;
					self::writeLog(1, $logResult);
				}
			}
		} else {
			//如果返回出错则写日志
			$logResult = 'ID:1,user_id:' . $uid . ',reutrn_val:' . $returnVal;
			self::writeLog(1, $logResult);
		}
		//需要写入支付cookie,临时方案
		$vipUINFO .= App_User::dealWithMarsCid(0);

		//设置814userclass cookie
		$adType = 'a';
		if (!empty($returnData['data']['ad_type'])) {
			$adType = trim($returnData['data']['ad_type']);
		} elseif (!empty($userType)) {
			$adType = $userType;
		}
		Vipcore_Cookie::set('814userclass', $adType, array('expire' =>  time() + 28800, 'path' => '/', 'domain' => $cookieDomain));

		//设置VipUINFO用户群信息区分大类小类cookie
		$expireTime = time() + 86400;
		Vipcore_Cookie::set('VipUINFO', $vipUINFO, array('expire' => $expireTime, 'path' => '/', 'domain' => $cookieDomain));
		//设置全局cookie变量供本次请求其他接口读取 不能删除
		$_COOKIE['VipUINFO'] = $vipUINFO;
		
		return $userType;
	}



	/**
	 *	取首页版本(用于组合MC Key或者标记显示)
	 *
	 * @param int $timestamp 时间戳
	 * @return bool/sring
	 */
	public static function getIndexVersion($warehouse, $userType = 0, $abType = 0, $timestamp = 0) {
		
		$rs = false;
		if (!empty($warehouse)) {
			$timestamp = $timestamp ? $timestamp : $_SERVER['REQUEST_TIME'];
			$timeFlag = abs(intval(date("i", $timestamp)/10) % 2);

			$rs = $warehouse . '_' . $userType . "_" . $timeFlag . "_" . $abType;
		} 

		return $rs;
	}

	//取静态首页的版本
	public static function getTimeVersion($timestamp = 0)
	{
		$timestamp = $timestamp ? $timestamp : $_SERVER['REQUEST_TIME'];
		$timeVersion = date('YmdH', $timestamp) . intval(date("i", $timestamp)/10) . "0";
		
		return $timeVersion;
	}

	//取首页内容
	public static function getIndexContent($warehouse, $userType, $type=0, $apiOpenCache = true) {

		global $view, $config;

		$userTypeTemplateTag = self::getUserTypeTag();

		//$fid大促用的页面编码
		$fid = !empty($view['preview_fid']) ? trim($view['preview_fid']) : '';

		$pageCode = isset($userTypeTemplateTag[$userType]['tag']) ? $userTypeTemplateTag[$userType]['tag'] : 'c';
		
		/*** CMS 模块 **/
		$vipDay = false;
		$reData['template'] = 0;
		$actData = App_Activity::getDopCMSID($warehouse, App_User::CHANNEL_USER_TYPE_OLD);
		if ($actData != null) {
			$vipDay = true; // 设置大促标记to前端
			// $query['act_end_time'] = strtotime($actDate['end']); //前端需要，大促结束时间
			$query['act_end_time'] = $actData['end_time']; //前端需要，大促结束时间

			// $reData = App_Activity::getActivityData($warehouse, $pageCode, $actData['cms_id']);
			$reData['template'] = $actData['template'];		

			$vipDayData = App_Activity::getCmsData($actData['cms_id']);
			$vipDayData = isset($vipDayData['active_content_html']) ? $vipDayData['active_content_html'] : '';
			// unset($actDate);
		}
		/*** CMS 模块 **/
		//C3(SVIP)页面：按开售时间来判断档期($searchType=1)，其它页面按展示时间
		$searchType = ($userType == 5) ? 1 : 0;

		$sellingBrandList = Controller_Brand::getSellingBrandList($warehouse, $searchType, $pageCode, 2, $fid, 1, $apiOpenCache);
		// echo json_encode($sellingBrandList);
		// die();

		$newhotMerchandise = Controller_HotMerchandise::newGetHotMerchandiseList($warehouse, $pageCode, $apiOpenCache);

		$readyBrandList = Controller_Brand::getReadyBrandList($warehouse, $searchType, $pageCode, $apiOpenCache);

		$nowDate = date('Ymd', $_SERVER['REQUEST_TIME']);				

		/** ------------------ 页面变量 模板使用 ------------------ **/
		$query['channelId']     = 13;
		$query['user_type']		= $userType;										//用户类型
		$query['warehouse']		= $warehouse;										//分仓	
		//$query['showPopup']		= self::getShowPopup();								//判断首页是否弹窗
		// $query['warehouseId']	= App_Warehouse::getIdByWarehouse( $warehouse );	//仓库id
        $query['v']             = in_array($pageCode, $config['app']['newNavUserIndex30']) ? 3 : 0;  //首页3.0用户才使用新导航
        $query['originCid']		= 10;	//来源cid 
        $query['requestTime']   = $_SERVER['REQUEST_TIME'];									
		
		$query['seo_title']       = '全球特卖_最专业的海外直购网站_全球包邮_唯品会';
		$query['seo_keyword']     = '海外直购,海外购,全球购';
		$query['seo_description'] = "唯品会全球特卖频道，最专业的海外直购网站，全球精选、特卖无界、全球包邮，阳光海淘，海外购、全球购，首选唯品会！客服电话：400-6789-888";
		//海淘商品加入购物车新、旧流程 1旧 2新
      	$query['globalCartProc'] = intval(App_CCReader::get('globalCartProc'));
      	//海淘购物车文案
      	$global_buy_button_text = App_GlobalShopBag::getOverseasShopBag();
      	$global_buy_button_text = (isset($global_buy_button_text['btn_title']) && $global_buy_button_text['btn_title']) ? 
      								$global_buy_button_text['btn_title'] : ($query['globalCartProc'] == 2 ? '立即购买' : '加入购物袋');
      	
		$static_ver = App_Common::getStaticVer();
		$config['view']['jsVer'] = $static_ver['jsVer'];
		$config['view']['cssVer'] = $static_ver['cssVer'];
		$config['view']['specialVer'] = $static_ver['specialVer'];
		//$config['view']['menuList'] = Controller_NavMenu::getNavMenu();
		$config['view']['menuList'] = Controller_NavMenu::getNewNavMenu();
		$config['view']['headerConfig'] = Controller_NavMenu::getNavLogo();
		$view = $config['view'];
		$view['warehouse_cfg'] = $config['warehouse']['provinceWH'];
		$view['commonVer'] = App_CCReader::get('commonVer');
		$view['indexVirtualBrandIds'] = App_CCReader::get('indexVirtualBrandIds');

        // $readyBrandList = Controller_Brand::getReadyBrandList($warehouse, $searchType, $pageCode, $apiOpenCache);

		// $headNavList = Controller_Category::headNavList($warehouse, $apiOpenCache);
		// echo json_encode($headNavList);
		// die();

		ob_start();
		$timeVersion = self::getTimeVersion(); 
		$version = self::getIndexVersion($warehouse, $userType, $type);
		
        //获取模版
        $template = 'index.html';

		include $view['basePath'] . '/' . $template;
		echo "<!-- time:" . date("Y-m-d H:i:s") . " version:" . $timeVersion  . " key:" . 
			$version . " tag:" . $userTypeTemplateTag[$userType]['tag'] . " template:" . 
			$template . " sver:". $config['app']['svnVersion'] . "-->";

		$rs = ob_get_contents();
		ob_end_clean();
		
		return $rs;
	}

    /**
     * 获取首页模版
     * @param  [type] $channelId           [description]
     * @param  [type] $userType            [description]
     * @param  [type] $userTypeTemplateTag [description]
     * @return [type]                      [description]
     */
    private static function getIndexTemplate($userType,$userTypeTemplateTag){
       
       	//return 'index.html';
        $template  = 'index';

        if(isset($userTypeTemplateTag[$userType]['template'])){
            //正常的频道页
            $template .= '_'.$userTypeTemplateTag[$userType]['template'];
        }else{
            $template .= '_c';
        }
        $template .= '.html';

        return $template;
    }

	/**
	 *	取品牌故事url
	 *
	 * @param String $flashUrl flash字段URL
	 */
	public static function getStoryUrl($flashUrl = '') {

		$storyUrl = '';
		if (!empty($flashUrl)) {
			//添加品牌故事为链接
			if(!preg_match("/^http:\/\/a\.vpimg1\.com/", $flashUrl) && preg_match("/^http:\/\//", $flashUrl)) {
				$storyUrl = $flashUrl;
			}
		}

        return $storyUrl;
	}
	
	public static function resetAbcCookie($userType = 'a') {
		$nowTime = time();
		$cookieDomain = self::getCookieDomain();
		switch ($userType) {
			case 'a':
				//新访客页面,如：gz_new_user.html
                $VipNewUser = Vipcore_Cookie::get('VipNewUser');
				if (empty($VipNewUser)) {
					$nextDay = date('Y-m-d',strtotime('+1 day'));
                    Vipcore_Cookie::set('VipNewUser', 1, array('expire' => strtotime($nextDay . ' 10:00:00'), 'path' => '/', 'domain' => $cookieDomain));
				}
				break;
			case 'b':
				//新访客页面,如：gz_new_visitor.html
                Vipcore_Cookie::set('VipNewUser', '', array('expire' => $nowTime - 86400, 'path' => '/', 'domain' => $cookieDomain));
					
				break;
			case 'c':
			default:
				//旧客页面,如：gz.html
                Vipcore_Cookie::set('VipNewUser', '', array('expire' => $nowTime - 86400, 'path' => '/', 'domain' => $cookieDomain));
					
				break;
		}
	
		return true;
	}
	
	/**
	 * 写日志
	 * 
	 * $logType 类型：1为API用户出错   2为MC穿透 3我的收藏品牌 4为品购售罄
	 */
	public static function writeLog($logType = 1, $result = '')
	{
		global $config;
		
		//通过MC控制是否写日志
		$mem = Vipcore_Cache::factory('memcache');
		$memKey = $config['app']['preMemKey'] . ':notWriteErrorLog:UserType';
		$notWriteLog = $mem->get($memKey);
		if ($logType == 1 && !empty($notWriteLog)) {
			return false;
		}
		$memKey = $config['app']['preMemKey'] . ':notWriteErrorLog:HomeMc';
		$notWriteLog = $mem->get($memKey);
		if ($logType == 2 && !empty($notWriteLog)) {
			return false;
		}
		$memKey = $config['app']['preMemKey'] . ':notWriteErrorLog:MyBrand';
		$notWriteLog = $mem->get($memKey);
		if ($logType == 3 && !empty($notWriteLog)) {
			return false;
		}
        $memKey = $config['app']['preMemKey'] . ':notWriteErrorLog:VISPurchase';
        $notWriteLog = $mem->get($memKey);
        if ($logType == 4 && !empty($notWriteLog)) {
            return false;
        }

		$logTypeName = '';
		switch ($logType) {
			case 1:
				$logTypeName = 'user_type';
				break;
			case 2:
				$logTypeName = 'home_mc';
				break;
			case 3:
				$logTypeName = 'my_brand';
                break;
            case 4:
                $logTypeName = 'vis_purchase';
                break;
		}
		
		$logInfo = 'log_type[' . $logTypeName . '] result[' . $result . "]";

		$logger = Vipcore_Log_Logger::getInstance(Vipcore_Log_Logger::WRITER_LOG4PHP);
		$logger->info($logInfo);
		
		return true;
	}
    

	/**
	 * url 内是否存在缓存Key
	 * @return Ambigous <boolean, unknown>
	 */
	public static function getEnableCache(){
	    if(isset($GLOBALS['enableCache'])){
	        $enableCache = $GLOBALS['enableCache'];
	    } else {
	        $enableCache = true;
	    } 	    
	    if ($enableCache){
	        if (!empty($_GET['preview'])) {
	            $authToken = '1fccbbee77df0369c1923f43fa6354cb';
	            $token = !empty($_GET['token']) ? trim($_GET['token']) : '';
	            if ($token === $authToken) {
	                $enableCache = false;
	            }
	        }
	    }
	    $GLOBALS['enableCache'] = $enableCache; 
	    return $enableCache;	    
	}
	
	/**
	 * 获取纠正时间差
	 * @return number
	 */
	public static function getReclaimTime(){
	    return 20;
	}
	
	/**
	 * 通过配置及Url获取shopapi的处理方式
	 * return int 1:不作任何操作 2:直接返回接口数据 3:获取接口值并做缓存
	 */
	public static function getGlobalApiStatus($apiOpenCache = true){
	    $status = 1;
	    $enableCache = App_Common::getEnableCache();
	    if (isset($_GET['closeApi']) && ($_GET['closeApi'] ==1)){
	        return $status;
	    }
	    // $closeApi = self::getCloseIndexApiCache();  
	    // if($closeApi){
	    //     return $status;
	    // }
	    if ($GLOBALS['config']['app']['globalApiOpend'] == true ){
	        //接入ShopApi
	        if ($GLOBALS['config']['app']['globalApiLocalCache'] == true){
	            //ShopApi memcache开启	            
	            if ($enableCache && $apiOpenCache) {
	                $status = 3;	                
	            }else {
	                $status = 2;	        
	            }
	        }else {
	            $status = 2;	         
	        }
	    }
	    return $status;
	}

		/**
	 * 通过配置及Url获取shopapi的处理方式
	 * return int 1:不作任何操作 2:直接返回接口数据 3:获取接口值并做缓存
	 */
	public static function getShopApiStatus($apiOpenCache = true){
	    $status = 1;
	    $enableCache = App_Common::getEnableCache();
	    if (isset($_GET['closeApi']) && ($_GET['closeApi'] ==1)){
	        return $status;
	    }
	    /*$closeApi = self::getCloseIndexApiCache();  
	    if($closeApi){
	        return $status;
	    }*/
	    if ($GLOBALS['config']['app']['shopApiOpend'] == true ){
	        //接入ShopApi
	        if ($GLOBALS['config']['app']['shopApiLocalCache'] == true){
	            //ShopApi memcache开启	            
	            if ($enableCache && $apiOpenCache) {
	                $status = 3;	                
	            }else {
	                $status = 2;	        
	            }
	        }else {
	            $status = 2;	         
	        }
	    }
	    return $status;
	}

	/**
	 * 是否刷新shop.api缓存开关
	 * @param boolean $apiOpenCache 初始化值
	 */
	public static function getShopApiOpenCache($apiOpenCache = true){
	    if($apiOpenCache){
	        //设置成true时才处理
	        if (!empty($_GET['refresh'])) {
	            $authToken = '1fccbbee77df0369c1923f43fa6354cb';
	            $token = !empty($_GET['token']) ? trim($_GET['token']) : '';
	            if ($token === $authToken) {
	                $apiOpenCache = false;
	            }
	        }
	    }
	    return $apiOpenCache;
	}

	public static function getDopApiStatus($apiOpenCache = true){
	    $status = 1;
	    $enableCache = App_Common::getEnableCache();
	    if (isset($_GET['closeApi']) && ($_GET['closeApi'] ==1)){
	        return $status;
	    }
	    // $closeApi = self::getCloseIndexApiCache();
	    // if($closeApi){
	    //     return $status;
	    // }
	    if ($GLOBALS['config']['app']['dopApiOpend'] == true ){
	        //接入ShopApi
	        if ($GLOBALS['config']['app']['dopApiLocalCache'] == true){
	            //ShopApi memcache开启
	            if ($enableCache && $apiOpenCache) {
	                $status = 3;
	            }else {
	                $status = 2;
	            }
	        }else {
	            $status = 2;
	        }
	    }
	    return $status;
	}

	public static function getDopApiOpenCache($apiOpenCache = true){
	    if($apiOpenCache){
	        //设置成true时才处理
	        if (!empty($_GET['refresh'])) {
	            $authToken = '1fccbbee77df0369c1923f43fa6354cb';
	            $token = !empty($_GET['token']) ? trim($_GET['token']) : '';
	            if ($token === $authToken) {
	                $apiOpenCache = false;
	            }
	        }
	    }
	    return $apiOpenCache;
	}

	public static function real_ip() {

	    if (isset($_SERVER['HTTP_CDN_SRC_IP']) && $_SERVER['HTTP_CDN_SRC_IP']!='unknown') {
            $ip =$_SERVER["HTTP_CDN_SRC_IP"];
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } else {
            $ip = getenv('REMOTE_ADDR');
        }
        $realip = explode(',', $ip);
        $pt = '/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/';
        $realip = (preg_match($pt, $realip[0]))?$realip[0]:'0.0.0.0';
        return $realip;
	}
}