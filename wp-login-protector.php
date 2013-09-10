<?php
/*
Plugin Name: WP Login Protector
Plugin URI: http://github.com/supercleanse/wp-login-protector
Description: Simple tools to protect your wordpress login from brute force attacks.
Version: 1.0.0
Author: Blair Williams
Author URI: http://blairwilliams.com
Text Domain: wp-login-protector
Copyright: 2004-2013, Caseproof, LLC

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

wlp_hooks();

function wlp_hooks() {
  add_action('admin_menu', 'wlp_menu');

  if( false==($basic_auth = get_option('wlp_basic_auth')) )     { $basic_auth = (defined('WLP_BASIC_AUTH')?WLP_BASIC_AUTH:'off'); }
  if( false==($filter_http = get_option('wlp_filter_http')) )   { $filter_http = (defined('WLP_FILTER_HTTP')?WLP_FILTER_HTTP:'on'); }
  if( false==($protect_post = get_option('wlp_protect_post')) ) { $protect_post = (defined('WLP_PROTECT_POST')?WLP_PROTECT_POST:'on'); }
  
  // Adds an extra layer of basic auth to the wp-login page
  if( 'on'==$basic_auth ) { add_action('login_init','wlp_basic_auth'); }

  // Filters out requests made with HTTP/1.0
  if( 'on'==$filter_http ) { add_action('login_init','wlp_filter_http'); }

  // Prevents posts to wp-login without a protection cookie in place
  if( 'on'==$protect_post ) {
    add_action('init','wlp_set_login_protection_cookie');
    add_action('login_init','wlp_post_protection');
  }
}

function wlp_menu() {
  add_options_page( __('Login Protector'),
                    __('Login Protector'),
                    'manage_options',
                    'wp-login-protector',
                    'wlp_options' );
}

function wlp_options() {
  if(!is_admin() or !current_user_can('manage_options')) { wp_die(__('Whoa pardner ... you don\'t have access to that')); }

  $method = strtolower($_SERVER['REQUEST_METHOD']);

  if($method == 'get')
    wlp_display_options();
  elseif($method == 'post')
    wlp_process_options();
}

function wlp_display_options($message='') {
  if( false==($basic_auth = get_option('wlp_basic_auth')) )     { $basic_auth = (defined('WLP_BASIC_AUTH')?WLP_BASIC_AUTH:'off'); }
  if( false==($filter_http = get_option('wlp_filter_http')) )   { $filter_http = (defined('WLP_FILTER_HTTP')?WLP_FILTER_HTTP:'on'); }
  if( false==($protect_post = get_option('wlp_protect_post')) ) { $protect_post = (defined('WLP_PROTECT_POST')?WLP_PROTECT_POST:'on'); }

  ?>
  <h2><?php _e('Login Protector'); ?></h2>
  <?php if(!empty($message)): ?>
    <div class="updated" style="margin-left: 0; padding: 10px;"><?php echo $message; ?></div><br/>
  <?php endif; ?>

  <form action="" method="post">
    <label for="wlp_protect_post"><input type="checkbox" name="wlp_protect_post" <?php checked($protect_post=='on'); ?>>&nbsp;&nbsp;<?php _e('POST Cookie Protection'); ?></label>
    <p class="description"><?php _e('This will set a cookie when an initial, GET request is made on the site (which happens when a human logs in). If the Cookie is not present on the POST request then the login is blocked.'); ?></p><br/>
    <label for="wlp_filter_http"><input type="checkbox" name="wlp_filter_http" <?php checked($filter_http=='on'); ?>>&nbsp;&nbsp;<?php _e('Block HTTP/1.0 POSTs'); ?></label>
    <p class="description"><?php _e('Block any login POST requests made with HTTP 1.0. It is common for bots to use HTTP 1.0.'); ?></p><br/>
    <label for="wlp_basic_auth"><input type="checkbox" name="wlp_basic_auth" <?php checked($basic_auth=='on'); ?>>&nbsp;&nbsp;<?php _e('Basic Authentication'); ?></label>
    <p class="description"><?php _e('This will add an extra layer of basic authentication to the WordPress login page. This is a more aggressive approach but should completely prevent any bots from even attempting a wordpress login.'); ?></p><br/>
    <input type="submit" value="<?php _e('Submit'); ?>">
  </form>
  <?php
}

function wlp_process_options() {
  update_option('wlp_basic_auth',   (isset($_POST['wlp_basic_auth']  )?'on':'off'));
  update_option('wlp_filter_http',  (isset($_POST['wlp_filter_http'] )?'on':'off'));
  update_option('wlp_protect_post', (isset($_POST['wlp_protect_post'])?'on':'off'));

  $message = __('Your options have been updated successfully');

  wlp_display_options($message);
}

// Basic auth on wp-login
function wlp_basic_auth() {
  if( !isset($_SERVER['PHP_AUTH_USER']) or !isset($_SERVER['PHP_AUTH_PW']) )
    wlp_unauthorized(__('No credentials have been provided.', 'memberpress'));
  else {
    $user = wp_authenticate($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

    if(is_wp_error($user))
      wlp_unauthorized( $user->get_error_message() );
  }
}

// Basic authentication prompt
function wlp_unauthorized($message) {
  header('WWW-Authenticate: Basic realm="' . get_option('blogname') . '"');
  header('HTTP/1.0 401 Unauthorized');
  die(sprintf(__('UNAUTHORIZED: %s', 'memberpress'),$message));
}

// Filter HTTP/1.0 requests
function wlp_filter_http() {
  if(preg_match('/1\.0/',$_SERVER['SERVER_PROTOCOL'])) { wlp_forbidden(); }
}

// Set a cookie on init so we can test it when a login is posted
function wlp_set_login_protection_cookie() {
  if( strtoupper($_SERVER['REQUEST_METHOD'])=='GET' and
      !isset($_COOKIE['wlp_post_protection']) ) {
    setcookie('wlp_post_protection','1',time()+60*60*24);
    $_COOKIE['wlp_post_protection'] = '1';
  }
}

// Filter POST requests that aren't referred from a request originating on this site
function wlp_post_protection() {
  if( strtoupper($_SERVER['REQUEST_METHOD'])=='POST' and
      !isset($_COOKIE['wlp_post_protection']) ) {
    wlp_forbidden();
  }
}

function wlp_forbidden() {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

