<?php

class fb_chat_main {
    
    public function check_permission() {
        if (USERID == 0) {
            return FALSE;
        }

        $class = vartrue($this->plugPrefs['fb_chat_class'], 253);
        
        if (!check_class($class)) {
            return FALSE;
        }
        
        return TRUE;
    }
    
    public function get_online_users($users = array()) {
        $sql = e107::getDb();
        
        $sqlFlds = "DISTINCT(online_user_id)";
        $sqlArgs = "online_user_id != 0";
        $sqlArgs .= " AND online_user_id != '" . USERID . "." . USERNAME . "'";
        $rows = $sql->retrieve("online", $sqlFlds, $sqlArgs, TRUE);
        
        $ids = array();
        foreach ($rows as $row) {
            $parts = explode(".", $row['online_user_id']);
            $ids[] = (int) $parts[0];
        }
                
        $sqlArgs1 = "user_id = " . implode(" OR user_id = ", $ids);
        
        $query = 'SELECT user_id, user_name, user_login FROM #user AS u
            LEFT JOIN #fb_chat_turnedoff AS t ON t.fb_chat_turnedoff_uid = u.user_id
            WHERE ' . $sqlArgs1 . ' 
                AND (fb_chat_turnedoff_uid = "" OR fb_chat_turnedoff_uid IS NULL) 
            ORDER BY u.user_name ASC';

        $rows = $sql->retrieve($query, TRUE);
        
        foreach ($rows as $row) {
            $users[] = array(
                'id' => $row['user_id'],
                'name' => $this->get_user_name_by_names($row['user_name'], $row['user_login']),
            );
        }
                
        return $users;
    }
    
    public function get_chat_status($uid) {
        $count = e107::getDb()->count("fb_chat_turnedoff", "(*)", "fb_chat_turnedoff_uid = " . (int) $uid);
        if ($count > 0) {
            return 0;
        } else {
            return 1;
        }
    }
    
    public function get_user_name_by_names($user_name = "", $disp_name = "", $name = "N/A") {
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
    
    /**
     * Get chat display name by the obtained User ID
     * @param int $uid
     *  User ID
     * @param string $name
     *  Default return value
     * @return string $name
     */
    public function get_user_name_by_id($uid = 0, $name = "N/A") {
        if ((int) $uid === 0) {
            return $name;
        }
        
        $mode = vartrue($this->plugPrefs['fb_chat_title'], 0);
        
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
    
    public function handle_output($text) {
        $tp = e107::getParser();
        $opts = $this->handle_output_get_opts();
        $text = $tp->toHTML($text, FALSE, 'BODY' . $opts);
        // Try to embed videos by links
        $text = $this->handle_output_embed_videos($text);
        // TODO - get a better solution to handle quote conflict with json
        $text = str_replace("\"", "'", $text);
        return $text;
    }

    public function handle_output_get_opts($opts = "") {
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

    public function handle_output_embed_videos($text) {
        $emb = vartrue($this->plugPrefs['fb_chat_embed_videos'], 0);
        if ((int) $emb === 0) {
            return $text;
        }

        $urls = $this->get_urls_from_string($text);
        if (isset($urls[0]) && !empty($urls[0])) {
            require_once("autoembed/AutoEmbed.class.php");

            $aeObj = new AutoEmbed;
            $embedCode = "";

            foreach ($urls[0] as $url) {
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

    public function get_urls_from_string($string = "", $matches = array()) {
        preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $string, $matches);
        return array_unique($matches);
    }
    
    public function get_new_messages($uid = 0, $messages = array()) {
        if ((int) $uid === 0) {
            return $messages;
        }
        
        $query = 'SELECT * FROM #fb_chat AS f
            LEFT JOIN #user AS u ON f.fb_chat_from = u.user_id
            WHERE 
                f.fb_chat_to = "' . (int) $uid . '" 
                AND f.fb_chat_rcd = 0 
            ORDER BY f.fb_chat_id ASC';

        $sql = e107::getDb();
        $messages = $sql->retrieve($query, TRUE);
        
        return $messages;
    }
    
    public function sanitize_string($text) {
        $text = htmlspecialchars($text, ENT_QUOTES);
        $text = str_replace("\n\r", "\n", $text);
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\n", "<br>", $text);
        return $text;
    }
        
}

?>
