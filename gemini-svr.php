<?php 
/* 
* sailboat-anon | fairwinds! | https://github.com/sailboat-anon/gemini-svr | gemini://sailboat-anon.space
* geminispace server | gemini-svr.php
* 
*                                  |
*                                  |
*                           |    __-__
*                         __-__ /  | (
*                        /  | ((   | |
*                      /(   | ||___|_.  .|
*                    .' |___|_|`---|-'.' (
*               '-._/_| (   |\     |.'    \
*                   '-._|.-.|-.    |'-.____'.
*                       |------------------'
*                        `----------------'   
* 
* forked/refactored from https://coding.openguide.co.uk/git/gemini-php/
*
* setup: 
* 	openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -subj '/CN=yourdomain.space'
* 	cp cert.pem certs/yourdomain.space.pem
* 	cat key.pem >> certs/yourdomain.space.pem
*
* use:
*	php gemini-svr.php <cert_password>
*/
echo <<<EOT
                      _       _                             _           
                     (_)     (_)                           | |          
  __ _  ___ _ __ ___  _ _ __  _ ______ _____   ___ __ _ __ | |__  _ __  
/  _` |/ _ \ '_ ` _ \| | '_ \| |______/ __\ \ / / '__| '_ \| '_ \| '_ \ 
| (_| |  __/ | | | | | | | | | |      \__ <\ V /| |_ | |_) | | | | |_) |
\__,  |\___|_| |_| |_|_|_| |_|_|      |___/ \_/ |_(_)| .__/|_| |_| .__/ 
 __/  |        gemini://sailboat-anon.space          | |         | |    
|___ /                                               |_|         |_|    


EOT;

if ($argc < 2) { die("> First argument must be the cert password\n"); }
$config = array(
	'logging'		=>	true,
	'log_file' 		=>	getcwd().'/logs/server.log',
	'log_sep' 		=>	'|',
	'cert_file'		=> 	getcwd().'/certs/sailboat-anon.space/combined.pem',
	'local_ip' 		=> 	'localhost',
	'local_port'	=> 	'1965',
	'hosted_sites_dir' 			=> getcwd().'/hosts/',
	'default_dir'				=> getcwd().'/hosts/sailboat-anon.space/',
	'acceptable_index_files'	=>	array('index.gmi', 'index.gemini')
);
global $remote_ip;

if(empty($config['cert_file'])) die("> Missing cert {$config['cert_file']} \n");
if(!is_readable($config['cert_file'])) die("> Cert is unreadable: {$config['cert_file']} \n");
//file_put_contents($config['log_file'], json_encode($config), FILE_APPEND); // debug mode only!

$context = stream_context_create();
stream_context_set_option($context, 'ssl', 'local_cert', $config['cert_file']);
stream_context_set_option($context, 'ssl', 'passphrase', $argv[1]);
stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
stream_context_set_option($context, 'ssl', 'verify_peer', false);
stream_context_set_option($context, 'ssl', 'cafile', $config['cert_file']);

$socket = stream_socket_server("tcp://{$config['local_ip']}:{$config['local_port']}", $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);

while (true) {
	$hsocket = stream_socket_accept($socket, '-1', $remote_ip); // -1 is 'daemon'
	stream_set_blocking($hsocket, true);
	stream_socket_enable_crypto($hsocket, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER); // enforce TLS 1.2
	// downgrade or restrict
	/*$cryptoMethod = STREAM_CRYPTO_METHOD_TLS_SERVER
		& ~ STREAM_CRYPTO_METHOD_TLSv1_0_SERVER
		& ~ STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;
	*/
	$line = fread($hsocket, 1024);
	stream_set_blocking($hsocket, false);

	$parsed_url = parse_request($line);
	$filepath = get_filepath($parsed_url);
	$status_code = get_status_code($filepath);

	$meta = '';
	$filesize = 0;
	
	if($status_code == 20) {
		$meta = get_mime_type($filepath);
		$content = file_get_contents($filepath);	
		$filesize = filesize($filepath);
	} else {
		$meta = "Not found: {$status_code}";
	}

	$status_line = $status_code." ".$meta;
	if ($config['logging']) {
		log_to_file($remote_ip, $status_code, $meta, $filepath, $filesize);
	}
	$status_line .= "\r\n";
	fwrite($hsocket, $status_line);

	if($status_code == 20) {
		fwrite($hsocket,$content);
	}
	fclose($hsocket);
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

function get_status_code($filepath): string {
	if(is_file($filepath) and file_exists($filepath)) return 20;
	if(!file_exists($filepath)) return 51;
	return 50;
}

function get_mime_type($filepath): string {
	$type = mime_content_type($filepath);
	$path_parts = pathinfo($filepath);
	if(empty($path_parts['extension']) or ($path_parts['extension'] == 'gemini') || ($path_parts['extension'] == 'gmi')) $type = 'text/gemini';
	return $type;
}

function get_filepath($url): string {
	global $config;
	$hostname = "";
	if(!is_array($url))	return false;
	if(!empty($url['host'])) $hostname = $url['host'];
	$valid_hosts = get_valid_hosts();
	if(!in_array($hostname, $valid_hosts))
		$hostname = "localhost";
	
	$url['path'] = str_replace(array("/..", "__"), "", $url['path']); 
	if(substr($url['path'], -1) == "/") {
		if (is_readable($config['acceptable_index_files'][0])) {
			$extension = '.gmi';
		}
		$url['path'] .= $config['acceptable_index_files'][1]; // allow .gemini, prefer .gmi
	}
	$return_path = ($config['hosted_sites_dir']).$hostname.$url['path'];
	// path traversal sanity check
	if(substr(realpath($return_path), 0, strlen($config['hosted_sites_dir'])) == $config['hosted_sites_dir']) { return $return_path; }
}

function log_to_file($ip, $status_code, $meta, $filepath, $filesize) {
	global $config;
	$ts = date('Y-m-d H:i:s', strtotime('now'));
	$str = $ts.$config['log_sep'].$ip.$config['log_sep'].$status_code.$config['log_sep'].$meta.$config['log_sep'].$filepath.$config['log_sep'].$filesize."\n";
	print_r($str);
	file_put_contents($config['log_file'], $str, FILE_APPEND);
}