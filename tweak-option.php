<?php
/*
Plugin Name: Tweak Option
Description: Easily manage any WP option.
Version: 1.4
Author: J.N. Breetvelt a.k.a OpaJaap
Author URI: http://www.opajaap.nl/
Plugin URI: http://wordpress.org/extend/plugins/tweak-option/
*/

/* ADMIN MENU */
add_action( 'admin_menu', 'twop_add_admin' );

function twop_add_admin() {
    add_management_page( __('Tweak Option', 'twop'), __('Tweak Option', 'twop'), 'manage_options', 'tweak_option', 'tweak_option_proc' );
	add_action('admin_head', 'twop_add_js');
}

global $wpdb;
define ( 'TWOPTIONS', $wpdb->prefix . 'options' );

function twop_add_js() {

	echo '
		<script type="text/javascript">
			var twopValueBackup = new Array();
			var twopAutoBackup = new Array();
			var twopHtmlBackup = new Array();
			function twopAjaxDelete(option) {
				var xmlhttp = new XMLHttpRequest();
				var ajaxurl = "'.admin_url('admin-ajax.php').'?action=twop&twop-action=delete&twop-option="+option+"&tweak_option="+document.getElementById("tweak_option").value+"&_wp_http_referer=/wp-admin/tools.php?page=tweak_option";
				
				xmlhttp.onreadystatechange = function() {
					if ( xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
						if ( xmlhttp.responseText == "Done" ) {
							twopHtmlBackup[option] = jQuery("#"+option).html();
							jQuery("#"+option).html("<td>"+option+"</td><td style=\"color:red; font-weight:bold;\" >Removed</td><td></td><td></td><td style=\"color:green; font-weight:bold; cursor:pointer;\" onclick=\"twopAjaxUndo(\'"+option+"\')\">Undo</td>");
						}
						else {
							alert(xmlhttp.responseText);
						}
						jQuery("#twop-message").html("");
					}
				}
				
				xmlhttp.open("GET",ajaxurl,true);
				xmlhttp.send();	

			}
			function twopAjaxUndo(option) {
				var xmlhttp = new XMLHttpRequest();
				var ajaxurl = "'.admin_url('admin-ajax.php').'";
				var data = "action=twop&twop-action=undo&twop-option="+option+"&tweak_option="+document.getElementById("tweak_option").value+"&_wp_http_referer=/wp-admin/tools.php?page=tweak_option";
				data += "&option-value="+encodeURIComponent(twopValueBackup[option])+"&option-autoload="+twopAutoBackup[option];
				xmlhttp.onreadystatechange = function() {
					if ( xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
						if ( xmlhttp.responseText == "Done" ) {
							jQuery("#"+option).html(twopHtmlBackup[option]);
						}
						else {
							alert(xmlhttp.responseText);
						}
						jQuery("#twop-message").html("");
					}
				}
				
				xmlhttp.open("POST",ajaxurl,true);
				xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
				xmlhttp.send(data);	

			}
		</script>
		';
}

function tweak_option_proc() {
global $wpdb;

	// IF Submitted, check admin referrer
	if ( isset( $_POST['submit'] ) ) {
		check_admin_referer( 'twop', 'tweak_option' );
		if ( isset($_POST['twop_from']) ) update_option('twop_from', $_POST['twop_from']);
		//		print_r($_POST);	// Diagnostic
	}
	
	// Go for editor?
	if ( isset( $_POST['submit'] ) && $_POST['submit'] == 'Edit' ) {
		twop_edit_option($_POST['edititem']);
	}	
	// New option ?
	elseif ( isset( $_POST['submit'] ) && $_POST['submit'] == 'Add new option' ) {
		twop_edit_option('');
	}

	else {
		// Delete ?
		if ( isset( $_POST['submit'] ) && $_POST['submit'] == 'Delete' ) {
			delete_option($_POST['delitem']);
			echo '<h3 id="twop-message" style="color:red; font-weight:bold" >"'.$_POST['delitem'].'" deleted<br /></h3>';
		}
		// Save changes ?
		if ( isset( $_POST['submit'] ) && $_POST['submit'] == 'Save' ) {
			if ( isset( $_POST['twop-new'] ) ) {
				update_option($_POST['name'], html_entity_decode(stripslashes($_POST['value'])));	// First create
			}
			$iret = $wpdb->query($wpdb->prepare("UPDATE `".TWOPTIONS."` SET `option_value` = %s, `autoload` = %s WHERE `option_name` = %s", html_entity_decode(stripslashes($_POST['value'])), $_POST['autoload'], $_POST['name']));
			if ( $iret !== false ) echo '<h3 id="twop-message" style="color:green; font-weight:bold" >"'.$_POST['name'].'" saved<br /></h3>';
			else echo '<h3 id="twop-message" style="color:red; font-weight:bold" >Saving "'.$_POST['name'].'" failed<br /></h3>';
		}
		// Cancel changes ?
		if ( isset( $_POST['submit'] ) && $_POST['submit'] == 'Cancel' ) {
			echo '<h3 id="twop-message" style="color:blue; font-weight:bold" >Saving "'.$_POST['name'].'" cancelled<br /></h3>';
		}

		// Display table
		$from = get_option('twop_from', '');
		$query = $wpdb->prepare('SELECT * FROM `' . TWOPTIONS . '` WHERE `option_name` >= %s ORDER BY `option_name`', $from);
		$options = $wpdb->get_results( $query, 'ARRAY_A' ); 
		?>
		<div class="wrap" >
			<div id="icon-tools" class="icon32">
				<br />
			</div>
			<h2>Tweak Option</h2>
			<form action="<?php echo get_admin_url().'admin.php?page=tweak_option' ?>" method="post">
				<?php wp_nonce_field( 'twop', 'tweak_option' ) ?>
				<?php if ( $options ) {
					echo '
						<input name="delitem" id="delitem" type="hidden" value="nil" />
						<input name="edititem" id="edititem" type="hidden" value="nil" />';
					echo 'Starting from: <input type="text" name="twop_from" value="'.$from.'" /><input name="submit" type="submit" value="Update" />
						<input name="submit" type="submit" class="button-primary" value="Add new option" />
						';
					echo '<div id="twopt_table" class="wrap" style="padding: 0px; display: block;">
							<table class="widefat" >
								<thead style="font-weight: bold;" >
									<tr>
										<th>ID</th>
										<th>Option name</th>
										<th>Option value</th>
										<th>Autoload</th>
										<th>Edit</th >
										<th>Delete</th>
									</tr>
								</thead>
								<tbody>';
						
								foreach ( $options as $option ) { 
									echo '
									<script type="text/javascript">
										twopValueBackup["'.$option['option_name'].'"] = "'.esc_js($option['option_value']).'";
										twopAutoBackup["'.$option['option_name'].'"] = "'.$option['autoload'].'";
									</script>
									';
									if ( strlen($option['option_name']) > 50 ) {
										$name = substr($option['option_name'],0,50).'...';
									}
									else {
										$name = $option['option_name'];
									}
									if ( strlen($option['option_value']) > 80 ) {
										$value = substr($option['option_value'],0,80).'...';
									}
									else {
										$value = $option['option_value'];
									}
									echo '<tr id="'.$option['option_name'].'">
											<td>'.$option['option_id'].'</td>
											<td title="'.$option['option_name'].'" style="cursor:pointer;" >'.$name.'</td>
											<td title="'.esc_attr($option['option_value']).'" style="cursor:pointer;" >'.htmlspecialchars($value).'</td>
											<td>'.$option['autoload'].'</td>
											<td>
												<input name="submit" type="submit" value="Edit" style="padding:0; margin:0; border:none; color:blue; font-weight:bold; cusor:pointer;" onclick="document.getElementById(\'edititem\').value=\''.$option['option_name'].'\'" />
											</td>
											<td>';
												if ( $option['option_name'] != 'active_plugins' ) echo '
												<span style="color:red; font-weight:bold; cursor:pointer;" onclick="if (confirm(\'Are you sure you want to delete '.$option['option_name'].'?\')) { twopAjaxDelete(\''.$option['option_name'].'\'); } " >Delete</span>';
												echo '
											</td>
										</tr>';
								}
								echo
								'</tbody>
							</table>
						</div>';
				}
				?>
			</form>
		</div>
		<?php
	}
}

function twop_edit_option($option) {
global $wpdb;

	$new = ( $option == '' );
	
	$data = $wpdb->get_row($wpdb->prepare("SELECT * FROM `" . TWOPTIONS . "` WHERE `option_name` = %s", $option), 'ARRAY_A');
	if ( ! $data ) $data = array( 'option_name' => 'The name of the option', 'option_value' => 'Enter the option value', 'autoload' => 'yes' );
	?>
		<div class="wrap" >
			<div id="icon-tools" class="icon32">
				<br />
			</div>
			<h2>Tweak Option, Edit <span style="color:green;" ><?php echo $option ?></span></h2>
	
			<form action="<?php echo get_admin_url().'admin.php?page=tweak_option' ?>" method="post" >
				<?php wp_nonce_field( 'twop', 'tweak_option' ) ?>
				<?php if ( $option ) { ?><input type="hidden" name="name" value="<?php echo $data['option_name'] ?>" /><?php } ?>
				<?php if ( $new ) { ?><input type="hidden" name="twop-new" value="new" /><?php } ?>
				<table class="widefat" >
					<tr>
						<td style="font-weight:bold;" >Name</td>
						<td>
							<?php if ( $option ) echo $data['option_name']; else echo '<input type="text" name="name" style="width:100%; " value="'.$data['option_name'].'" />' ?>
						</td>
					</tr>
					<tr>
						<td style="font-wight:bold;" >Value</td>
						<td><textarea name="value" style="width:100%; height:300px; " ><?php echo esc_textarea($data['option_value']) ?></textarea></td>
					</tr>
					<tr>
						<td style="font-weight:bold" >Autoload</td>
						<td>
							<select name="autoload" >
								<option value="yes" <?php if ( $data['autoload'] == 'yes' ) echo 'selected="selected" ' ?>>Yes</option>
								<option value="no" <?php if ( $data['autoload'] == 'no' ) echo 'selected="selected" ' ?>>No</option>
							</select>
						</td>
					</tr>
				</table>
				<input name="submit" type="submit" class="button-primary" value="Save" />
				<input name="submit" type="submit" class="button-primary" value="Cancel" />
			</form>
			
		</div>
		
	<?php	
}

add_action('wp_ajax_twop', 'twop_ajax_callback');

function twop_ajax_callback() {
global $wpdb;

	check_admin_referer( 'twop', 'tweak_option' );
	
	$action = $_REQUEST['twop-action'];
	$option = $_REQUEST['twop-option'];
	
	switch ( $action ) {
		case 'delete':
			delete_option($option);
			echo 'Done';
			break;
		case 'undo':
			$value = html_entity_decode(stripslashes($_REQUEST['option-value']));
			$autoload = $_REQUEST['option-autoload'];
			$iret = $wpdb->query($wpdb->prepare("INSERT INTO `".TWOPTIONS."` (`option_name`, `option_value`, `autoload`) VALUES(%s, %s, %s)", $option, $value, $autoload));
			if ( $iret !== false ) echo 'Done';
			else echo 'Error: unable to undo delete '.$option;
			break;
		default:
			echo 'Non implemented action';
	}
	exit;
}
