<?php
/*
Plugin Name: EPA TIC Admin
Plugin URI: http://www.sandorkovacs.ro/ip-ban-wordpress-plugin/
Description: Ban one or more Ip Address or User Agents. Also you may add an IP RANGE to iplist ex: 82.11.22.100-82.11.22-177
Author: Sandor Kovacs
Version: 1.3.0
Author URI: http://sandorkovacs.ro/en/
*/

// Do the magic stuff
add_action( 'plugins_loaded', 'simple_ip_ban' );

add_action( 'admin_init', 'simple_ip_ban_init' );
add_action('admin_menu', 'register_simple_ip_ban_submenu_page');

function simple_ip_ban_init() {
   /* Register our stylesheet. */
   wp_register_style( 'ip-ban', plugins_url('ip-ban.css', __FILE__) );
   wp_enqueue_style('ip-ban');
}

function register_simple_ip_ban_submenu_page() {
    add_submenu_page(
        'options-general.php', __('EPA TIC Admin'), __('EPA TIC Admin'),
        'manage_options',
        'simple-ip-ban',
        'simple_ip_ban_callback' );
}

function simple_ip_ban_callback() {

    // By Default activate do not redirect for logged in users
    if (!get_option('s_not_for_logged_in_user'))    update_option('s_not_for_logged_in_user', 1);

    // form submit  and save values
    if (isset( $_POST['_wpprotect'] )
        && wp_verify_nonce( $_POST['_wpprotect'], 'ipbanlist' ) ) {
        $ip_list                = wp_kses($_POST['ip_list'], array());
        $ua_list                = wp_kses($_POST['user_agent_list'], array());
	$dev_mode               = sanitize_text_field($_POST['dev_mode']);
        //$redirect_url           = sanitize_text_field($_POST['redirect_url']);
        //$not_for_logged_in_user = sanitize_text_field($_POST['not_for_logged_in_user']);

        update_option('s_ip_list',                $ip_list);
        update_option('s_ua_list',                $ua_list);
	update_option('s_dev_mode',                $dev_mode);
        //update_option('s_redirect_url',           $redirect_url);
        //update_option('s_not_for_logged_in_user', $not_for_logged_in_user);
    }

    // read values from option table

    $ip_list      = get_option('s_ip_list');
    $ua_list      = get_option('s_ua_list');
    $dev_mode      = get_option('s_dev_mode');
    //$redirect_url = get_option('s_redirect_url');
    //$not_for_logged_in_user = (intval(get_option('s_not_for_logged_in_user')) == 1 ) ? 1 : 0;


?>

<div class="wrap" id='simple-ip-list'>
    <div class="icon32" id="icon-options-general"><br></div><h2><?php _e('EPA TIC Admin'); ?></h2>

    <p>
        <?php _e('Add ip address or/and user agents in the textareas. Add only 1 item per line.
        User not falling under the specified IPs or ranges will be redirected to the homepage when attempting to access the admin section of the site.' ) ?>
    </p>

    <p>
        <?php _e('or add an IP RANGE, ex:  <strong>82.11.22.100-82.11.22-177</strong>' ) ?>
    </p>

    <form action="" method="post">

    <p>
    <label for='ip-list'><?php _e('IP List'); ?></label> <br/>
    <textarea name='ip_list' id='ip-list'><?php echo $ip_list ?></textarea>
    </p>

    <p>
    <label for='user-agent-list'><?php _e('User Agent List'); ?></label> <br/>
    <textarea name='user_agent_list' id='user-agent-list'><?php echo $ua_list ?></textarea>
    </p>

   

    <?php wp_nonce_field('ipbanlist', '_wpprotect') ?>
	    
    <p>
    <label for='dev-mode'><?php _e('Developer Mode'); ?></label> <br/>
    <input type='checkbox' name='dev_mode' id='dev-mode' <?php checked( $dev_mode, 1 ); ?> value='1'>
    </p>   

    <p>
        <input type='submit' name='submit' value='<?php _e('Save') ?>' />
    </p>


    </form>

</div>

<?php

}



function simple_ip_ban() {

    // Do nothing for admin user
    //if ((is_user_logged_in() && is_admin()) ||
   //     (intval(get_option('s_not_for_logged_in_user')) == 1  && is_user_logged_in())) return '';




 // Obtain IP Address
if (!empty($_SERVER['HTTP_CLIENT_IP']))
{
	// check ip from share internet
	$remote_ip = $_SERVER['HTTP_CLIENT_IP'];
}
elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
{
	// to check ip is pass from proxy
	$remote_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
else
{
	$remote_ip = $_SERVER['REMOTE_ADDR'];
}

    $remote_ua = $_SERVER['HTTP_USER_AGENT'];

 if (s_check_ip_address($remote_ip, get_option('s_ip_list')) ||
        s_check_user_agent($remote_ua,get_option('s_ua_list'))) {
if ( simple_ip_ban_get_current_url() ==  home_url() ) return '';  //suggested by umchal
} else {
	
if ( ! empty( get_option('s_dev_mode') ) ) {
    header("Location: 404.php");
	exit();
} else {
    wp_redirect( home_url() );
}	 
	 
show_admin_bar(false);
//exit;
}

}

/**
 * Check for a given ip address.
 *
 * @param: string $ip The ip adddress
 * @param: string $ip_list The list with the banned ip addresss
 *
 * @return: boolean If founded it will return true, otherwise false
 **/

function s_check_ip_address($ip, $ip_list) {

    $list_arr = explode("\r\n", $ip_list);

    // Check for exact IP
    if (in_array($ip, $list_arr)) return true;

    // Check in IP range
    foreach ($list_arr as $k => $v) {
        if (substr_count($v, '-')) {
            // It's an ip range
            $curr_ip_range = explode('-', $v);
            /* Watchout for IPs as negative numbers
              Inspired by http://stackoverflow.com/questions/29108058/ip-range-comparison-using-ip2long
            */
            $high_ip = ip2long(trim($curr_ip_range[1]));
            $low_ip = ip2long(trim($curr_ip_range[0]));
            $checked_ip = ip2long($ip);
            if (sprintf("%u", $checked_ip) <= sprintf("%u", $high_ip)  &&
                sprintf("%u", $low_ip) <= sprintf("%u", $checked_ip)) return true;
        }
    }
    // Enable Wildcard Search in IP List	
        foreach($list_arr as $i){
            $wildcardPos = strpos($i, "*");
            # Check if the ip has a wildcard
            if($wildcardPos !== false && substr($ip, 0, $wildcardPos) . "*" == $i)
                return true;
        }


    return false;
}



function s_check_user_agent($ua, $ua_list) {
    $list_arr = explode("\r\n", $ua_list);
    if (in_array($ua, $list_arr)) return true;

    return false;
}


// Suggested solution by umchal
// Support link: http://wordpress.org/support/topic/too-many-redirects-22

function simple_ip_ban_get_current_url() {
	$pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
	if ($_SERVER["SERVER_PORT"] != "80")
	{
	    $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	}
	else
	{
	    $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}
