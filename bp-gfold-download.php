<?php

$query = $_SERVER['QUERY_STRING'];
set_time_limit(3600 * 5);

function fail() {
	die('<h1>Error</h1>');
}

/* copied from the gfold class */
function clean_filename($name) {
	return str_replace(array('/', '\\', '<', '>', '*', '"', ':', '?', '|'), '-', $name);
}
function clean_name($name) {
	$extension = strrchr($name, '.');
	if ($extension === false) {
		$filename = $name;
	} else {
		$filename = mb_substr($name, 0, -mb_strlen($extension));
	}
	$start = mb_strpos($filename, '__');
	if ($start === false) {return $name;}
	return mb_substr($filename, 0, $start) . $extension;
}

/* parse group id and filename */
$start = mb_strpos($query, '-');
$gid = intval(mb_substr($query, 0, $start));
$file = clean_filename(rawurldecode(mb_substr($query, $start + 1)));
$path = dirname(__FILE__).'/'.$gid.'/'.$file;

if (!file_exists($path)) {
	fail();
}

header('Content-Description: File Transfer');
header('Content-Type: application/x-download');
header('Content-Disposition: attachment; filename='.clean_name(basename($path)));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, must-revalidate');
header('Content-Length: ' . filesize($path));

ob_clean();
flush();
readfile($path);

?>