<?php
ini_set("memory_limit", "5G");
$dir = '/opt/wallets/';
$folders = array_diff(scandir($dir), array('..', '.'));
$MustFileUser = fileowner(__DIR__.'/../httpdocs/index.php');
$MustFileGroup = filegroup(__DIR__.'/../httpdocs/index.php');
$bootstrap_json_file = __DIR__.'/../httpdocs/bootstraps/bootstrap_list.json';
$bootstrap_json = ((file_exists($bootstrap_json_file))?json_decode(file_get_contents($bootstrap_json_file), true):array());

class CoinHiveAPI {
	const API_URL = 'https://api.coinhive.com';
	private $secret = null;
	public function __construct($secret) {
		if (strlen($secret) !== 32) {
			throw new Exception('CoinHive - Invalid Secret');
		}
		$this->secret = $secret;
	}
  
	function get($path, $data = []) {
		$data['secret'] = $this->secret;
		$url = self::API_URL.$path.'?'.http_build_query($data);
		$response = file_get_contents($url);
		return json_decode($response);
	}
	
	function post($path, $data = []) {
		$data['secret'] = $this->secret;
		$context = stream_context_create([
			'http' => [
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data)
			]
		]);
		$url = SELF::API_URL.$path;
		$response = file_get_contents($url, false, $context);
		return json_decode($response);
	}	
}

$coinhive = new CoinHiveAPI('******************');

$denylist = array("bitcoin",
				  "litecoin",
				  "crowncoin",
				  "boolberrycoin",
				  "bitcoingold");

foreach ($folders as $folder) {
	$coinname = $folder;
	if (in_array(strtolower($coinname), $denylist)) continue;
	
	$folder = $dir.$folder.'/';
	if (file_exists($folder.'blocks/')) {
		$folder = $folder.'blocks/';
	} 
	echo 'GENERATE BOOTSTRAP FOR '.$coinname.PHP_EOL;
	$bootstrap = glob($folder."blk0*.dat");
	$newfolder = __DIR__.'/../httpdocs/bootstraps/';
	if(!file_exists($newfolder)) {
		mkdir($newfolder, 0777, true);
		chown($newfolder, $MustFileUser);
		chgrp($newfolder, $MustFileGroup);
	}

	if (is_array($bootstrap) && !empty($bootstrap)) {
		$old_files = glob($newfolder.strtolower($coinname)."*_bootstrap.zip");
		if (!isset($old_files['0']) || (filemtime($old_files['0']) < time()-86400)) {
			if(isset($old_files['0']) && file_exists($old_files['0'])) unlink($old_files['0']);
			$newfile = $newfolder.strtolower($coinname)."_".substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789', 10)), 0, 7)."_bootstrap.dat";
		} else {
			$newfile = $old_files['0'];
		}
		
		$zipname = str_replace('.dat', '.zip', $newfile);
		$datname = str_replace('.zip', '.dat', $newfile);
		
		if (!file_exists($newfile) || (filemtime($newfile) < time()-86400)) {
			if (file_exists($newfile)) unlink($newfile);
			foreach ($bootstrap as $blkfile) {
				file_put_contents($datname, (file_get_contents($blkfile)), FILE_APPEND);
			}
			//
			$zip = new ZipArchive;
			$zip->open($zipname, ZipArchive::CREATE);
			$zip->addFile($datname, "bootstrap.dat");
			$zip->close();
			
			unlink($datname);
			chown($zipname, $MustFileUser);
			chgrp($zipname, $MustFileGroup);
			
			$link = $coinhive->post('/link/create', [
				'url' => 'https://*******.de/bootstraps/'.basename($zipname), 
				'hashes' => 8192
			]);
			
			if ($link->success) {
				$bootstrap_json[strtolower($coinname)]['link'] = $link->url;
				$bootstrap_json[strtolower($coinname)]['time'] = date("d-m-Y");
				file_put_contents($bootstrap_json_file, json_encode($bootstrap_json));
				chown($bootstrap_json_file, $MustFileUser);
				chgrp($bootstrap_json_file, $MustFileGroup);
			}
		}
	}
}



?>
