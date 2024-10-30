<?php

/*
Plugin Name: BuddyPress Group Folders
Description: Simple folders for BuddyPress groups
Version: 1.5
Author: Rudolf Enberg
Author URI: http://dalocker.net
*/

if (!defined('ABSPATH')) {exit;}

define ('BP_GFOLD_DIR', dirname(__FILE__).'/' );

if (function_exists('load_plugin_textdomain')) {
	load_plugin_textdomain('bp-gfold', false, dirname( plugin_basename( __FILE__ ) ) . '/bp-gfold-i18n/');
}

$bp_gfold_prefs = array(
	'title'		=> array(
		'label'		=> __('Folder name', 'bp-gfold'),
		'type'		=> 'text',
		'default'	=> __('Files', 'bp-gfold'),
		'sanitize'	=> 'title'
	),
	'groups'	=> array(
		'label'		=> __('Allowed groups', 'bp-gfold'),
		'type'		=> 'text',
		'default'	=> '',
		'sanitize'	=> 'list'
	),
	'mb'		=> array(
		'label'		=> __('Maximum file size', 'bp-gfold'),
		'suffix'	=> __('MB', 'bp-gfold'),
		'type'		=> 'text',
		'default'	=> 7,
		'sanitize'	=> 'float'
	),
	'totalmb'	=> array(
		'label'		=> __('Maximum folder size', 'bp-gfold'),
		'suffix'	=> __('MB', 'bp-gfold'),
		'type'		=> 'text',
		'default'	=> 50,
		'sanitize'	=> 'float'
	),
	'path'		=> array(
		'label'		=> __('Store folders under path', 'bp-gfold'),
		'type'		=> 'text',
		'default'	=> __('gfold', 'bp-gfold'),
		'sanitize'	=> 'path'
	),
	'security'	=> array(
		'label'		=> __('Outsider access', 'bp-gfold'),
		'type'		=> 'security',
		'default'	=> 'none',
		'sanitize'	=> 'security'
	),
	'makespace'	=> array(
		'label'		=> __('When out of space', 'bp-gfold'),
		'type'		=> 'bool',
		'default'	=> 1,
		'sanitize'	=> 'bool',
		'suffix'	=> __('Delete older files automatically', 'bp-gfold')
	)
);

function bp_gfold_gpref($name) {
	global $bp_gfold_prefs;
	return get_option('bp_gfold_'.$name, $bp_gfold_prefs[$name]['default']);
}

function bp_gfold_enabled($gid) {
	$allowed = explode(',', bp_gfold_gpref('groups'));
	foreach ($allowed as $id) {
		if (trim($id) == $gid) {return true;}
	}
	return false;
}

function bp_gfold_foldersize($path) {
	$total_size = 0;
	$files = scandir($path);
	$cleanPath = rtrim($path, '/'). '/';

	foreach($files as $t) {
		if ($t == '.' || $t == '..') {continue;}
		$currentFile = $cleanPath . $t;
		if (is_dir($currentFile)) {
			$size = bp_gfold_foldersize($currentFile);
			$total_size += $size;
		}
		else {
			$size = filesize($currentFile);
			$total_size += $size;
		}  
	}

	return $total_size;
}

function bp_gfold_cmp_date_desc($a, $b) {
	if ($a['date'] == $b['date']) {return 0;}
	return ($a['date'] > $b['date']) ? -1 : 1;
}

function bp_gfold_cmp_date_asc($a, $b) {
	if ($a['date'] == $b['date']) {return 0;}
	return ($a['date'] < $b['date']) ? -1 : 1;
}

class bp_gfold {
	
	public $gid, $title, $slug, $enabled;
	public $plugin_dir, $repo_dir, $repo_root;

	function __construct($group_id = false) {
	
		if ($group_id === false) {return;}
		
		$this->gid = $group_id;
		$this->plugin_dir = WP_PLUGIN_DIR.'/buddypress-gfold/';
		$this->repo_root = WP_CONTENT_DIR.'/'.$this->get_pref('path');
		$this->repo_root_url = WP_CONTENT_URL.'/'.$this->get_pref('path');
		$this->repo_url = $this->repo_root_url.$this->gid.'/';
		$this->repo_dir = $this->repo_root.$this->gid.'/';

		$this->title = $this->get_pref('title');
		$this->slug = sanitize_title_with_dashes($this->title);
		$this->enabled = $this->gfold_enabled($this->gid);

	}
	
	function gfold_enabled($gid) {
		$allowed = explode(',', $this->get_pref('groups'));
		foreach ($allowed as $id) {
			if (trim($id) == $gid) {return true;}
		}
		return false;
	}
	
	function get_pref($name) {
		return bp_gfold_gpref($name);
	}

	function format_filesize($bytes) {
		$suff = explode(',', __('b,kb,MB,GB,TB,PB','bp-gfold'));
		$index = floor(log($bytes)/log(1024));
		$value = ($bytes/pow(1024, $index));
		return round($bytes/pow(1024, $index), max(0, min(2, $index - 1)) ).' '.$suff[$index];
	}
	
	function path($gid) {
		return ABSPATH.$this->repo_path.(($gid === false) ? '' : $gid.'/');
	}
	
	function link($name, $download) {
		if ($download) {
			$link = $this->repo_root_url.'g.php?'.$this->gid.'-'.rawurlencode($name);
		} else {
			$link = $this->repo_url.rawurlencode($name);
		}
		return $link;
	}
	
	function clean_name($name) {
		$extension = strrchr($name, '.');
		if ($extension === false || mb_strlen($extension) > 5) {
			$filename = $name;
			$extension = false;
		} else {
			$filename = mb_substr($name, 0, -mb_strlen($extension));
		}
		$start = mb_strrpos($filename, '__');
		if ($start === false) {return $name;}
		return mb_substr($filename, 0, $start) . $extension;
	}
	
	function file_meta($path) {
		$data = array();
			
		$data['name'] = end(explode('/', $path));
		$data['disp'] = $this->clean_name($data['name']);
		$data['date'] = filectime($path);
		$data['size'] = filesize($path);
		$data['path'] = $path;
		
		return $data;
	}
	
	function fetch($page = 0, $limit = false, $sort = 'date', $order = 'desc') {
		$files = array();
		
		/* repository doesn't exists; return empty array */
		if (!is_dir($this->repo_dir)) {return $files;}
		
		$dir = @opendir($this->repo_dir);
		while (false !== ($item = readdir($dir))) {
			if ($item == '.' || $item == '..' || $item == 'index.php' || $item == 'repository.php') {continue;}
			
			$data = $this->file_meta($this->repo_dir.$item);
			if ($data) {$files[] = $data;}
		}
		
		if ($sort) {
			usort($files, 'bp_gfold_cmp_'.$sort.'_'.$order);
		}

		return $files;
	}
	
	function output_files($files) {
		$count = count($files);
		for ($f = 0; $f < $count; $f++) {
			echo '<tr data-fn="'.esc_attr($files[$f]['name']).'"'.($f % 2 == 0 ? ' class="stripe"':'').'>';
			echo '<td class="title">'.htmlspecialchars($files[$f]['disp']).'</td>';
			echo '<td>'.$this->format_filesize($files[$f]['size']).'</td>';
			echo '<td>'.date('d.n.Y H:i', $files[$f]['date']).'</td>';
			echo '<td class="right"><span><a href="'.esc_attr($this->link($files[$f]['name'], false)).'" target="_blank">'.__('open', 'bp-gfold').'</a><a href="'.esc_attr($this->link($files[$f]['name'], true)).'">'.__('download', 'bp-gfold').'</a><a href="#rn" onclick="bpgfoldrn(this);return false;">'.__('rename', 'bp-gfold').'</a><a href="#del" onclick="bpgfoldd(this);return false;">'.__('delete', 'bp-gfold').'</a></span></td>';
			echo '</tr>';
		}
	}
	
	function repo_make() {
		/* ensure repository folder exists */
		if (!file_exists($this->repo_dir) && !wp_mkdir_p($this->repo_dir)) {return false;}
	
		/* ensure gfold root has download script */
		$path = $this->repo_root.'g.php';
		if (!file_exists($path) && !copy(dirname(__FILE__).'/bp-gfold-download.php', $path)) {return false;}
		
		/* and dummy index */
		$path = $this->repo_root.'index.php';
		if (!file_exists($path)) {
			file_put_contents($path, '<?php ?>');
		}

		/* ensure group repository has dummy index */
		$path = $this->repo_dir.'index.php';
		if (!file_exists($path)) {
			file_put_contents($path, '<?php ?>');
		}
		
		/* give group a private key */
		$path = $this->repo_dir.'repository.php';
		if (!file_exists($path)) {

			/* doesn't matter what the key is, just has to be something random */
			$key = '';
			if (function_exists('wp_generate_password')) {
				$key .= wp_generate_password(10, true, true);
				$key .= substr(md5(microtime() . $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']), 10, 10);
				$key .= wp_generate_password(12, true, true);
			}
			
			if (strlen($key) < 32) {
				$key = substr($key . $this->random_key(32), 0, 32);
			}
			
			if (!function_exists('addslashes')) {
				return false;
			}
			$key = addslashes($key);

			if (!file_put_contents($path, '<?php if (!defined(\'ABSPATH\')) {exit;} '.
			'return array(\'id\' => '.$this->gid.', \'key\' => \''.$key.'\'); ?>')) {
				return false;
			}
		}
		
		return true;
	}

	function clean_filename($name) {
		$name = str_replace('*', '+', $name);
		$name = str_replace('<', '(', str_replace('>', ')', $name));
		$name = str_replace('|', 'l', $name);
		return str_replace(array('/', '\\', ':', '?', '"', "\x00"), '-', $name);
	}
	
	function string_empty($string) {
		if ($string == '' || $string == null) {return true;}
		return (strlen(trim($string)) == 0) ? true : false;
	}
	
	function repo_hash() {
		/* grab private repository data */
		$path = $this->repo_dir.'repository.php';
		if (file_exists($path)) {$data = include $path;}
		
		/* grab current user */
		$user = wp_get_current_user();
		
		/* the string to hash is "grp[1]key[2]usr[3]"
		where [1] is the ID of the group, [2] is the
		private key of the repository (or "NVej6SsC"
		if one doesn't exist yet) and [3] is the ID 
		of the current user. */
		$string = 
		'grp'.$this->gid.
		'key'.($data ? $data['key'] : 'NVej6SsC').
		'usr'.$user->ID;

		/* use the standard wordpress hash */
		if (function_exists('wp_hash')) {
			$hash = wp_hash($string);
			if ($hash && mb_strlen($hash) > 16) {return $hash;}
		}
		
		/* alternative: hash once with md5,
		append "j6/P9s6+" and hash again,
		append "bk6FUuQ2" and hash again */
		return md5(md5(md5($string).'j6/P9s6+').'bk6FUuQ2');
	}
	
	function repo_space_used() {
		return bp_gfold_foldersize($this->repo_dir);
	}
	
	function delete_file($path) {
		if (unlink($this->repo_dir.$path)) {
			return true;
		}
		return false;
	}
	
	function rename_file($old, $new) {
		if (rename($this->repo_dir.$old, $this->repo_dir.$new)) {
			return true;
		}
		return false;
	}
	
	function random_key($length) {
		$chars = '!#()-_~+abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$mchar = strlen($chars) - 1; $key = '';
		
		if (function_exists('wp_rand')) {
			for ($i = 0; $i < $length; $i++) {
				$key .= substr($chars, wp_rand(0, $mchar), 1);
			}
		} else {
			for ($i = 0; $i < $length; $i++) {
				$key .= substr($chars, mt_rand(0, $mchar), 1);
			}
		}
		
		/* ensure it doesn't contain two successive underscores */
		while (strpos($key, '__') !== false) {
			$key = str_replace('__', $this->random_key(2), $key);
		}
		/* ensure it doesn't start with an underscore */
		while (substr($key, 0, 1) == '_') {
			$key = $this->random_key(1).substr($key, 1);
		}
		
		return $key;
	}
	
	function find_filename($name, $original = false) {
		$name = trim($name);
		if ($name == '') {$name = __('Untitled', 'bp-gfold');}
		
		/* remove nasty stuff */
		$name = $this->clean_filename($name);
		$name = str_ireplace('.php', '_php', $name);
		$name = str_ireplace('.htm', '_htm', $name);
		$name = str_ireplace('.htaccess', '_htaccess', $name);
		$name = str_ireplace('php.ini', 'php_ini', $name);
		
		/* separate extension (very primitive but good enough in most cases) */
		$extension = strrchr($name, '.');
		/* consider up to five characters an extension */
		if ($extension === false || mb_strlen($extension) > 5) {
			$filename = $name;
			$extension = false;
		} else {
			$filename = trim(mb_substr($name, 0, -mb_strlen($extension)));
		}
		
		/* truncate long filenames */
		if (mb_strlen($filename) > 50) {
			$filename = mb_substr($filename, 0, 47) . '...';
		}

		/* get a list of every single file - hopefully not too long */
		$files = $this->fetch(0, false, false);
		$len = count($files);
		for ($i = 0; $i < 9; $i++) {
			$lower = mb_strtolower($filename . $extension);
			$taken = false;
			/* compare against each filename */
			for ($f = 0; $f < $len; $f++) {
				/* when renaming, ignore filename collisions with the file itself */
				if ($files[$f]['name'] === $original) {
					continue;
				}
				if ($lower == mb_strtolower($files[$f]['disp'])) {
					$taken = true;
					break;
				}
			}
			if ($taken) {
				if (mb_substr($filename, -1) == ')' && mb_substr($filename, -3, 1) == '(') {
					/* increase suffix */
					$filename = mb_substr($filename, 0, -2) . max(2, ((intval(mb_substr($filename, -2, 1)) + 1) % 10)) . ')';
				} else {
					/* add suffix */
					$filename .= ' (2)';
				}
			} else {
				break;
			}
		}
		
		if ($taken) {return false;}
		
		/* optionally obfuscate filenames */
		if ($this->get_pref('security') == 'urls') {
			$filename = preg_replace('/__+/', '_', $filename) . '__'.$this->random_key(22);
		}
		
		return $filename . $extension;
	}
	
	function repo_space($bytes) {
		$max_repo_size = intval($this->get_pref('totalmb')) * 1024 * 1024 - 200;
		$space_used = $this->repo_space_used();

		/* can fit */
		if ($space_used + $bytes <= $max_repo_size) {
			return true;
		}

		/* can't fit, can't make space */
		if ($bytes > $max_repo_size || $this->get_pref('makespace') == 0) {
			return false;
		}
		
		/* -- remove older files to make space -- */
		
		$space_free = $max_repo_size - $space_used;
		
		for ($n = 0; $n < 1000; $n++) {
			/* grab a bunch of old files */
			$files = $this->fetch(0, 100, 'date', 'asc');
			if (count($files) == 0) {break;} /* no more files */
			/* remove files until enough free space */
			for ($i = 0; $i < 100; $i++) {
				if ($this->delete_file($files[$i]['path'])) {
					$space_free += $files[$i]['size'];
				}
				if ($space_free >= $bytes) {return true;}
			}
		}
		
		return false;
	}
}

if (is_admin()) {

	require BP_GFOLD_DIR.'bp-gfold-admin.php';
	
} else {

	add_action('bp_init', 'bp_gfold_init');
	function bp_gfold_init($e) {
		global $bp;
		/* make sure bp exists and is on a group page */
		if (!isset($bp) || !$bp->groups || !$bp->groups->current_group) {return;}
		
		/* extend with gfold tab when necessary */
		if (bp_gfold_enabled($bp->groups->current_group->id)) {
			require BP_GFOLD_DIR.'bp-gfold-extension.php';
		}
	}
	
}

?>
