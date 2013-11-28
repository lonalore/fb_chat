<?php

if (!defined('e107_INIT')) {
    exit;
}

require_once("classes/fb_chat_main.class.php");

e107::lan('fb_chat', false, true);

class fb_chat_e_header extends fb_chat_main {

    protected $plugPrefs = array();
    private $jsOptions = "";

    function __construct() {       
        $this->plugPrefs = e107::getPlugConfig('fb_chat')->getPref();
        
        if (!$this->check_permission()) {
            return;
        }
        
        $this->get_js_options();
        $this->include_components();
    }
    
    function get_js_options() {
        $conf = $this->plugPrefs;
        
        $opts = array(
            'linkClass' => vartrue($conf['fb_chat_launch'], 'fbcLaunch'),
            'requestPath' => e_PLUGIN_ABS . 'fb_chat',
            'heartbeatMin' => vartrue($conf['fb_chat_hb_min'], 3) * 1000,
            'heartbeatMax' => vartrue($conf['fb_chat_hb_max'], 30) * 1000,
            'heartbeatMenu' => vartrue($conf['fb_chat_hb_menu'], 30) * 1000,
            'floatMenu' => vartrue($conf['fb_chat_fm'], 1),
            'floatMenuTitle' => LANF_FB_CHAT_04,
        );
        
        foreach($opts as $key => $value) {
            $this->jsOptions .= $key . ': "' . $value . '",';
        }
        
        $this->jsOptions = substr($this->jsOptions, 0, -1);
    }

    function include_components() {
        $inlineJS = '$(document).fb_chat({ ' . $this->jsOptions . ' });';
        $inlineJS = '$(document).ready(function() { ' . $inlineJS . ' });';

        e107::css('fb_chat', 'css/fb_chat.css');
        e107::css('fb_chat', 'css/fb_chat_screen.css');
        e107::css('fb_chat', 'css/fb_chat_screen_ie.css', null, 'all', '<!--[if lte IE 7]>', '<![endif]-->');
        
        // TODO: Need to fix bootstrap theme's CSS style because of chat wrapper...
        // TODO: Make an option on Admin UI for this
        //e107::css('fb_chat', 'css/fix/fb_chat__bootstrap.css');
        
        e107::js('fb_chat', 'js/fb_chat.js', 'jquery');
        e107::js('inline', $inlineJS, 'jquery');
    }

}

new fb_chat_e_header;

?>