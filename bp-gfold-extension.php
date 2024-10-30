<?php

if (!defined('ABSPATH')) {exit;}

class gfold_Group_Extension extends BP_Group_Extension {

	public $visibility = 'private';
	public $enable_admin_item = false;
	public $enable_edit_item = false;
	public $gid;
	private $gfold;

	function __construct() {
		global $bp;
		
		$this->gid = $bp->groups->current_group->id;
		
		$this->gfold = new bp_gfold($this->gid);
		$this->name = $this->gfold->title;
		$this->slug = $this->gfold->slug;

		add_action('wp_enqueue_scripts', 'bp_gfold_assets');
	}

	function display() {
		$msg = false;
		
		$page = get_query_var('name');
		if (substr($page, 0, 7) == 'upload-') {
			$success = explode('-', substr($page, 7));
			$total = intval($success[1]); $success = intval($success[0]);
			if ($success == 0) {
				$msg = __('Upload failed.', 'bp-gfold');
			} else {
				if ($success == $total) {
					$msg = $total > 1 ? __('Done!', 'bp-gfold') : __('All done!', 'bp-gfold');
				} else {
					$msg = $success == 1 ? __('1 file uploaded successfully.', 'bp-gfold') : __('%s files uploaded successfully.', 'bp-gfold');
				}
			}
			$msg = str_replace('%s', $success, $msg);
			$msg = str_replace('%t', $total, $msg);
		}

		echo '<div id="gfold_top"><form id="gfold_form" method="POST" action="'.plugins_url('bp-gfold-worker.php', __FILE__).'" target="gfold_uploader" enctype="multipart/form-data" onsubmit="return bp_gfold_onsubmit();"><input type="hidden" name="bp-gfold-action" value="upload"><input type="hidden" name="bp-gfold-group-id" id="bp-gfold-group-id" value="'.$this->gid.'" /><input type="hidden" name="bp-gfold-group-hash" id="bp-gfold-group-hash" value="'.esc_attr($this->gfold->repo_hash()).'" />';
		
		wp_nonce_field('bp-gfold-'.$this->gid, 'bp-gfold-nonce');
		
		echo '<input type="hidden" name="MAX_FILE_SIZE" value="'.($this->gfold->get_pref('mb')*1024*1024).'" /><input type="file" name="upload[]" id="gfold_file" multiple required /> <input id="gfold_submit" type="submit" value="'.esc_attr(__('Upload', 'bp-gfold')).'" /><span id="gfold_result" '.(($msg) ? 'style="display: inline-block;"' : '').' class="success">';
		
		if ($msg) {
			echo $msg;
		}
		
		echo '</span></form><iframe id="gfold_uploader" name="gfold_uploader" onerror="bp_gfold_result(false, false);"></iframe><input type="hidden" id="bp-gfold-loader" value="'.plugins_url('bp-gfold-loader.gif', __FILE__).'">';
		
		$loc = array(
			'uploading' => __('Uploading', 'bp-gfold'),
			'fail'	=> __('Upload failed', 'bp-gfold'),
			'auth_fail' => __('Authentication error', 'bp-gfold'),
			'delete_dialog' => __('Confirm delete: "%s"', 'bp-gfold'),
			'rename_dialog' => __('Rename "%s":', 'bp-gfold'),
			'delete_fail' => __('Failed to delete.', 'bp-gfold'),
			'rename_fail' => __('Failed to rename.', 'bp-gfold'),
			'rename_notfound' => __('Failed to rename. The file may have just been renamed or deleted.', 'bp-gfold')
		);
		echo '<input type="hidden" id="bp-gfold-l10n" value="'.esc_attr(json_encode($loc)).'">';
		echo '</div>';
		
		$files = $this->gfold->fetch($page);
		if (count($files) == 0) {
			echo '<br/>'.__('(nothing yet)', 'bp-gfold');
		} else {
			echo '<table id="gfold_table">';
			$this->gfold->output_files($files);
			echo '</table>';
		}
		unset($files);
	}
	
	/*function edit_screen() {
		echo '<h4>'.__('Delete all files', 'bp-gfold').'</h4>';

		wp_nonce_field('bp-gfold-dall-'.$this->gid);
		
		echo '<input type="hidden" name="bp-gfold-group-hash" id="bp-gfold-group-hash" value="'.esc_attr($this->gfold->repo_hash()).'" /><input type="hidden" name="bp-gfold-group-id" id="bp-gfold-group-id" value="'.$this->gid.'" />';
		
		echo '<p><label for="bp-gfold-captcha">There is no recovery option. Confirm by typing the smallest prime number</label><input type="text" id="bp-gfold-captcha" name="bp-gfold-captcha"></p><p><input type="submit" value="'.__('Delete all', 'bp-gfold').'"></p>';

	}*/
	
}

function bp_gfold_assets() {
	wp_register_style('bp-gfold.css', plugins_url('bp-gfold.css', __FILE__));
	wp_enqueue_style('bp-gfold.css');
	
	wp_register_script('bp-gfold.js', plugins_url('bp-gfold.js', __FILE__));
	wp_enqueue_script('bp-gfold.js');
}

bp_register_group_extension('gfold_Group_Extension');

?>
