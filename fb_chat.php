<?php

require_once("../../class2.php");
if (!plugInstalled('fb_chat')) {
    exit;
}

e107::lan('fb_chat', false, true);

session_start();

class fb_chat {
    
    private $plugPrefs = array();

    function __construct() {
        if (USERID == 0) {
            return;
        }
        
        $this->plugPrefs = e107::getPlugConfig('fb_chat')->getPref();
        
        if (!check_class($this->plugPrefs['fb_chat_class'])) {
            exit;
        }

        if (!isset($_SESSION['chatHistory'])) {
            $_SESSION['chatHistory'] = array();
        }

        if (!isset($_SESSION['openChatBoxes'])) {
            $_SESSION['openChatBoxes'] = array();
        }

        /**
         * 1 - startchatsession
         * 2 - chatheartbeat
         * 3 - closechat
         * 4 - sendchat
         * 5 - get username
         */
        $action = (int) $_GET['a'];

        switch ($action) {
            case 1:
                $this->startChatSession();
                break;
            case 2:
                $this->chatHeartbeat();
                break;
            case 3:
                $this->closeChat();
                break;
            case 4:
                $this->sendChat((int) $_POST['to'], $_POST['message']);
                break;
            case 5:
                $this->getUserName($_POST['tid']);
                break;
            default:
                echo "ERROR: Invalid action parameter.";
                break;
        }
    }

    function startChatSession() {
        $items = '';
        if (!empty($_SESSION['openChatBoxes'])) {
            foreach ($_SESSION['openChatBoxes'] as $chatbox => $void) {
                $items .= $this->chatBoxSession($chatbox);
            }
        }

        if ($items != '') {
            $items = substr($items, 0, -1);
        }
        
        $row = get_user_data(USERID);
        $name = $this->setUserName($row['user_name'], $row['user_login']);

        header('Content-type: application/json');
        echo '{ "user": { "id": "' . USERID . '", "name": "' . $name . '" }, "items": [' . $items . '] }';
        exit;
    }

    function chatHeartbeat() {
        $items = '';
        
        $query = 'SELECT * FROM #fb_chat AS f
            LEFT JOIN #user AS u ON f.fb_chat_from = u.user_id
            WHERE 
                f.fb_chat_to = "' . USERID . '" 
                AND f.fb_chat_rcd = 0 
            ORDER BY f.fb_chat_id ASC';
        
        $sql = e107::getDb();
        $sql->gen($query);
        while($row = $sql->fetch()) {
            $fid = $row['fb_chat_from'];
            $fnm = $this->setUserName($row['user_name'], $row['user_login']);
            $msg = $this->handleOutput($row['fb_chat_msg']);
            $snt = $row['fb_chat_sent'];

            if (!isset($_SESSION['openChatBoxes'][$fid])
                    && isset($_SESSION['chatHistory'][$fid])) {
                $items = $_SESSION['chatHistory'][$fid];
            }

            $items .= '{ "s": "0", "f": { "id": "' . $fid . '", "name": "' . $fnm . '" }, "m": "' . $msg . '" },';

            if (!isset($_SESSION['chatHistory'][$fid])) {
                $_SESSION['chatHistory'][$fid] = '';
            }

            $_SESSION['chatHistory'][$fid] .= '{ "s": "0", "f": { "id": "' . $fid . '", "name": "' . $fnm . '" }, "m": "' . $msg . '" },';

            unset($_SESSION['tsChatBoxes'][$fid]);
            $_SESSION['openChatBoxes'][$fid] = $snt;
        }

        // Last message...
        if (!empty($_SESSION['openChatBoxes'])) {
            foreach ($_SESSION['openChatBoxes'] as $chatbox => $time) {
                if (!isset($_SESSION['tsChatBoxes'][$chatbox])) {
                    $now = time() - $time;
                    
                    $gen = e107::getDate();
                    $df = vartrue($this->plugPrefs['fb_chat_date_format'], "short");
                    $time_s = $gen->convert_date($time, $df);

                    $message = LANF_FB_CHAT_03 . " " . $time_s;
                    if ($now > 180) {
                        $items .= '{ "s": "2", "f": { "id": "' . $chatbox . '" }, "m": "' . $message . '" },';

                        if (!isset($_SESSION['chatHistory'][$chatbox])) {
                            $_SESSION['chatHistory'][$chatbox] = '';
                        }

                        $_SESSION['chatHistory'][$chatbox] .= '{ "s": "2", "f": { "id": "' . $chatbox . '" }, "m": "' . $message . '" },';
                        $_SESSION['tsChatBoxes'][$chatbox] = 1;
                    }
                }
            }
        }
        
        e107::getDb()->update("fb_chat", "fb_chat_rcd = 1 WHERE fb_chat_to = " . USERID . " AND fb_chat_rcd = 0 ");

        if ($items != '') {
            $items = substr($items, 0, -1);
        }

        header('Content-type: application/json');
        echo '{"items": [' . $items . ']}';
        exit;
    }

    function sendChat($to = 0, $msg = "") {
        if ((int) $to === 0) {
            exit;
        }
        
        $tp = e107::getParser();
        $msg = $tp->toDB($msg);
        
        $arg = array(
            "fb_chat_from" => USERID,
            "fb_chat_to" => $to,
            "fb_chat_msg" => $msg,
            "fb_chat_sent" => time(),
        );
        e107::getDb()->insert('fb_chat', $arg);

        $_SESSION['openChatBoxes'][$to] = time();
        if (!isset($_SESSION['chatHistory'][$to])) {
            $_SESSION['chatHistory'][$to] = '';
        }

        $message = $this->handleOutput($msg);
        $row = get_user_data(USERID);
        $name = $this->setUserName($row['user_name'], $row['user_login']);
        
        $_SESSION['chatHistory'][$to] .= '{ "s": "1", "f": { "id": "' . $to . '", "name": "' . $name . '" } , "m": "' . $message . '" },';
        unset($_SESSION['tsChatBoxes'][$to]);
        
        header('Content-type: application/json');
        echo '{ "f": "' . $name . '", "m": "' . $message . '" }';
        exit;
    }

    function closeChat() {
        unset($_SESSION['openChatBoxes'][$_POST['chatbox']]);
        echo "1";
        exit;
    }

    function chatBoxSession($chatbox) {
        $items = '';
        if (isset($_SESSION['chatHistory'][$chatbox])) {
            $items = $_SESSION['chatHistory'][$chatbox];
        }
        return $items;
    }
    
    function getUserName($uid = 0, $name = "N/A") {
        if ((int) $uid === 0) {
            exit;
        }
        
        $link = vartrue($this->plugPrefs['fb_chat_title_link'], 1);
        $row = get_user_data(intval($uid));
        $name = $this->setUserName($row['user_name'], $row['user_login']);
        
        if ((boolean) $link) {
            $name = "<a href='" . SITEURL . "user.php?id." . (int) $uid . "' target='_self'>" . $name . "</a>";
        }

        header('Content-type: application/json');
        echo '{"name": "' . $name . '"}';
        exit;
    }
    
    function setUserName($user_name = "", $disp_name = "", $name = "N/A") {
        if ($user_name == "" && $disp_name == "") {
            return $name;
        }
        
        $mode = vartrue($this->plugPrefs['fb_chat_title'], 0);
        
        if ($mode == 1) {
            $name = $disp_name;
        }
        
        if ($name == "N/A" || $name == "") {
            $name = $user_name;
        }
        
        return $name;
    }
    
    function handleOutput($text) {
        $tp = e107::getParser();
        $opts = $this->_handleOutput_get_opts();
        $text = $tp->toHTML($text, FALSE, 'BODY' . $opts);
        
        // Try to embed videos by links
        $text = $this->_handleOutput_embed_videos($text);
        
        // TODO - get a better solution to handle quote conflict with json
        $text = str_replace("\"", "'", $text);
        return $text;
    }
    
    function _handleOutput_get_opts($opts = "") {
        $emote = vartrue($this->plugPrefs['fb_chat_emote'], 0);
        if ((int) $emote === 1) {
            $opts .= ",emotes_on";
        } else {
            $opts .= ",emotes_off";
        }
        
        $click = vartrue($this->plugPrefs['fb_chat_clickable_links'], 0);
        if ((int) $click === 1) {
            $opts .= ",make_clickable";
        } else {
            $opts .= ",no_make_clickable";
        }
        
        return $opts;
    }
    
    function _handleOutput_embed_videos($text) {
        $emb = vartrue($this->plugPrefs['fb_chat_embed_videos'], 0);
        if ((int) $emb === 0) {
            return $text;
        }
        
        $urls = $this->_get_urls_from_string($text);       
        if (isset($urls[0]) && !empty($urls[0])) {
            require_once("classes/autoembed/AutoEmbed.class.php");
            $aeObj = new AutoEmbed;
            
            $embedCode = "";
            foreach($urls[0] as $url) {
                if ($aeObj->parseUrl($url)) {
                    $aeObj->setWidth(220);
                    $aeObj->setHeight(124);
                    $embedCode = $aeObj->getEmbedCode();
                }
            }
            
            if (!empty($embedCode)) {
                $text .= "<div class='chatembedcode'>" . $embedCode . "</div>";
            }
        }
        
        return $text;
    }
    
    function _get_urls_from_string($string = "", $matches = array()) {
        preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $string, $matches);
        return array_unique($matches);
    }

}

new fb_chat;
?>