<?php
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

$config = array([
	logging		=>	true,
	log_file 	=>	'logs/server.log',
	log_sep 	=>	'\t',
	cert_file	=> 	'sba.crt',
	cert_pass 	=> 	'password',

	local_ip 	=> 	'127.0.0.1',
	local_port	=> 	'1965',
	hosted_sites_dir 		=> 'hosts/',
	default_dir				=>	'hosts/gemini-web-svcs/',
	acceptable_index_files	=>	array('index.gemini'),

]);

if(empty($config['cert_file'])) die("> Missing cert {$config['cert_file']} \n");
if(!is_readable($this->certificate_file))die("> Cert is unreadable: {$config['cert_file']} \n");

function parse_request($request) {
	$url = trim($request); // strip <CR><LF> from the end
	return parse_url($url);
}

function get_valid_hosts() {
	$dirs = array_map('basename', glob($this->data_dir.'*', GLOB_ONLYDIR));
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
	if(empty($path_parts['extension']) or $path_parts['extension'] == "gemini") $type = "text/gemini";
	return $type;
}


	// Kristall Browser is adding "__" to the end of the filenames
	// wtf am I missing?
	// also removing ".." to mitigate against directory traversal
	$url['path'] = str_replace(array("..", "__"), "", $url['path']);
	// force an index file to be appended if a filename is missing
	if(empty($url['path'])) {
		$url['path'] = "/".$this->default_index_file;
	} elseif(substr($url['path'], -1) == "/") {
		$url['path'] .= $this->default_index_file;
	}

	$valid_data_dir = dirname(__FILE__)."/".$this->data_dir;
	$return_path = $this->data_dir.$hostname.$url['path'];
	// check the real path is in the data_dir (path traversal sanity check)
	if(substr(realpath($return_path),0, strlen($valid_data_dir)) == $valid_data_dir) {
		return $return_path;
	}
	return false;

function log_to_file($ip, $status_code, $meta, $filepath, $filesize) {
	$ts = date("Y-m-d H:i:s", strtotime('now'));
	$this->log_sep;
	$str = $ts.$this->log_sep.$ip.$this->log_sep.$status_code.$this->log_sep.
	$meta.$this->log_sep.$filepath.$this->log_sep.$filesize."\n";
	file_put_contents($this->log_file, $str, FILE_APPEND);
}