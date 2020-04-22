<?php

namespace Query;

use Query\MinecraftQueryException;

class MinecraftQuery{

	public const HANDSHAKE = 9;
	public const STATISTICS = 0;

	private $socket;
	private $players;
	private $info;

	public function connect(string $ip, int $port = 19132, int $timeout = 3){
		$this->socket = @fsockopen("udp://" . $ip, $port, $errno, $errstr, $timeout);

		if($errno || !$this->socket){
			$this->players = $this->info = null;
			return false;
		}

		stream_set_timeout($this->socket, $timeout);
		stream_set_blocking($this->socket, true);

		try{
			$result = $this->handshake();
			$this->statistics($result);
		}catch(MinecraftQueryException $ex){
			echo $ex->getMessage() . PHP_EOL;
			$this->players = $this->info = null;
		}finally{
			fclose($this->socket);
		}

		return true;
	}

	public function getInfo(){
		return $this->info ?? [];
	}

	public function getPlayers(){
		return $this->players ?? [];
	}

	public function isOnline(){
		if($this->info !== null){
			return true;
		}
		return false;
	}

	private function handshake(){
		$data = $this->writeData(self::HANDSHAKE);
		return pack("N", $data);
	}

	private function statistics($handshake){
		$KVdata = [];
		$payload = $this->writeData(self::STATISTICS, $handshake . pack("c*", 0x00, 0x00, 0x00, 0x00));
		$payload = substr($payload, 11); // split num
		$data = explode("\x00\x01player_\x00\x00", $payload);

		$players = substr($data[1], 0, -2);
		$data = explode("\x00", $data[0]);

		for($i = 0; $i < count($data) - 1; $i++){
			if($i & 1){
				$KVdata[$data[$i - 1]] = $data[$i];
			}
		}

		$KVdata["numplayers"] = (int)$KVdata["numplayers"];
		$KVdata["maxplayers"] = (int)$KVdata["maxplayers"];
		$KVdata["hostport"] = (int)$KVdata["hostport"];

		if($KVdata["plugins"]){
			$data = explode(": ", $KVdata["plugins"], 2);
			if(count($data) == 2){
				$KVdata["plugins"] = explode("; ", $data[1]);
			}
			for($i = 0; $i < count($KVdata["plugins"]); $i++){
				list($name, $version) = explode(" ", $KVdata["plugins"][$i], 2);
				unset($KVdata["plugins"][$i]);
				$KVdata["plugins"][$i]["name"] = $name;
				$KVdata["plugins"][$i]["version"] = $version;
			}
		}else{
			$KVdata["server_engine"] = "Vanilla";
		}

		$this->info = $KVdata;

		$this->players = empty($players) ? null : explode("\x00", $players);
	}

	private function writeData($packetType, $token = ""){
		$magic = pack("c*", 254, 253);
		$sessionID = [0x01, 0x02, 0x03, 0x04];
		$packet = $magic . chr($packetType);
		foreach($sessionID as $id){
			$packet .= pack("c*", $id);
		}
		$packet .= $token;

		if(strlen($packet) !== fwrite($this->socket, $packet, strlen($packet))){
			throw new MinecraftQueryException("Failed to write on socket.");
		}

		$data = fread($this->socket, 4096);

		if(!$data){
			throw new MinecraftQueryException("Failed to read from socket.");
		}

		$offset = 0;
		if($data{$offset} != $packet{2}){ // Checking packet type
			throw new MinecraftQueryException("Failed to verify packet: Type " . ord($data{$offset}) . " != " . ord($packet{2}));
		}

		$offset += 1;
		$sid = unpack("c*", substr($data, $offset, 4));
		for($i = 0; $i < count($sessionID); $i++){
			if($sessionID[$i] != $sid[$i + 1]){ // Checking sessionID
				throw new MinecraftQueryException("Failed to verify packet: SessionID " . $sessionID[$i] . " != " . $sid[$i + 1]);
			}
		}

		$offset += 4;

		return substr($data, $offset);
	}
}
