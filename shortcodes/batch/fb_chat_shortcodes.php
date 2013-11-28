<?php

if (!defined('e107_INIT')) {
    exit;
}

class fb_chat_shortcodes extends e_shortcode {

    private $plugPrefs = array();
    private $avatar_mw = 0;
    private $avatar_mh = 0;
    private $avatar_m = null;

    function __construct() {
        $this->plugPrefs = e107::getPlugConfig('fb_chat')->getPref();
        
        $this->avatar_mw = vartrue($this->plugPrefs['fb_chat_menu_pic_w'], 32);
        $this->avatar_mh = vartrue($this->plugPrefs['fb_chat_menu_pic_h'], 32);
        $this->avatar_m = "w=" . $this->avatar_mw . "&h=" . $this->avatar_mh;
    }

    function sc_avatar_menu($text = "") {
        $tp = e107::getParser();

        $uid = $this->var['id'];

        // e107 default avatar
        $genAvat = e_IMAGE . "generic/blank_avatar.jpg";
        // fb_chat default avatar
        $defAvat = $this->plugPrefs['fb_chat_nopic'];
        // set default avatar
        $avatar = vartrue($defAvat, $genAvat);
        // get source url for default avatar
        $img = $tp->thumbUrl($avatar, $this->avatar_m, true);
        
        $width = $this->avatar_mw;
        $height = $this->avatar_mh;

        if ((int) $uid > 0) {
            $row = get_user_data(intval($uid));
            $image = $row['user_image'];
            if (vartrue($image)) {
                if (strpos($image, "://") !== false) {
                    $img = $image;
                } elseif (substr($image, 0, 8) == "-upload-") {
                    // strip the -upload- from the beginning.
                    $image = substr($image, 8);
                    if (file_exists(e_AVATAR_UPLOAD . $image)) {
                        // Local Default Image
                        $img = $tp->thumbUrl(e_AVATAR_UPLOAD . $image, $this->avatar_m);
                    }
                } elseif (file_exists(e_AVATAR_DEFAULT . $image)) {
                    // User-Uplaoded Image
                    $img = $tp->thumbUrl(e_AVATAR_DEFAULT . $image, $this->avatar_m);
                }
            }
        }

        $title = $this->var['name'];
        $class = vartrue($this->plugPrefs['fb_chat_launch'], 'fbcLaunch');
        
        $attrs = array(
            'class' => 'user-avatar e-tip ' . $class,
            'src' => $img,
            'fb-data' => $uid,
            'width' => $width,
            'height' => $height,
            'title' => $title,
            'alt' => $title,
            'style' => 'width:' . $width . 'px;height:' . $height . 'px;',
        );
        
        foreach ($attrs as $key => $value) {
            $text .= $key . "='" . $value . "' ";
        }
        
        return "<img " . $text . "/>";
    }
    
    function sc_avatar_floating_menu($text = "") {
        $tp = e107::getParser();

        $uid = $this->var['id'];
        $width = "26";
        $height = "26";
        $params = "w=" . $width . "&h=" . $height;

        // e107 default avatar
        $genAvat = e_IMAGE . "generic/blank_avatar.jpg";
        // fb_chat default avatar
        $defAvat = $this->plugPrefs['fb_chat_nopic'];
        // set default avatar
        $avatar = vartrue($defAvat, $genAvat);
        // get source url for default avatar
        $img = $tp->thumbUrl($avatar, $params, true);

        if ((int) $uid > 0) {
            $row = get_user_data(intval($uid));
            $image = $row['user_image'];
            if (vartrue($image)) {
                if (strpos($image, "://") !== false) {
                    $img = $image;
                } elseif (substr($image, 0, 8) == "-upload-") {
                    // strip the -upload- from the beginning.
                    $image = substr($image, 8);
                    if (file_exists(e_AVATAR_UPLOAD . $image)) {
                        // Local Default Image
                        $img = $tp->thumbUrl(e_AVATAR_UPLOAD . $image, $params);
                    }
                } elseif (file_exists(e_AVATAR_DEFAULT . $image)) {
                    // User-Uplaoded Image
                    $img = $tp->thumbUrl(e_AVATAR_DEFAULT . $image, $params);
                }
            }
        }

        $title = $this->var['name'];
        $class = vartrue($this->plugPrefs['fb_chat_launch'], 'fbcLaunch');
        
        $attrs = array(
            'class' => 'user-avatar e-tip ' . $class,
            'src' => $img,
            'fb-data' => $uid,
            'width' => $width,
            'height' => $height,
            'title' => $title,
            'alt' => $title,
            'style' => 'width:' . $width . 'px;height:' . $height . 'px;',
        );
        
        foreach ($attrs as $key => $value) {
            $text .= $key . "='" . $value . "' ";
        }
        
        return "<img " . $text . "/>";
    }

    function sc_avatar_name() {
        return $this->var['name'];
    }
    
}

?>
