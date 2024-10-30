<?php

if (!defined('ABSPATH')) {exit;}

$bpgfold = new bp_gfold;

add_action('admin_menu', 'bp_gfold_admin_menu');
add_action('admin_init', 'bp_gfold_admin_init');

function bp_gfold_admin_menu() {
	add_options_page(__('Group Folders', 'bp-gfold'), __('Group Folders', 'bp-gfold'), 'edit_plugins', 'bp-gfold-admin', 'bp_gfold_admin_page_render');
}

function bp_gfold_admin_init() {
	global $bp_gfold_prefs;
	
 	add_settings_section(
		'bp_gfold_basic',
		null,
		null,
		'bp_gfold'
	);

	foreach ($bp_gfold_prefs as $key => $pref) {
		add_settings_field(
			'bp_gfold_'.$key,
			$pref['label'],
			'bp_gfold_input_'.$pref['type'],
			'bp_gfold',
			'bp_gfold_basic',
			array('bp_gfold_'.$key, bp_gfold_gpref($key), isset($pref['suffix']) ? $pref['suffix'] : '')
		);
		
		register_setting('bp_gfold', 'bp_gfold_'.$key, (isset($pref['sanitize'])) ? 'bp_gfold_sanitize_'.$pref['sanitize'] : null);
	}
}

function bp_gfold_sanitize_path($input) {
	global $bp_gfold_prefs;
	$input = trim($input, '/');
	if (trim($input) == '') {
		$input = $bp_gfold_prefs['path']['default'];
	}
	return trim($input, '/').'/';
}

function bp_gfold_sanitize_float($input) {
	return floatval(str_replace(',', '.', $input));
}

function bp_gfold_sanitize_list($input) {
	$input = explode(',', $input);
	$clean = array();
	foreach ($input as $item) {
		$item = trim($item);
		if ($item) {
			$clean[] = $item;
		}
	}
	return implode(', ', array_unique($clean));
}

function bp_gfold_sanitize_title($input) {
	global $bpgfold;
	$input = trim($input);
	if ($input == '') {$input = $bpgfold->prefs['title']['default'];}
	return $input;
}

function bp_gfold_sanitize_bool($input) {
	return ($input == '1' || $input == 'on') ? 1 : 0;
}

function bp_gfold_sanitize_security($input) {
	return ($input == 'urls') ? 'urls' : 'none';
}

function bp_gfold_input_text($data) {
	echo '<input name="'.$data[0].'" id="'.$data[0].'" type="text" value="'.esc_attr($data[1]).'">'.(($data[2]) ? ' <span>'.$data[2].'</span>' : '');
}

function bp_gfold_input_bool($data) {
	if ($data[2]) {echo '<label for="'.$data[0].'">';}
	echo '<input name="'.$data[0].'" id="'.$data[0].'" type="checkbox"'.(($data[1] == 1) ? ' checked' : '').'>';
	if ($data[2]) {echo ' '.$data[2].'</label>';}
}

function bp_gfold_input_security($data) {
	echo '<select name="'.$data[0].'" id="'.$data[0].'">';
	echo '<option value="none">'.__('Normal', 'bp-gfold').'</option>';
	echo '<option value="urls"'.(($data[1] == 'urls') ? ' selected' : '').'>'.__('Restricted', 'bp-gfold').'</option>';
	echo '</select>';
	echo '<p class="description"><b>'.__('Normal', 'bp-gfold').':</b> '.__('Anyone can access a file if they know the precise filename.', 'bp-gfold').'<br/><b>'.__('Restricted', 'bp-gfold').':</b> '.__('Filenames are obfuscated to be difficult to guess.', 'bp-gfold').'</p>';
}

function bp_gfold_admin_page_render() {

	echo '<div class="wrap">
	<h2>'. __('Group Folders', 'bp-gfold') .'</h2>
	
	<form method="POST" action="options.php">';

	settings_fields('bp_gfold');
	do_settings_sections('bp_gfold');
	submit_button();
	
	echo '
		</form>
	</div>';
	
}

?>
