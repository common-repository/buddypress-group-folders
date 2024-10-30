<?php

/** BASIC SETUP **/

$WP_ABSPATH = '/';

ignore_user_abort(true);

header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

/* grab a clean copy of POST data */
$cPOST = $_POST;
/* unescape magic quotes */
if ((function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
	|| (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase')) != 'off'))) {
	foreach ($cPOST as $k => $v) {
		$cPOST[$k] = stripslashes($v);
	}
}

$ajax = $cPOST['ajax'] == 'true' ? true : false;
$bp_gid = intval($cPOST['bp-gfold-group-id']);

/* die with script to execute within iframe */
function die_script($script, $body = '') {
	die ('<!DOCTYPE html><html><head><title></title><script>'.$script.'</script></head><body>'.$body.'</body></html>');
}

function die_ajax($success = false, $info = false) {
	header('Content-Type: application/json');
	$object = array('success' => $success, 'info' => $info);
	die(json_encode($object));
}

function fail($string = false) {
	global $ajax;
	if ($ajax) {
		die_ajax(false, $string);
	} else {
		die_script('top.bp_gfold_upload_event('.
		(($string) ? '\''.$string.'\'' : '').');', '<h1>Error</h1>');
	}
}

function fail_auth() {
	usleep(mt_rand(500000, 1000000));
	fail('auth');
}

function result() {
	global $all_files, $done;
	/* successfull uploads / total files */
	fail('result:'.$done.'/'.count($all_files));
}

function diverse_array($vector) { 
    $result = array(); 
    foreach ($vector as $key1 => $value1) {
        foreach ($value1 as $key2 => $value2) {
            $result[$key2][$key1] = $value2;
		}
	}
    return $result; 
}

/* look for wordpress in the standard location */
$WP_LOAD = dirname(dirname(dirname(dirname(__FILE__)))).'/wp-load.php';
if (!file_exists($WP_LOAD)) {
	/* try manually entered path instead */
	$WP_LOAD = $KNOWN_ABSPATH . 'wp-load.php';
	if (!file_exists($WP_LOAD)) {fail();}
}

/* we need wordpress for authentication */
require ($WP_LOAD);

/* make sure we have gfold */
if (!class_exists('bp_gfold')) {
	fail();
}

$bpgfold = new bp_gfold($bp_gid);

/** AUTHENTICATION **/

/* make sure the user is logged in */
if (!is_user_logged_in()) {fail_auth();}

/* verify the standard wordpress nonce */
if (!wp_verify_nonce($cPOST['bp-gfold-nonce'], 'bp-gfold-'.$bp_gid)) {fail_auth();}

/* verify the repository access hash */
$hash = $bpgfold->repo_hash();
if ($hash == false || $cPOST['bp-gfold-group-hash'] != $hash) {fail_auth();}

$action = $cPOST['bp-gfold-action'];

/** UPLOAD **/
if ($action == 'upload') {

	/* make sure repository exists */
	if (!$bpgfold->repo_make()) {fail();}

	$max_filesize = floatval($bpgfold->get_pref('mb')) * 1024 * 1024;
	$all_files = diverse_array($_FILES['upload']);

	$space_needed = 0;
	$files = array();
	$done = 0;

	/* grab only accepted files for processing */
	foreach ($all_files as $file) {
		if ($file['size'] > $max_filesize || $file['size'] == 0 || $file['error'] != 0) {
			continue;
		}
		$files[] = $file;
		$space_needed += $file['size'];
	}

	/* no files accepted for upload */
	if (count($files) == 0) {result();}

	/* make sure there's enough space for these files */
	/*if (!$bpgfold->repo_space($space_needed)) {fail('space');}*/

	foreach ($files as $file) {
		/* find a safe filename that isn't already taken */
		$name = $bpgfold->find_filename($file['name']);
		if ($name === false) {continue;}
		if (move_uploaded_file($file['tmp_name'], $bpgfold->repo_dir.$name)) {
			$done += 1;
		}
	}

	result();
	
} else {

	switch ($action) {
		case 'rename':
			
			$name = $bpgfold->clean_filename($cPOST['name']);
			$newName = $bpgfold->clean_filename($cPOST['newName']);
			
			if ($bpgfold->string_empty($name) || $bpgfold->string_empty($newName)) {
				fail();
			}

			if (!file_exists($bpgfold->repo_dir.$name)) {
				fail('notfound');
			}
			
			$extension = strrchr($name, '.');
			/* consider only up to five characters an extension */
			if ($extension === false || mb_strlen($extension) > 5) {
				$extension = false;
			}
			
			$newName = $bpgfold->find_filename($newName.$extension, $name);
			if (!$newName) {fail();}
			
			if (!$bpgfold->rename_file($name, $newName)) {fail();}
			
			/* success */
			die_ajax(true, array(
				'disp' => $bpgfold->clean_name($newName),
				'full' => $newName,
				'link' => $bpgfold->link($newName, false),
				'link_download' => $bpgfold->link($newName, true)
			));
			
			break;
		case 'delete':
		
			$file = $bpgfold->clean_filename($cPOST['name']);
			
			if ($bpgfold->string_empty($file)) {
				fail();
			}
			
			if (!file_exists($bpgfold->repo_dir.$file)) {
				fail();
			}
			
			if (!$bpgfold->delete_file($file)) {
				fail();
			}
			
			/* success */
			die_ajax(true);
			
			break;
		default:
			fail();
	}

}

?>
