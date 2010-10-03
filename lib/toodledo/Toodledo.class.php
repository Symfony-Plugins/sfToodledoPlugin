<?php
// Written by shesek - http://www.shesek.info/php/toodledo-api
// Released under the SMWTFPL license # http://www.shesek.info/wp-content/uploads/SMWTFPL.txt


class Toodledo {
	const CACHEDIR = '/tmp/';
	const APIURL = 'http://api.toodledo.com/api.php?';
	const TOKENCACHETIME = 13800; // 3 hours and 50 minutes
	
	const XML=1;
	const RAW=2;
	const ARR=3;
	const DEFAULT_RETURN=1;
	
	protected $key;
	
	public function __construct($userid, $password, $appid = null){
		$token = $this->getToken($userid, $appid);
		$this->key = md5(md5($password) . $token . $userid);
	}
	
	public function request($method, $params=array(), $output=self::DEFAULT_RETURN){
		if (!empty($this->key)) $params['key'] = $this->key;
		$url = self::APIURL . 'method='.$method . (!empty($params) ? ';' . http_build_query($params, '', ';') : '');

		$string = file_get_contents($url);
		if (empty($string))
			throw new Exception('Empty response');
		if ($output === self::RAW)
			return $string;
		// Wrap with <response> so outer-most wrapper is accesible with $xml->foo
		if (substr($string,0,2) === '<?' && ($pos=strpos($string,'?>')) !== false&& $pos < strpos($string, '<', 1))
			$string = substr_replace($string, '<response>', $pos+2, 0) . '</response>';
		else
			$string = '<response>' . $string . '</response>';
		$xml = new SimpleXMLElement($string);
		$error = $xml->error;
		if (!empty($error))
			throw new Exception((string)$error);
		if ($output === self::ARR)
			return self::simplexml2array($xml);
		return $xml;
	}
	
	public function __call($method, $args) {
		// Could've array_unshift($args, $method); return call_user_func_array(array($this,'request'),$args);, but its slower
		return $this->request($method, (isset($args[0]) ? $args[0] : array()), (isset($args[1]) ? $args[1] : self::DEFAULT_RETURN));
	}
	
	
	protected function getToken($userid, $appid=null) {
		$cache_path = self::CACHEDIR . '/toodledo.token.' . $userid;
		if (file_exists($cache_path) && filemtime($cache_path) >= time() - self::TOKENCACHETIME) // 3.5 hours
			return file_get_contents($cache_path);
		$params = array('userid'=>$userid);
		if (!empty($appid)) $params['appid'] = $appid;
		$response = $this->request('getToken', $params, self::XML);
		$token = $response->token;
		if (empty($token))
			throw new Exception('Invalid response while getting token');
		$token = (string)$token;
		file_put_contents($cache_path, $token);
		return $token;
	}

	public static function simplexml2array($xml) {
	   if (get_class($xml) == 'SimpleXMLElement') {
	       $attributes = $xml->attributes();
	       foreach($attributes as $k=>$v) {
		   if ($v) $a[$k] = (string) $v;
	       }
	       $x = $xml;
	       $xml = get_object_vars($xml);
	   }
	   if (is_array($xml)) {
	       if (count($xml) == 0) return (string) $x; // for CDATA
	       foreach($xml as $key=>$value) {
		   $r[$key] = self::simplexml2array($value);
	       }
	       if (isset($a)) $r['@'] = $a;    // Attributes
	       return $r;
	   }
	   return (string) $xml;
	}
}

