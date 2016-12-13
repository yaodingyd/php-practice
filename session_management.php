<? php

\\login
if (isset($_POST['remember']) && $_POST['remember'] != NULL) { // we only use cookie if remember me is checked
  setcookie('uid', $user['uid'], time() + (86400 * 7), "/");
            $plaintext_token = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
            $hashed_token = hash('sha256', $plaintext_token);
            setcookie('token', $plaintext_token, time() + (86400 * 7), "/");
            $stmt = $pdo->prepare("INSERT INTO user_token(uid, token, session_id, last_seen) VALUES (?, ?, ?, ?)");
          $stmt->execute([$user['uid'], $hashed_token, $_COOKIE['PHPSESSID'], time()]);                   
  } else {
            $plaintext_token = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
            $hashed_token = hash('sha256', $plaintext_token);
            setcookie('token', $plaintext_token, time() + 86400, "/");
            $stmt = $pdo->prepare("INSERT INTO user_token(uid, token, session_id, last_seen) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['uid'], $hashed_token, $_COOKIE['PHPSESSID'], time()]);     
        }
 
 
 \\logout
 if (isset($_COOKIE['token'])) {
	setcookie('token',"",time() - 3600, "/");
	$stmt = $pdo->prepare("DELETE FROM user_token WHERE token = ?");
    $token = hash('sha256', $_COOKIE['token']);
	$stmt->execute([$token]);
}
if (isset($_COOKIE['uid'])) {
   setcookie('uid',"",time() - 3600, "/"); 
}

\\authentication
<?


class authentication {
    
    function session_timeout_control () {  
        if (isset($_SESSION['LAST_SEEN']) && (time() - $_SESSION['LAST_SEEN'] > 1200)) {
            // last request was more than 20 minutes ago
            session_unset();
            session_destroy();
        }
        $_SESSION['LAST_SEEN'] = time();
        session_start();     
    }
    
    function enforce_logins_limit () {
        if (ADMIN) {
            $max_logins = 4;
        } else {
            $stmt = $pdo->prepare("SELECT p.logins FROM subscriptions s LEFT JOIN plans p ON (s.plan = p.pid) WHERE s.uid = ? AND s.status = ? LIMIT 1");
            $stmt->execute([$_SESSION['uid'], '000']);
            $subscription = $stmt->fetch();
            $max_logins = $subscription[0];
        }

		$stmt = $pdo->prepare("SELECT token, last_seen FROM token WHERE uid = ? ORDER BY last_seen DESC");
		$stmt->execute([$_SESSION['uid']]);
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if ($data) {
			$current_logins = 0;
			foreach ($data as $item) {
				if (time() - $item['last_seen'] <= 1200) {
					$current_logins++;
					if ($current_logins > $max_logins) {
						$stmt = $pdo->prepare("DELETE FROM token WHERE token = ?");
						$stmt->execute([$item['token']]);
					}
				}
			}
		}

		if (isset($_COOKIE['token'])) {
			$token = hash('sha256', $_COOKIE['token']);
			$stmt = $pdo->prepare("UPDATE token SET last_seen = ? WHERE token = ? LIMIT 1");
			$stmt->execute([time(), $token]);
		}
    }
    
    function set_userdata_global () {
        //USERDATA
        if (isset($_SESSION['uid'])) {
            $stmt = $pdo->prepare("SELECT user_name AS name, user_level AS level, user_avatar AS avatar, lang, instrument, free_prints, paid_prints FROM users WHERE uid = ? LIMIT 1");
            $stmt->execute([$_SESSION['uid']]);
            $userdata = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $userdata = ["name" => "", "level" => "", "avatar" => "", "lang" => "", "instrument" => "", "free_prints" => "", "paid_prints" => ""];
        }

        //GLOBAL USER LEVEL DEFINITION
        define("MEMBER", $userdata['level'] >= 101 ? 1 : 0);
        define("ADMIN", $userdata['level'] >= 102 ? 1 : 0);
        define("SUPERADMIN", $userdata['level'] = 103 ? 1 : 0);
        $active = ADMIN ? 1 : 0;
        if (MEMBER) {
            define("LANG", $userdata['lang']);

            //CHECK IF USER HAS AN ACTIVE SUBSCRIPTION
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE uid = ? AND status = ? LIMIT 1");
            $stmt->execute([$_SESSION['uid'], '000']);
            if ($stmt->fetchColumn()) {
                $active = 1;
            }
        } else {
            if (strpos($_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'], $_SERVER['SERVER_NAME']."/da") !== false) {
                define("LANG", 'da');
            } else {
                define("LANG", 'en');
            }
        }

        define("ACTIVE", $active);
    }
    
    function check_session_valid () {
        if (isset($_SESSION['uid'])) {
            $stmt = $pdo->prepare("SELECT token FROM token WHERE session_id =?");
            $stmt->execute([$_COOKIE['PHPSESSID']]);
            $data = $stmt->fetch();
            if (!$data) {
                session_unset();
                session_destroy(); //clear session storage
                session_start();
            } else {
                set_userdata_global();
                enforce_logins_limit();
            }
        }
    }

    function relogin_with_cookie () {
        //polyfill for hash_equals
        if (!function_exists('hash_equals')) {
            function hash_equals($str1, $str2) {
                error_log($str1." testing ".$str2);
                if (strlen($str1) != strlen($str2)) {
                    error_log($str1." is not equal ".$str2);
                    return false;
                } else {
                    $res = $str1 ^ $str2;
                    $ret = 0;
                    for($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
                    return !$ret;
                }
            }
        }
        
        $relogged_in = FALSE;
        if (!isset($_SESSION['uid']) && isset($_COOKIE['uid']) && isset($_COOKIE['token'])) {
            $uid = $_COOKIE['uid'];
            $stmt = $pdo->prepare("SELECT token FROM token WHERE uid = ?");
            $stmt->execute([$_COOKIE['uid']]);
            $data = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($data) {
                foreach ($data as $value) {
                    if (hash_equals($value, hash('sha256', $_COOKIE['token']))) {
                        $_SESSION['uid'] = $uid;
                        setcookie('uid', $uid, time() + (86400 * 7), "/");
                        $plaintext_token = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
                        $hashed_token = hash('sha256', $plaintext_token);
                        setcookie('token', $plaintext_token, time() + (86400 * 7), "/");
                        $stmt = $pdo->prepare("UPDATE token set token = ?, session_id = ? WHERE token = ? LIMIT 1");
                        $stmt->execute([$hashed_token, $_COOKIE['PHPSESSID'], $value]);
                        $relogged_in = true;
                        break;
                    }
                }
            }
        }
        if (!$relogged_in && !isset($_SESSION['uid'])) {
            setcookie('uid', "", time() - 3600);
            setcookie('token', "", time() - 3600);
        }
    }

    function init () {
        session_timeout_control();
        if (isset($_SESSION['uid'])) {
            check_session_valid();
        } else {
            relogin_with_cookie();
        }
    }
}

$auth = new authentication();
$auth->init();
?>


?>
