<?php
/**
 * @file
 * Main class of fb_chat plugin.
 */


/**
 * Class fb_chat_main.
 */
class fb_chat_main
{

	/**
	 * Check permissions of current user
	 *
	 * @return boolean
	 *  TRUE: Access granted
	 *  FALSE: Access denied
	 */
	public function check_permission()
	{
		if(USERID == 0)
		{
			return false;
		}

		$class = vartrue($this->plugPrefs['fb_chat_class'], 253);

		if(!check_class($class))
		{
			return false;
		}

		return true;
	}

	/**
	 * Get online users. (where chat is turned on)
	 *
	 * @param array $users
	 *  Empty array to store online users.
	 * @return array $users
	 *  Associative array with details of online users (ID and Name).
	 */
	public function get_online_users($users = array())
	{
		$sql = e107::getDb();

		$sqlFlds = "DISTINCT(online_user_id)";
		$sqlArgs = "online_user_id != 0";
		$sqlArgs .= " AND online_user_id != '" . USERID . "." . USERNAME . "'";
		$rows = $sql->retrieve("online", $sqlFlds, $sqlArgs, true);

		$ids = array();
		foreach($rows as $row)
		{
			$parts = explode(".", $row['online_user_id']);
			$ids[] = (int) $parts[0];
		}

		$sqlArgs1 = "user_id = " . implode(" OR user_id = ", $ids);
		$query = 'SELECT user_id, user_name, user_login FROM #user AS u
            LEFT JOIN #fb_chat_turnedoff AS t ON t.fb_chat_turnedoff_uid = u.user_id
            WHERE ' . $sqlArgs1 . '
                AND (fb_chat_turnedoff_uid = "" OR fb_chat_turnedoff_uid IS NULL)
            ORDER BY u.user_name ASC';
		$rows = $sql->retrieve($query, true);

		foreach($rows as $row)
		{
			$users[] = array(
				'id'   => $row['user_id'],
				'name' => $this->get_user_name_by_names($row['user_name'], $row['user_login']),
			);
		}

		return $users;
	}

	/**
	 * Get chat status by user.
	 *
	 * @param int $uid
	 *  User ID
	 * @return int
	 *  0: Chat is turned off
	 *  1: Chat is turned on
	 */
	public function get_chat_status($uid)
	{
		$count = e107::getDb()
			->count("fb_chat_turnedoff", "(*)", "fb_chat_turnedoff_uid = " . (int) $uid);
		if($count > 0)
		{
			return 0;
		}
		else
		{
			return 1;
		}
	}

	/**
	 * Decide which name to use.
	 *
	 * @param string $user_name
	 *  Username
	 * @param string $disp_name
	 *  Display name
	 * @param string $name
	 *  Default return value.
	 * @return string $name
	 *  Name, which appears in chatbox.
	 */
	public function get_user_name_by_names($user_name = "", $disp_name = "", $name = "...")
	{
		if($user_name == "" && $disp_name == "")
		{
			return $name;
		}

		$mode = vartrue($this->plugPrefs['fb_chat_title'], 0);

		if($mode == 1)
		{
			$name = $disp_name;
		}

		if($name == "..." || $name == "")
		{
			$name = $user_name;
		}

		return $name;
	}

	/**
	 * Get chat display name by the obtained User ID. Decide which name to use.
	 *
	 * @param int $uid
	 *  User ID
	 * @param string $name
	 *  Default return value
	 * @return string $name
	 *  Name, which appears in chatbox.
	 */
	public function get_user_name_by_id($uid = 0, $name = "...")
	{
		if((int) $uid === 0)
		{
			return $name;
		}

		$mode = vartrue($this->plugPrefs['fb_chat_title'], 0);

		if($mode == 1)
		{
			$row = e107::user(intval($uid));
			if(isset($row['user_login']))
			{
				$name = $row['user_login'];
			}
		}

		if($name == "..." || $name == "")
		{
			$row = e107::user(intval($uid));
			if(isset($row['user_name']))
			{
				$name = $row['user_name'];
			}
		}

		return $name;
	}

	/**
	 * Parse output HTML.
	 *
	 * @param string $text
	 *  Input string.
	 * @return string $text
	 *  Output (HTML) string.
	 */
	public function handle_output($text)
	{
		$tp = e107::getParser();
		$opts = $this->handle_output_get_opts();
		$text = $tp->toHTML($text, false, 'BODY' . $opts);
		// Try to embed videos by links
		$text = $this->handle_output_embed_videos($text);
		// TODO - get a better solution to handle quote conflict with json
		$text = str_replace("\"", "'", $text);
		return $text;
	}

	/**
	 * Get options for parsing output string.
	 *
	 * @param string $opts
	 *  Default return value.
	 * @return string $opts
	 *  Options for methode toHTML().
	 */
	public function handle_output_get_opts($opts = "")
	{
		$emote = vartrue($this->plugPrefs['fb_chat_emote'], 0);
		if((int) $emote === 1)
		{
			$opts .= ",emotes_on";
		}
		else
		{
			$opts .= ",emotes_off";
		}

		$click = vartrue($this->plugPrefs['fb_chat_clickable_links'], 0);
		if((int) $click === 1)
		{
			$opts .= ",make_clickable";
		}
		else
		{
			$opts .= ",no_make_clickable";
		}

		return $opts;
	}

	/**
	 * Implementation of Class AutoEmbed. Try to parse URLs and
	 * get embed Audio/Video.
	 *
	 * @param string $text
	 *  Input string
	 * @return string $text
	 *  Output string
	 */
	public function handle_output_embed_videos($text)
	{
		$emb = vartrue($this->plugPrefs['fb_chat_embed_videos'], 0);
		if((int) $emb === 0)
		{
			return $text;
		}

		$urls = $this->get_urls_from_string($text);
		if(isset($urls[0]) && !empty($urls[0]))
		{
			e107_include(e_PLUGIN . "fb_chat/classes/autoembed/AutoEmbed.class.php");

			$aeObj = new AutoEmbed;
			$embedCode = "";

			foreach($urls[0] as $url)
			{
				if($aeObj->parseUrl($url))
				{
					$aeObj->setWidth(220);
					$aeObj->setHeight(124);
					$embedCode = $aeObj->getEmbedCode();
				}
			}

			if(!empty($embedCode))
			{
				$text .= "<div class='chatembedcode'>" . $embedCode . "</div>";
			}
		}

		return $text;
	}

	/**
	 * Try to get URLs from a string.
	 *
	 * @param string $string
	 *  Input string.
	 * @param array $matches
	 *  Default return array.
	 * @return array $matches
	 *  Array contains URLs. Or an empty array.
	 */
	public function get_urls_from_string($string = "", $matches = array())
	{
		preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $string, $matches);
		return array_unique($matches);
	}

	/**
	 * Get new messages from DB by UID.
	 *
	 * @param int $uid
	 *  User ID.
	 * @param array $messages
	 *  Default return array.
	 * @return array $messages
	 *  Array with messages. Or an empty array.
	 */
	public function get_new_messages($uid = 0, $messages = array())
	{
		if((int) $uid === 0)
		{
			return $messages;
		}

		$query = 'SELECT * FROM #fb_chat AS f
            LEFT JOIN #user AS u ON f.fb_chat_from = u.user_id
            WHERE
                f.fb_chat_to = "' . (int) $uid . '"
                AND f.fb_chat_rcd = 0
            ORDER BY f.fb_chat_id ASC';

		$sql = e107::getDb();
		$messages = $sql->retrieve($query, true);

		return $messages;
	}

	/**
	 * Sanitize strong for JSON output.
	 *
	 * @param string $text
	 *  Input string.
	 * @return string $text
	 *  Sanitized output string.
	 */
	public function sanitize_string($text)
	{
		$text = htmlspecialchars($text, ENT_QUOTES);
		$text = str_replace("\n\r", "\n", $text);
		$text = str_replace("\r\n", "\n", $text);
		$text = str_replace("\n", "<br>", $text);
		return $text;
	}

	/**
	 * Converts a PHP variable into its JavaScript equivalent.
	 * We use HTML-safe strings, with several characters escaped.
	 */
	function fb_chat_json_encode($var)
	{
		// The PHP version cannot change within a request.
		static $php530;

		if(!isset($php530))
		{
			$php530 = version_compare(PHP_VERSION, '5.3.0', '>=');
		}

		if($php530)
		{
			// Encode <, >, ', &, and " using the json_encode() options parameter.
			return json_encode($var, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		}

		return $this->fb_chat_json_encode_helper($var);
	}

	/**
	 * Encodes a PHP variable to HTML-safe JSON for PHP versions below 5.3.0.
	 */
	function fb_chat_json_encode_helper($var)
	{
		switch(gettype($var))
		{
			case 'boolean':
				return $var ? 'true' : 'false'; // Lowercase necessary!

			case 'integer':
			case 'double':
				return $var;

			case 'resource':
			case 'string':
				// Always use Unicode escape sequences (\u0022) over JSON escape
				// sequences (\") to prevent browsers interpreting these as
				// special characters.
				$replace_pairs = array(

					// ", \ and U+0000 - U+001F must be escaped according to RFC 4627.
					'\\'           => '\u005C',
					'"'            => '\u0022',
					"\x00"         => '\u0000',
					"\x01"         => '\u0001',
					"\x02"         => '\u0002',
					"\x03"         => '\u0003',
					"\x04"         => '\u0004',
					"\x05"         => '\u0005',
					"\x06"         => '\u0006',
					"\x07"         => '\u0007',
					"\x08"         => '\u0008',
					"\x09"         => '\u0009',
					"\x0a"         => '\u000A',
					"\x0b"         => '\u000B',
					"\x0c"         => '\u000C',
					"\x0d"         => '\u000D',
					"\x0e"         => '\u000E',
					"\x0f"         => '\u000F',
					"\x10"         => '\u0010',
					"\x11"         => '\u0011',
					"\x12"         => '\u0012',
					"\x13"         => '\u0013',
					"\x14"         => '\u0014',
					"\x15"         => '\u0015',
					"\x16"         => '\u0016',
					"\x17"         => '\u0017',
					"\x18"         => '\u0018',
					"\x19"         => '\u0019',
					"\x1a"         => '\u001A',
					"\x1b"         => '\u001B',
					"\x1c"         => '\u001C',
					"\x1d"         => '\u001D',
					"\x1e"         => '\u001E',
					"\x1f"         => '\u001F',
					// Prevent browsers from interpreting these as as special.
					"'"            => '\u0027',
					'<'            => '\u003C',
					'>'            => '\u003E',
					'&'            => '\u0026',
					// Prevent browsers from interpreting the solidus as special and
					// non-compliant JSON parsers from interpreting // as a comment.
					'/'            => '\u002F',
					// While these are allowed unescaped according to ECMA-262, section
					// 15.12.2, they cause problems in some JSON parsers.
					"\xe2\x80\xa8" => '\u2028', // U+2028, Line Separator.
					"\xe2\x80\xa9" => '\u2029', // U+2029, Paragraph Separator.
				);

				return '"' . strtr($var, $replace_pairs) . '"';

			case 'array':
				// Arrays in JSON can't be associative. If the array is empty or if it
				// has sequential whole number keys starting with 0, it's not associative
				// so we can go ahead and convert it as an array.
				if(empty($var) || array_keys($var) === range(0, sizeof($var) - 1))
				{
					$output = array();
					foreach($var as $v)
					{
						$output[] = $this->fb_chat_json_encode_helper($v);
					}
					return '[ ' . implode(', ', $output) . ' ]';
				}
				break;

			// Otherwise, fall through to convert the array as an object.
			case 'object':
				$output = array();
				foreach($var as $k => $v)
				{
					$output[] = $this->fb_chat_json_encode_helper(strval($k)) . ':' . $this->fb_chat_json_encode_helper($v);
				}
				return '{' . implode(', ', $output) . '}';

			default:
				return 'null';
		}
	}


}
