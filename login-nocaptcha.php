<?php
/*
Plugin Name: Login No Captcha reCAPTCHA
Plugin URI: https://wordpress.org/plugins/login-recaptcha/
Description: Adds a Google reCAPTCHA No Captcha checkbox to the login form, thwarting automated hacking attempts
Author: Robert Peake
Version: 1.1.10
Author URI: http://www.robertpeake.com/
Text Domain: login_nocaptcha
Domain Path: /languages/
*/

if(false === function_exists('add_action')) die();


class LoginNocaptcha {

    public static function init() {
        add_action('plugins_loaded', ['LoginNocaptcha', 'load_textdomain'   ]  );
        add_action('admin_menu',     ['LoginNocaptcha', 'register_menu_page']  );
        add_action('admin_init',     ['LoginNocaptcha', 'register_settings' ]  );
        add_action('admin_notices',  ['LoginNocaptcha', 'admin_notices'     ]  );

        if (LoginNocaptcha::valid_key_secret(get_option('login_nocaptcha_key')) && 
            LoginNocaptcha::valid_key_secret(get_option('login_nocaptcha_secret')) ) {
            add_action('login_enqueue_scripts', ['LoginNocaptcha', 'enqueue_scripts_css']        );
            add_action('admin_enqueue_scripts', ['LoginNocaptcha', 'enqueue_scripts_css']        );
            add_action('login_form',            ['LoginNocaptcha', 'nocaptcha_form'     ]        );
          //add_action('lostpassword_form',     ['LoginNocaptcha', 'nocaptcha_form'     ]        );
            add_action('authenticate',          ['LoginNocaptcha', 'authenticate'       ], 30, 3 );
          //add_action('lostpassword_post',     ['LoginNocaptcha', 'authenticate'       ], 30, 3 );
        }
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'login_nocaptcha', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public static function register_menu_page(){
        add_options_page( __('Login NoCatpcha Options','login_nocaptcha'), __('Login NoCaptcha','login_nocaptcha'), 'manage_options', plugin_dir_path(  __FILE__ ).'admin.php');
    }

    public static function register_settings() {

        /* user-configurable values */
        add_option('login_nocaptcha_key', '');
        add_option('login_nocaptcha_secret', '');
        
        /* user-configurable value checking public static functions */
        register_setting( 'login_nocaptcha', 'login_nocaptcha_key', 'LoginNocaptcha::filter_string' );
        register_setting( 'login_nocaptcha', 'login_nocaptcha_secret', 'LoginNocaptcha::filter_string' );

        /* system values to determine if captcha is working and display useful error messages */
        add_option('login_nocaptcha_working', false);
        add_option('login_nocaptcha_error', sprintf(__('Login NoCaptcha has not been properly configured. <a href="%s">Click here</a> to configure.','login_nocaptcha'), 'options-general.php?page=login-recaptcha/admin.php'));
        add_option('login_nocaptcha_message_type', 'update-nag');
        if (LoginNocaptcha::valid_key_secret(get_option('login_nocaptcha_key')) && 
           LoginNocaptcha::valid_key_secret(get_option('login_nocaptcha_secret')) ) {
            update_option('login_nocaptcha_working', true);
        } else {
            update_option('login_nocaptcha_working', false);
            update_option('login_nocaptcha_message_type', 'update-nag');
            update_option('login_nocaptcha_error', sprintf(__('Login NoCaptcha has not been properly configured. <a href="%s">Click here</a> to configure.','login_nocaptcha'), 'options-general.php?page=login-recaptcha/admin.php'));
        }
    }

    public static function filter_string( $string ) {
        return trim(filter_var($string, FILTER_SANITIZE_STRING)); //must consist of valid string characters
    }

    public static function valid_key_secret( $string ) {
      if(40 === strlen($string)) return true;
      return false;
    }

    public static 
    function register_scripts_css() {
      //wp_register_script('login_nocaptcha_google_api', 'https://www.google.com/recaptcha/api.js?hl='.get_locale() );
      wp_register_script('login_nocaptcha_google_api', 'https://www.google.com/recaptcha/api.js'); //let client-side figure out user-language against always updating list in api.js source (also available at: https://developers.google.com/recaptcha/docs/language )
      wp_register_style('login_nocaptcha_css', plugin_dir_url( __FILE__ ) . 'css/style.css');
    }

    public static 
    function enqueue_scripts_css() {
      if(!wp_script_is('login_nocaptcha_google_api','registered')) {
          LoginNocaptcha::register_scripts_css();
      }
      wp_enqueue_script('login_nocaptcha_google_api');
      wp_enqueue_style('login_nocaptcha_css');
    }

    public static 
    function get_google_errors_as_string( $g_response ) {
      $string = '';
      $codes = ['missing-input-secret'   => __('The secret parameter is missing.',                 'login_nocaptcha')
               ,'invalid-input-secret'   => __('The secret parameter is invalid or malformed.',    'login_nocaptcha')
               ,'missing-input-response' => __('The response parameter is missing.',               'login_nocaptcha')
               ,'invalid-input-response' => __('The response parameter is invalid or malformed.',  'login_nocaptcha')
               ,'bad-request'            => __('The request is invalid or malformed.',             'login_nocaptcha')
               ];

      foreach($g_response->{'error-codes'} as $code) {
          $string .= $codes[$code].' ';
      }
      return trim($string);
    }

    public static 
    function nocaptcha_form() {
      $source<<<SOURCE
<div class="g-recaptcha" data-sitekey="##LOGIN_NOCAPTCHA_KEY##" data-callback="submitEnable" data-expired-callback="submitDisable"></div>
<script type="text/javascript">
  function submitEnable(){  document.querySelector("#wp-submit").removeAttribute("disabled");         }
  function submitDisable(){ document.querySelector("#wp-submit").setAttribute("disabled","disabled"); }
  function docready(fn){/in/.test(document.readyState)?setTimeout('docready('+fn+')',9):fn()}
  
  docready(function() {submitDisable();});
</script>
<noscript>
  <div>
    <div>
      <div class="iframe_container">
        <iframe referrerpolicy="unsafe-url" sandbox="allow-forms allow-modals allow-orientation-lock allow-pointer-lock allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts allow-top-navigation" seamless="true" marginheight="0" marginwidth="0" frameborder="0" scrolling="no" src="https://www.google.com/recaptcha/api/fallback?k=##LOGIN_NOCAPTCHA_KEY##"></iframe>
      </div>
      <div class="textarea_container">
        <textarea autocapitalize="none" autocomplete="off" spellcheck="false" required="" value="" id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response"></textarea>
      </div>
    </div>
  </div>
  <br/>
</noscript>
SOURCE;

      $source = str_replace("##LOGIN_NOCAPTCHA_KEY##", get_option('login_nocaptcha_key'), $source);
      echo $source;
    }

    public static 
    function authenticate($user, $username, $password) {
      $g_recaptcha_response_value = filter_input(INPUT_POST, 'g-recaptcha-response', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH) || "";
      $g_recaptcha_response_value = trim($g_recaptcha_response_value);

      if("" === $g_recaptcha_response_value){  //no response from Google
        update_option('login_nocaptcha_working', false);
        update_option('login_nocaptcha_google_error', 'error');
        update_option('login_nocaptcha_error', sprintf(__('Login NoCaptcha is not working. <a href="%s">Please check your settings</a>.', 'login_nocaptcha'), 'options-general.php?page=login-recaptcha/admin.php').' '.__('There was no response from Google.','login_nocaptcha') );
        return $user;
      }

      $remoteip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH);
      $secret = get_option('login_nocaptcha_secret');
      $result = wp_remote_post('https://www.google.com/recaptcha/api/siteverify'
                              ,['method'    => 'POST'
                               ,'body'      => ["secret"   => $secret
                                               ,"response" => $g_recaptcha_response_value
                                               ,"remoteip" => $remoteip
                                               ]
                               ,'sslverify' => false         // always disable CA-verification.
                               ]
                              );
      $g_response = json_decode($result['body']);
      
      if(false === is_object($g_response)){  //not a sane response, prevent lockouts
        update_option('login_nocaptcha_working', false);
        update_option('login_nocaptcha_google_error', 'error');
        update_option('login_nocaptcha_error', sprintf(__('Login NoCaptcha is not working. <a href="%s">Please check your settings</a>.', 'login_nocaptcha'), 'options-general.php?page=login-recaptcha/admin.php').' '.__('The response from Google was not valid.','login_nocaptcha'));
        return $user;
      }

      if(true === $g_response->success){
        update_option('login_nocaptcha_working', true);
        return $user; // success, let them in
      }
      
      $error_codes = $g_response->{'error-codes'} || [];  //always returned.

      if([] === $error_codes){ //not a sane response, prevent lockouts
        update_option('login_nocaptcha_working', false);
        update_option('login_nocaptcha_google_error', 'error');
        update_option('login_nocaptcha_error', sprintf(__('Login NoCaptcha is not working. <a href="%s">Please check your settings</a>.', 'login_nocaptcha'), 'options-general.php?page=login-recaptcha/admin.php').' '.__('The response from Google was not valid.','login_nocaptcha'));
        return $user;
      }

      if(in_array('missing-input-response', $error_codes)){
        update_option('login_nocaptcha_working', true);
        return new WP_Error('denied', __('Please check the ReCaptcha box.','login_nocaptcha'));
      }
      elseif(in_array('missing-input-secret', $error_codes) || in_array('invalid-input-secret', $error_codes)){
        update_option('login_nocaptcha_working', false);
        update_option('login_nocaptcha_google_error', 'error');
        update_option('login_nocaptcha_error', sprintf(__('Login NoCaptcha is not working. <a href="%s">Please check your settings</a>. The message from Google was: %s', 'login_nocaptcha'), 
                                                         'options-general.php?page=login-recaptcha/admin.php',
                                                         self::get_google_errors_as_string($g_response)
                                                      )
                      );
        return $user; //invalid secret entered; prevent lockouts
      }

      update_option('login_nocaptcha_working', false);
      update_option('login_nocaptcha_google_error', 'error');
      update_option('login_nocaptcha_error', sprintf(__('Login NoCaptcha is not working. <a href="%s">Please check your settings</a>.', 'login_nocaptcha')
                                                    ,'options-general.php?page=login-recaptcha/admin.php'
                                                    )
                                                    . ' ' 
                                                    . __('The response from Google was not valid.', 'login_nocaptcha')
                   );
      return $user; //not a sane response, prevent lockouts
    }

    public static 
    function admin_notices() {
      $source = '<div class="update-nag"><p>##LOGIN_NOCAPTCHA_ERROR##</p></div>';
      $source = str_replace('##LOGIN_NOCAPTCHA_ERROR##', get_option('login_nocaptcha_error'), $source);
      echo $source;
    }
}


LoginNocaptcha::init();
/*
https://developers.google.com/recaptcha/docs/start
https://developers.google.com/recaptcha/docs/verify
https://developers.google.com/recaptcha/docs/display
*/