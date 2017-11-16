<?php
 
namespace Query;

class MinecraftQuery{
	
	const STATISTIC = 0x00;
	const HANDSHAKE = 0x09;
	
	private $Socket;
	private $Players;
	private $Info;
	
	public function connect($ip, $port = 19132, $timeout = 3, $srv = true){
		if(!is_int($timeout) || $timeout < 0){
			throw new \InvalidArgumentException("Timeout must be an integer.");
			return false;
		}
		
		if($srv){
			$result = $this->resolveSRV($ip,$port);
		}
		
		$this->Socket = @fsockopen("udp://" . $ip, $port, $errno, $errstr, $timeout);
		
		if($errno || !$this->Socket){
			return false;
		}
		
		stream_set_timeout($this->Socket,$timeout);
		stream_set_blocking($this->Socket,true);
		try{
			$challenge = $this->getChallenge();
			$this->getStatus($challenge);
		}catch(Exception $e){
			fclose($this->Socket);
		}
		
		fclose($this->Socket);
		return true;
	}
	
	public function getInfo(){
		return isset($this->Info) ? $this->Info : false;
	}
	
	public function getPlayers(){
		return isset($this->Players) ? $this->Players : false;
	}
	
	public function isOnline(){
		$info = $this->getInfo();
		if(!empty($info["HostPort"])){
			return true;
		}
		return false;
	}
	
	private function getChallenge(){
		$data = $this->writeData(self :: HANDSHAKE);
		if($data == false){
		
		}
		
		return pack("N",$data);
	}
	
	private function getStatus($challenge){
		$data = $this->writeData(self::STATISTIC,$challenge . pack("c*",0x00,0x00,0x00,0x00));
		$last = "";
		$info = [];
		$data = substr($data,11);
		$data = explode("\x00\x00\x01player_\x00\x00",$data);
		if(!isset($data[1])) return false;
		$players = substr($data[1],0,-2);
		$data = explode("\x00",$data[0]);
		
		$keys = [
			"hostname"   => "HostName",
			"gametype"   => "GameType",
			"game_id"    => "GameName",
			"version"    => "Version",
			"server_engine" => "ServerEngine",
			"plugins"    => "Plugins",
			"map"        => "Map",
			"numplayers" => "Players",
			"maxplayers" => "MaxPlayers",
			"whitelist" => "WhiteList",
			"hostip"     => "HostIp",
			"hostport"   => "HostPort"
		];
		
		foreach($data as $key => $value){
			if(~$key & 1 ){
				if(!array_key_exists($value,$keys)){
					$last = false;
					continue;
				}
				$last = $keys[$value];
				$info[$last] = "";
			}else if($last != false){
				$info[$last] = mb_convert_encoding($value,"UTF-8");
			}
		}
		
		$info["Players"] = IntVal($info["Players"]);
		$info["MaxPlayers"] = IntVal($info["MaxPlayers"]);
		$info["HostPort"] = IntVal($info["HostPort"]);
		
		if($info["Plugins"]){
			$data = explode( ": ",$info["Plugins"],2);
			
			$info["RawPlugins"] = $info["Plugins"];
			$info["Software"] = $data[0];
			
			if(count($data) == 2){
				$info["Plugins"] = explode("; ",$data[1]);
			}
		}else{
			$info["Software"] = "Vanilla";
		}
		$this->Info = $info;
		
		if(empty($players)){
			$this->Players = null;
		}else{
			$this->Players = explode("\x00",$players);
		}
	}
	
	private function writeData($command, $append = ""){
		$command = pack("c*",0xFE,0xFD,$command,0x01,0x02,0x03,0x04) . $append;
		$length  = strlen($command);
		
		if($length !== fWrite($this->Socket,$command,$length)){
			throw new Exception("Failed to write on socket.");
		}
		
		$data = fread($this->Socket,4096);
		
		if($data === false){
			throw new Exception("Failed to read from socket.");
		}
		
		if(strlen($data) < 5 || $data[0] != $command[2]){
			return false;
		}
		
		return substr($data,5);
	}
	
	private function resolveSRV(&$address, &$port){
		if(ip2long($address) !== false){
			return;
		}
		
		$record = dns_get_record("_minecraft._tcp." . $address, DNS_SRV);
			
		if(empty($record)){
			return;
		}
		
		if(isset($record[0]["target"])){
			$address = $record[0]["target"];
		}
	}
}