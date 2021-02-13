<?php
$config = array(
	'logging'		=>	true,
	'log_file' 	=>	'logs/server.log',
	'log_sep' 	=>	'\t',
	'cert_file'	=> 	getcwd().'/certs/sailboat-anon.space/combined.pem',
	'cert_pass' 	=> 	'password',
	'local_ip' 	=> 	'127.0.0.1',
	'local_port'	=> 	'1965',
	'hosted_sites_dir' 		=> getcwd().'/hosts/',
	'default_dir'				=>	getcwd().'/hosts/sailboat-anon.space/',
	'acceptable_index_files'	=>	array('index.gemini', 'index.gmi'),

);
print_r($config);
if(empty($config['cert_file'])) die("> Missing cert {$config['cert_file']} \n");
if(!is_readable($config['cert_file']))die("> Cert is unreadable: {$config['cert_file']} \n");

$context = stream_context_create();

stream_context_set_option($context, 'ssl', 'local_cert', $config['cert_file']);
stream_context_set_option($context, 'ssl', 'passphrase', $config['cert_pass']);
stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
stream_context_set_option($context, 'ssl', 'verify_peer', false);
stream_context_set_option($context, 'ssl', 'cafile', $config['cert_file']);

$socket = stream_socket_server("tcp://{$config['local_ip']}:{$config['local_port']}", $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);
stream_socket_enable_crypto($socket, false);
// apply patch from @nervuri:matrix.org to stop supporting out of spec versions of TLS
$cryptoMethod = STREAM_CRYPTO_METHOD_TLS_SERVER
	& ~ STREAM_CRYPTO_METHOD_TLSv1_0_SERVER
	& ~ STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;

while(true) {
	$forkedSocket = stream_socket_accept($socket, "-1", $remoteIP);

	stream_set_blocking($forkedSocket, true);
	stream_socket_enable_crypto($forkedSocket, true, $cryptoMethod);
	$line = fread($forkedSocket, 1024);
	stream_set_blocking($forkedSocket, false);

	$parsed_url = parse_request($line);
	
	$filepath = get_filepath($parsed_url);
	print_r($filepath);
	$status_code = get_status_code($filepath);

	$meta = "";
	$filesize = 0;

	if($status_code == "20") {
		$meta = get_mime_type($filepath);
		$content = file_get_contents($filepath);	
		$filesize = filesize($filepath);
	} else {
		$meta = "Not found: {$status_code}";
	}

	$status_line = $status_code." ".$meta;
	//if($g->logging)
		//$g->log_to_file($remoteIP,$status_code, $meta, $filepath, $filesize);
	$status_line .= "\r\n";
	fwrite($forkedSocket, $status_line);

	if($status_code == "20") {
		fwrite($forkedSocket,$content);
	}

	fclose($forkedSocket);
}

function parse_request($request) {
	$url = trim($request); // <CR><LF> 
	return parse_url($url);
}

function get_valid_hosts() {
	global $config;
	$dirs = array_map('basename', glob($config['hosted_sites_dir'].'*', GLOB_ONLYDIR));
	return $dirs;
}

function get_status_code($filepath) {
	if(is_file($filepath) and file_exists($filepath)) return '20';
	if(!file_exists($filepath)) return '51';
	return '50';
}

function get_mime_type($filepath) {
	$type = mime_content_type($filepath);
	// detect gemini file type
	// so.. if it ends with gemini (or if it has no extension), assume
	$path_parts = pathinfo($filepath);
	if(empty($path_parts['extension']) or $path_parts['extension'] == "gemini") $type = "text/gemini"; // add .gmi
	return $type;
}

function get_filepath($url) {
	global $config;
	$hostname = "";
	if(!is_array($url))	return false;
	if(!empty($url['host'])) $hostname = $url['host'];
	$valid_hosts = get_valid_hosts();
	if(!in_array($hostname, $valid_hosts))
		$hostname = "localhost";

	$url['path'] = str_replace(array("..", "__"), "", $url['path']);
	// force an index file to be appended if a filename is missing
	if(empty($url['path'])) {
		$url['path'] = "/".($config['acceptable_index_files'][0]);
	} elseif(substr($url['path'], -1) == "/") {
   $url['path'] .= $config['acceptable_index_files'][0]; // extend later
}

$return_path = ($config['hosted_sites_dir']).$hostname.$url['path'];
// check the real path is in the data_dir (path traversal sanity check)
if(substr(realpath($return_path),0, strlen($config['hosted_sites_dir'])) == $config['hosted_sites_dir']) {
   return $return_path;
   }
   return false;
}

function log_to_file($ip, $status_code, $meta, $filepath, $filesize) {
	$ts = date("Y-m-d H:i:s", strtotime('now'));
	$this->log_sep;
	$str = $ts.$this->log_sep.$ip.$this->log_sep.$status_code.$this->log_sep.
	$meta.$this->log_sep.$filepath.$this->log_sep.$filesize."\n";
	file_put_contents($this->log_file, $str, FILE_APPEND);
}