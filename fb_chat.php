<?php

require_once("../../class2.php");
if (!plugInstalled('fb_chat')) {
    exit;
}

require_once("classes/fb_chat_main.class.php");

e107::lan('fb_chat', false, true);

session_start();

class fb_chat extends fb_chat_main {

    protected $plugPrefs = array();

    public function __construct() {
        $this->plugPrefs = e107::getPlugConfig('fb_chat')->getPref();
        
        if (!$this->check_permission()) {
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
                $this->chat_start_session();
                break;
            case 2:
                $this->chat_heartbeat();
                break;
            case 3:
                $this->chat_close();
                break;
            case 4:
                $this->chat_send((int) $_POST['to'], $_POST['message']);
                break;
            case 5:
                $this->get_user_name($_POST['tid']);
                break;
            case 6:
                $this->get_online_list();
                break;
            default:
                exit;
        }
    }

    /**
     * 
     * @param string $items
     */
    public function chat_start_session($items = "") {
        if (!empty($_SESSION['openChatBoxes'])) {
            foreach ($_SESSION['openChatBoxes'] as $chatbox => $void) {
                $items .= $this->chat_box_session($chatbox);
            }
        }

        if (!empty($items)) {
            $items = substr($items, 0, -1);
        }

        $row = get_user_data(USERID);
        $name = $this->get_user_name_by_names($row['user_name'], $row['user_login']);

        header('Content-type: application/json');
        echo '{ "user": { "id": "' . USERID . '", "name": "' . $name . '" }, "items": [' . $items . '] }';
        exit;
    }

    /**
     * 
     * @param string $items
     */
    public function chat_heartbeat($items = "") {
        $query = 'SELECT * FROM #fb_chat AS f
            LEFT JOIN #user AS u ON f.fb_chat_from = u.user_id
            WHERE 
                f.fb_chat_to = "' . USERID . '" 
                AND f.fb_chat_rcd = 0 
            ORDER BY f.fb_chat_id ASC';

        $sql = e107::getDb();
        $sql->gen($query);
        while ($row = $sql->fetch()) {
            $fid = $row['fb_chat_from'];
            $fnm = $this->get_user_name_by_names($row['user_name'], $row['user_login']);
            $msg = $this->handle_output($row['fb_chat_msg']);
            $snt = $row['fb_chat_sent'];

            if (!isset($_SESSION['openChatBoxes'][$fid]) && isset($_SESSION['chatHistory'][$fid])) {
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

        if (!empty($items)) {
            $items = substr($items, 0, -1);
        }

        header('Content-type: application/json');
        echo '{"items": [' . $items . ']}';
        exit;
    }

    /**
     * 
     * @param int $to
     * @param string $msg
     */
    public function chat_send($to = 0, $msg = "") {
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

        $message = $this->handle_output($msg);
        $row = get_user_data(USERID);
        $name = $this->get_user_name_by_names($row['user_name'], $row['user_login']);

        $_SESSION['chatHistory'][$to] .= '{ "s": "1", "f": { "id": "' . $to . '", "name": "' . $name . '" } , "m": "' . $message . '" },';
        unset($_SESSION['tsChatBoxes'][$to]);

        header('Content-type: application/json');
        echo '{ "f": "' . $name . '", "m": "' . $message . '" }';
        exit;
    }

    /**
     * 
     */
    public function chat_close() {
        unset($_SESSION['openChatBoxes'][$_POST['chatbox']]);
        echo "1";
        exit;
    }

    /**
     * 
     * @param int $chatbox
     * @param string $items
     * @return string $items
     */
    public function chat_box_session($chatbox, $items = "") {
        if (isset($_SESSION['chatHistory'][$chatbox])) {
            $items = $_SESSION['chatHistory'][$chatbox];
        }
        return $items;
    }

    /**
     * 
     * @param int $uid
     * @param string $name
     */
    public function get_user_name($uid = 0, $name = "N/A") {
        if ((int) $uid === 0) {
            exit;
        }

        $link = vartrue($this->plugPrefs['fb_chat_title_link'], 1);
        $row = get_user_data(intval($uid));
        $name = $this->get_user_name_by_names($row['user_name'], $row['user_login']);

        if ((boolean) $link) {
            $name = "<a href='" . SITEURL . "user.php?id." . (int) $uid . "' target='_self'>" . $name . "</a>";
        }

        header('Content-type: application/json');
        echo '{"name": "' . $name . '"}';
        exit;
    }

    /**
     * 
     */
    public function get_online_list() {       
        $template = e107::getTemplate('fb_chat');
        $sc = e107::getScBatch('fb_chat', TRUE);
        $tp = e107::getParser();

        $users = $this->get_online_users();

        $text = $tp->parseTemplate($template['FLOAT_MENU_START']);
        foreach ($users as $user) {
            $sc->setVars($user);
            $text .= $tp->parseTemplate($template['FLOAT_MENU_ITEM'], TRUE, $sc);
        }
        $text .= $tp->parseTemplate($template['FLOAT_MENU_END']);

        header('Content-Type: text/html; charset=utf-8');
        echo $text;
        exit;
    }

}

new fb_chat;
?>