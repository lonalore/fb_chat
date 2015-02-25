<?php
/**
 * @file
 * Class instantiation to prepare JavaScript configurations and include css/js
 * files to page header.
 */

if(!defined('e107_INIT'))
{
	exit;
}

// Load required main class of plugin.
require_once("classes/fb_chat_main.class.php");

// Use [PLUGINS]/fb_chat/languages/[LANGUAGE]/[LANGUAGE]_front.php file.
e107::lan('fb_chat', false, true);


/**
 * Class fb_chat_e_header.
 */
class fb_chat_e_header extends fb_chat_main
{

	protected $plugPrefs = array();
	private   $jsOptions = "";

	function __construct()
	{
		$this->plugPrefs = e107::getPlugConfig('fb_chat')->getPref();

		if(!$this->check_permission())
		{
			return;
		}

		$this->get_js_options();
		$this->include_components();
	}

	/**
	 * Get chat settings and language texts for JavaScript
	 */
	function get_js_options()
	{
		$conf = $this->plugPrefs;

		// Pass default chat configs to JavaScript
		$this->jsOptions['linkClass'] = vartrue($conf['fb_chat_launch'], 'fbcLaunch');
		$this->jsOptions['requestPath'] = e_PLUGIN_ABS . 'fb_chat';
		$this->jsOptions['heartbeatMin'] = vartrue($conf['fb_chat_hb_min'], 3) * 1000;
		$this->jsOptions['heartbeatMax'] = vartrue($conf['fb_chat_hb_max'], 30) * 1000;
		$this->jsOptions['heartbeatMenu'] = vartrue($conf['fb_chat_hb_menu'], 30) * 1000;
		$this->jsOptions['floatMenu'] = vartrue($conf['fb_chat_fm'], 1);
		$this->jsOptions['status'] = $this->get_chat_status(USERID);

		// Pass language texts to JavaScript
		$this->jsOptions['lans'] = array(
			'online'  => LANF_FB_CHAT_04,
			'offline' => LANF_FB_CHAT_05,
			'turnon'  => LANF_FB_CHAT_07,
			'turnoff' => LANF_FB_CHAT_06,
			'offmsg'  => LANF_FB_CHAT_08,
			'nouser'  => LANF_FB_CHAT_09,
		);
	}

	/**
	 * Include necessary CSS and JS files
	 */
	function include_components()
	{
		$inlineJS = '$(document).fb_chat(' . $this->fb_chat_json_encode($this->jsOptions) . ');';
		$inlineJS = '$(document).ready(function() { ' . $inlineJS . ' });';

		e107::css('fb_chat', 'css/fb_chat.css');
		e107::css('fb_chat', 'css/fb_chat_screen.css');
		e107::css('fb_chat', 'css/fb_chat_screen_ie.css', null, 'all', '<!--[if lte IE 7]>', '<![endif]-->');

		// TODO: Need to fix bootstrap theme's CSS style because of chat wrapper.
		// TODO: Make an option on Admin UI for this.
		//e107::css('fb_chat', 'css/fix/fb_chat__bootstrap.css');

		e107::js('fb_chat', 'js/fb_chat.js', 'jquery');
		e107::js('inline', $inlineJS, 'jquery');
	}

}


// Class instantiation.
new fb_chat_e_header;
