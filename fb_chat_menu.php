<?php

if (!defined('e107_INIT')) {
    exit;
}

if (!plugInstalled('fb_chat')) {
    exit;
}

e107::lan('fb_chat', false, true);

class fb_chat_menu {
    
    private $onlineList = array();
    private $plugPrefs = array();
    
    /**
     * Get plugin prefs and select online users from database after check
     * current user permission.
     * @return
     */
    function __construct() {
        if (USERID == 0) {
            return;
        }
        
        $this->plugPrefs = e107::getPlugConfig('fb_chat')->getPref();
        
        if (!check_class($this->plugPrefs['fb_chat_class'])) {
            return;
        }
        
        $sqlTable = "online";
        $sqlField = "DISTINCT(online_user_id)";
        $sqlArgs = "online_user_id != 0";
        //$sqlArgs .= " AND online_user_id != '" . USERID . "." . USERNAME . "'";
        $rows = e107::getDb()->retrieve($sqlTable, $sqlField, $sqlArgs, TRUE);
        foreach($rows as $row) {
            $id = explode(".", $row['online_user_id']);
            $this->onlineList[] = array(
                'id' => (int) $id[0],
                'name' => $this->get_display_name($id[0]),
            );
        }
        $this->listOnlineUsers();
    }
    
    /**
     * Parse output HTML with online users by using
     * template file. Finally, render the menu.
     */
    public function listOnlineUsers() {
        $template = e107::getTemplate('fb_chat');
        $sc = e107::getScBatch('fb_chat', TRUE);
        $tp = e107::getParser();
        
        $text = $tp->parseTemplate($template['MENU_START']);
        
        foreach ($this->onlineList as $val) {
            $sc->setVars($val);
            $text .= $tp->parseTemplate($template['MENU_ITEM'], TRUE, $sc);
        }
        
        $text .= $tp->parseTemplate($template['MENU_END']);
                
        e107::getRender()->tablerender(LANF_FB_CHAT_01, $text);
        
        unset($text);
    }
    
    /**
     * Get chat display name by the obtained User ID
     * @param int $uid
     *  User ID
     * @param string $name
     *  Default return value
     * @return string $name
     */
    function get_display_name($uid = 0, $name = "N/A") {
        if ((int) $uid === 0) {
            return $name;
        }
        
        $mode = vartrue(e107::getPlugPref('fb_chat', 'fb_chat_title'), 0);
        
        if ($mode == 1) {
            $row = get_user_data(intval($uid));
            if (isset($row['user_login'])) {
                $name = $row['user_login'];
            }
        }
        
        if ($name == "N/A" || $name == "") {
            $row = get_user_data(intval($uid));
            if (isset($row['user_name'])) {
                $name = $row['user_name'];
            }
        }
        
        return $name;
    }
    
}

new fb_chat_menu();

?>