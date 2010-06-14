<?php
/*
Plugin Name: CommunityAPI Single Signon
Version: 0.98
Plugin URI: http://www.shortpacket.org/community-wp
Description: Plugin to integrate WordPress or WordPressMU with a Community API authentication and authorization source
Author: Kriss Andsten
Author URI: http://www.shortpacket.org/
*/

/* 
 Copyright (C) 2010 Kriss Andsten
 
 This plugin is originally based on the wpCAS plugin and I am grateful
 for the blueprint they provided.
 
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA	 02111-1307	 USA 
*/


require_once( ABSPATH . WPINC . '/registration.php');

$coapi_sso_options = get_option( 'coapi_sso_options' );
add_action( 'admin_menu', 'coapi_sso_options_page_add' );
$com_configured = true;

if ($coapi_sso_options['api_url'] == '') {
	$com_configured = false;
}

// plugin hooks into authentication system
add_filter('authenticate', array('CoAPI', 'authenticate'), 15, 3);
add_action('lost_password', array('CoAPI', 'disable_function'));
add_action('login_form', array('CoAPI', 'single_signon_support'));
add_filter('validate_username', array('CoAPI', 'fix_valid_username'), 10, 2);
add_filter('sanitize_user', array('CoAPI', 'fix_sanitized_username'), 10, 2);


class CoAPI {
	function authenticate($user, $username, $password) {
		global $coapi_sso_options, $com_configured;
		if ( is_a($user, 'WP_User') ) { return $user; }
		if ( !$com_configured ) { return; }
		
		if ($_POST['coapi_sso_authcookie'] == '')
		{
			return CoAPI::authenticateUserByLogin($username, $password);
		}
		else
		{
			return CoAPI::authenticateUserByCookie($_POST['coapi_sso_authcookie']);
		}
	}
	
	// disabled reset, lost, and retrieve password features
	function disable_function() {
		die( __( 'Please reset your forum password instead.', 'coapi' ));
	}
	
	
	function fix_valid_username( $valid, $username ) {
		
		$current_username = $username;
		$username = preg_replace('|[^a-z0-9 _.\-@\[\]ÀÁÂÄÅÆÇÈÉÊËÌÍÎÏĐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]|i', '', $username);
		$valid = ($current_username == $username);
				
		return $valid;
	}
	
	function fix_sanitized_username( $username, $raw ) {
		/*
			Allow iso-8859-1 characters in names 
			(Note: It's probably a very, very bad idea to edit this file 
			in a non-unidode editor
		*/
		$raw = preg_replace('|[^a-z0-9 _.\-@\[\]ÀÁÂÄÅÆÇÈÉÊËÌÍÎÏĐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]|i', '', $raw);
		
		return $raw;
	}
	
	function authenticateUserByCookie($authcookie)
	{
		global $coapi_sso_options, $com_configured;
		if ( !$com_configured ) { return; }
		
	  	$sitecookie = hash('sha256', $coapi_sso_options['api_secret'] . $coapi_sso_options['api_site'] . $authcookie);
		$apiReqUrl =  $coapi_sso_options['api_url'] . '?cmd=verify_cookie&authcookie=' . urlencode($authcookie) . 
		"&site=" . urlencode($coapi_sso_options['api_site']) .
		"&sitecookie=" . urlencode($sitecookie);			
		
		return CoAPI::authenticateUser($apiReqUrl);
	}
	
	function authenticateUserByLogin($username, $password)
	{
		global $coapi_sso_options;
		
		$sitecookie = hash('sha256', $coapi_sso_options['api_secret'] . $username . $password);
		$apiReqUrl = $coapi_sso_options['api_url'] . '?cmd=login&username=' . urlencode($username) . "&password=" . urlencode($password) . "&sitecookie=" . urlencode($sitecookie);
		return CoAPI::authenticateUser($apiReqUrl);
	}
	
	function authenticateUser($apiReqUrl)
	{
		global $coapi_sso_options, $com_configured;
		if ( !$com_configured ) { return; }
		
		$req = curl_init($apiReqUrl);
		curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($req, CURLOPT_HTTPHEADER, array("Content-type: text/xml;charset=UTF-8"));
		$response = curl_exec($req);
		curl_close($req);
		
		$xml = simplexml_load_string($response);
		$usernode = $xml->user[0];
		if (gettype($usernode) != 'object') { return; }
				
		$username = $usernode->attributes()->username;
		$email = $usernode->attributes()->email;
		
		$role = 'none';
		foreach ($usernode->group as $group)
		{
			$gname = $group->attributes()->name;
			
			if ($role == 'none' && $coapi_sso_options['subscriber_group'] == $gname) { $role = 'subscriber'; }
			if ($role != 'administrator' && $coapi_sso_options['editor_group'] == $gname) { $role = 'editor'; }
			if ($coapi_sso_options['admin_group'] == $gname) { $role = 'administrator'; }
		}
		
		if ($role == 'none') { return; }
		
		
		$user = get_userdatabylogin( $username );
		
		/*
			wp_insert_user will overwrite existing data with empty values if
			we don't define all the relevant keys in the array, so we copy them
			from the existing user data block.
		*/
		if (is_array($user))
		{
			foreach ($user as $key => $value)
			{
				$user_array[$key] = $value;
			}
		}
		
		$user_array[user_email] = $email;
		$user_array[user_login] = $username;
		$user_array[role] = $role;
		if ($user->ID) { $user_array['ID'] = $user->ID; }
		wp_insert_user($user_array);
		
		if (! $user->ID) { $user = get_userdatabylogin( $username ); }
		$userObject = new WP_User($user->ID, $username);
		
		return $userObject;
	}
	
	
	
	function single_signon_support() {
		global $coapi_sso_options;		
	?>
	<?php if($_POST['coapi_sso_authcookie'] == '' && $_GET['loggedout'] != 'true') { ?>
		<input type="hidden" name="coapi_sso_authcookie" id="coapi_sso_authcookie" value="" />
		<span id="coapi_sso_status">Attempting single signon...</span><br />
		
		<script type="text/javascript" src="<? echo($coapi_sso_options['api_url'] . '?cmd=get_cookie'); ?>"></script>
		
		<script type="text/javascript">
		window.onload = loginSSO;
		
		function loginSSO()
		{
			if (typeof(communityAuthHasSession) != 'undefined' && communityAuthHasSession != false)
			{
				document.getElementById('user_login').value = 'sso';
				document.getElementById('user_pass').value = 'sso-' + communityAuthHasSession;
				document.getElementById('coapi_sso_authcookie').value = communityAuthHasSession;
				document.loginform.submit();
			}
			else
			{
				document.getElementById('coapi_sso_status').innerText = 'Single signon attempted, failed.';
				document.getElementById('coapi_sso_status').textContent = 'Single signon attempted, failed.';
			}
		};
		</script>
	<?php } ?>
	<?php
	}
}


//----------------------------------------------------------------------------
//		ADMIN OPTION PAGE FUNCTIONS
//----------------------------------------------------------------------------

function coapi_sso_options_page_add() {
	add_options_page( __( 'CommunityAPI', 'coapi' ), __( 'CommunityAPI', 'coapi' ), 8, basename(__FILE__), 'coapi_sso_options_page');
} 

function coapi_sso_options_page() {
	
	// Setup Default Options Array
	$optionarray_def = array(
				 'api_url' => 'yourschool.edu',
				 'api_secret' => '',
				 'subscriber_group' => '',
				 'editor_group' => '',
				 'admin_group' => ''
				 );
	
	if (isset($_POST['submit']) ) {		 
		// Options Array Update
		$optionarray_update = array (
				 'api_url' => $_POST['api_url'],
				 'api_site' => $_POST['api_site'],
				 'api_secret' => $_POST['api_secret'],
				 'subscriber_group' => $_POST['subscriber_group'],
				 'editor_group' => $_POST['editor_group'],
				 'admin_group' => $_POST['admin_group']
		 );

		update_option('coapi_sso_options', $optionarray_update);
	}
	
	// Get Options
	$optionarray_def = get_option('coapi_sso_options');
	
	?>
	<div class="wrap">
	<h2>CommunityAPI Settings</h2>
	<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>&updated=true">
	
	<h4><?php _e( 'Community API settings', 'coapi' ) ?></h4>
	<table width="700px" cellspacing="2" cellpadding="5" class="editform">
		<tr valign="center"> 
			<td style="width: 200px; text-align: right; padding-right: 10px;"><?php _e( 'API url', 'coapi' ) ?></td> 
			<td><input type="text" name="api_url" id="api_url_inp" value="<?php echo $optionarray_def['api_url']; ?>" size="70" /></td>
		</tr>
		<tr valign="center"> 
			<td style="width: 200px; text-align: right; padding-right: 10px;"><?php _e( 'API secret', 'coapi' ) ?></td> 
			<td><input type="text" name="api_secret" id="api_secret_inp" value="<?php echo $optionarray_def['api_secret']; ?>" size="70" /></td>
		</tr>
		<tr valign="center"> 
			<td style="width: 200px; text-align: right; padding-right: 10px;"><?php _e( 'API site', 'coapi' ) ?></td> 
			<td><input type="text" name="api_site" id="api_site_inp" value="<?php echo $optionarray_def['api_site']; ?>" size="70" /></td>
		</tr>
		<tr valign="center"> 
			<td style="width: 200px; text-align: right; padding-right: 10px;"><?php _e( 'Admin group', 'coapi' ) ?></td> 
			<td><input type="text" name="admin_group" id="admin_group_inp" value="<?php echo $optionarray_def['admin_group']; ?>" size="70" /></td>
		</tr>
		<tr valign="center"> 
			<td style="width: 200px; text-align: right; padding-right: 10px;"><?php _e( 'Editor group', 'coapi' ) ?></td> 
			<td><input type="text" name="editor_group" id="editor_group_inp" value="<?php echo $optionarray_def['editor_group']; ?>" size="70" /></td>
		</tr>
		<tr valign="center"> 
			<td style="width: 200px; text-align: right; padding-right: 10px;"><?php _e( 'Subscriber group', 'coapi' ) ?></td> 
			<td><input type="text" name="subscriber_group" id="subscriber_group_inp" value="<?php echo $optionarray_def['subscriber_group']; ?>" size="70" /></td>
		</tr>
	</table>
	
	<div class="submit">
		<input type="submit" name="submit" value="<?php _e('Update Options') ?> &raquo;" />
	</div>
	</form>
<?php
}
?>
