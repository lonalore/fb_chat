<?php

if (!defined('e107_INIT')) {
    exit;
}

class fb_chat_shortcodes extends e_shortcode {

    private $avatar_mw = 0;
    private $avatar_mh = 0;
    private $avatar_m = null;
    private $avatar_pw = 0;
    private $avatar_ph = 0;
    private $avatar_p = null;

    function __construct() {
        $this->avatar_mw = vartrue(e107::getPlugPref('fb_chat', 'fb_chat_menu_pic_w'), 32);
        $this->avatar_mh = vartrue(e107::getPlugPref('fb_chat', 'fb_chat_menu_pic_h'), 32);
        $this->avatar_pw = vartrue(e107::getPlugPref('fb_chat', 'fb_chat_page_pic_w'), 64);
        $this->avatar_ph = vartrue(e107::getPlugPref('fb_chat', 'fb_chat_page_pic_h'), 64);

        $this->avatar_m = "w=" . $this->avatar_mw . "&h=" . $this->avatar_mh;
        $this->avatar_p = "w=" . $this->avatar_pw . "&h=" . $this->avatar_ph;
    }

    function sc_avatar_menu() {
        $tp = e107::getParser();

        $uid = $this->var['id'];

        $genFile = e_IMAGE . "generic/blank_avatar.jpg";
        $defAvat = e107::getPlugPref('fb_chat', 'fb_chat_nopic');
        $avatar = vartrue($defAvat, $genFile);
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
                    $image = substr($image, 8); // strip the -upload- from the beginning.
                    if (file_exists(e_AVATAR_UPLOAD . $image)) { // Local Default Image
                        $img = $tp->thumbUrl(e_AVATAR_UPLOAD . $image, $this->avatar_m);
                    }
                } elseif (file_exists(e_AVATAR_DEFAULT . $image)) {  // User-Uplaoded Image
                    $img = $tp->thumbUrl(e_AVATAR_DEFAULT . $image, $this->avatar_m);
                }
            }
        }

        $title = $this->var['name'];
        $class = vartrue(e107::getPlugPref('fb_chat', 'fb_chat_launch'), 'fbcLaunch');        
        $text = "<img class='img-rounded user-avatar e-tip " . $class . "' fb-data='" . $uid . "' title='" . $title . "' src='" . $img . "' alt='' style='width:" . $width . "px; height:" . $height . "px' />";

        return $text;
    }

}

?>
