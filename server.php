<?php
/*
 * Gemini server written in PHP by seven@0xm.net
 * Version 0.1, Oct 2020
*/

if(!require("config.php"))
	die("config.php is missing.  Copy config.php.sample to config.php and customise your settings");
require("gemini.class.php");
$g = new Gemini($config);

$context = stream_context_create();

stream_context_set_option($context, 'ssl', 'local_cert', $g->certificate_file);
stream_context_set_option($context, 'ssl', 'passphrase', $g->certificate_passphrase);
stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
stream_context_set_option($context, 'ssl', 'verify_peer', false);

$socket = stream_socket_server("tcp://{$g->ip}:{$g->port}", $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);

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

	$parsed_url = $g->parse_request($line);

	$filepath = $g->get_filepath($parsed_url);

	$status_code = $g->get_status_code($filepath);

	$meta = "";
	$filesize = 0;

	if($status_code == "20") {
		$meta = $g->get_mime_type($filepath);
		$content = file_get_contents($filepath);	
		$filesize = filesize($filepath);
	} else {
		$meta = "Not found";
	}

	$status_line = $status_code." ".$meta;
	if($g->logging)
		$g->log_to_file($remoteIP,$status_code, $meta, $filepath, $filesize);
	$status_line .= "\r\n";
	fwrite($forkedSocket, $status_line);

	if($status_code == "20") {
		fwrite($forkedSocket,$content);
	}

	fclose($forkedSocket);
}

?>
