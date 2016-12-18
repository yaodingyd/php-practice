<?

// Because PHP's native session timeout control is not reliable (session.gc_maxlifetime and session.cookie_lifetime)
// http://stackoverflow.com/questions/520237/how-do-i-expire-a-php-session-after-30-minutes
// So we need to manually implement session timeout control
if (isset($_SESSION['LAST_SEEN']) && (time() - $_SESSION['LAST_SEEN'] > $settings['session_timeout'])) {
	session_unset();
	session_destroy();
}
$_SESSION['LAST_SEEN'] = time();
session_start();
	
// Use constant MEMEBER to check if this is a existing session. 
// If so, check this session is valid;
// If not, try to re-log user in with cookie, or go to index page without cookie.
if (isset($_SESSION['uid'])) {
	$stmt = $pdo->prepare("SELECT token FROM user_tokens WHERE session_id =?");
	$stmt->execute([$_COOKIE['PHPSESSID']]);
	$data = $stmt->fetch();
	if (!$data) {
		// This session was valid before but its token record is deleted because user has reached maximum log-ins limit. 
		// So we start a new session.
		session_unset();
		session_destroy(); //clear session storage
		session_start();
		define("LOGINS_LIMIT_REACHED", true);
		//error_log('limit defined');
	}
}
	


// Because a session can be invalidated even user is logged in(forced log-out for maximum login limit)
// We need to check $_SESSON['uid'] again here 
if (isset($_SESSION['uid'])) {	
	//Set global userdata for logged-in user
	$stmt = $pdo->prepare("SELECT uid, user_name AS name, user_level AS level, user_avatar AS avatar, lang, free_prints, paid_prints FROM users WHERE uid = ? LIMIT 1");
	$stmt->execute([$_SESSION['uid']]);
	$userdata = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
	$userdata = ["level" => ""];
}

//GLOBAL USER LEVEL DEFINITION
define("MEMBER", $userdata['level'] >= 101 ? 1 : 0);
define("ADMIN", $userdata['level'] >= 102 ? 1 : 0);
define("SUPERADMIN", $userdata['level'] = 103 ? 1 : 0);

if (MEMBER) {
	define("LANG", $userdata['lang']);
} else {
	if (strpos($_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'], $_SERVER['SERVER_NAME']."/da") !== false) {
		define("LANG", 'da');
	} else {
		define("LANG", 'en');
	}
}

// locale is added before the userplan. Because we need correct timezone when possible for expire_date
define("LOCALE", BASEDIR.'locale/'.LANG.'/');
$locale = array();
include LOCALE.'global.php';

//Userplan is false if no active plan exist.
$userplan = 0;
$user_has_active_plan = 0; //Check if user has an active plan or not
if (MEMBER) {
	//Add the current existing plan if it exist
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE uid = ? AND expire_date >= ? LIMIT 1");
	$stmt->execute([$userdata['uid'], date('Y-m-d')]);
	if ($stmt->fetchColumn()) {
		$stmt = $pdo->prepare("SELECT s.expire_date, s.access, p.logins FROM subscriptions s INNER JOIN plans p ON (s.plan = p.pid) WHERE s.uid = ? AND s.expire_date >= ? ORDER BY s.expire_date ASC LIMIT 1");
		$stmt->execute([$_SESSION['uid'], date('Y-m-d')]);
		$userplan = $stmt->fetch(PDO::FETCH_ASSOC);
		
		$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE uid = ? AND status != ? LIMIT 1");
		$stmt->execute([$userdata['uid'], '111']);
		if ($stmt->fetchColumn()) {
			$user_has_active_plan = 1;
		}
	}
}

define("ACTIVE", ADMIN || $userplan ? 1 : 0);

//Enforce simultaneous logins limits for member
if (MEMBER) {	
	if (ADMIN) {
		$max_logins = $settings['admin_max_logins'];
	} elseif (ACTIVE)  {		
		$max_logins = $userplan['logins'];
	} else {
		$max_logins = 1; // Default max_logins to be 1 for unsubscribed member
	}

	$stmt = $pdo->prepare("SELECT token, last_seen FROM user_tokens WHERE uid = ? ORDER BY last_seen DESC");
	$stmt->execute([$userdata['uid']]);
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($data) {
		$current_logins = 0;
		foreach ($data as $item) {
			if (time() - $item['last_seen'] <= 1200) {
				$current_logins++;
				if ($current_logins > $max_logins) {
					$stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
					$stmt->execute([$item['token']]);
				}
			}
		}
	}

	if (isset($_COOKIE['token'])) {
		$token = hash('sha256', $_COOKIE['token']);
		$stmt = $pdo->prepare("UPDATE user_tokens SET last_seen = ? WHERE token = ? LIMIT 1");
		$stmt->execute([time(), $token]);
	}
}
	
// If not logged in, try to re-log in using cookies if there is uid and token stored in cookie
$relogged_in = FALSE;
if (!MEMBER && isset($_COOKIE['uid']) && isset($_COOKIE['token'])) {
	//polyfill for hash_equals
	if (!function_exists('hash_equals')) {
		function hash_equals($str1, $str2) {
			if (strlen($str1) != strlen($str2)) {
				return false;
			} else {
				$res = $str1 ^ $str2;
				$ret = 0;
				for($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
				return !$ret;
			}
		}
	}
			
	$uid = $_COOKIE['uid'];
	$stmt = $pdo->prepare("SELECT token FROM user_tokens WHERE uid = ?");
	$stmt->execute([$_COOKIE['uid']]);
	$data = $stmt->fetchAll(PDO::FETCH_COLUMN);
	if ($data) {
		// Get all token record from database for uid and iterate them all
		// Find one match, then update with new token and current session id.
		foreach ($data as $value) {
			if (hash_equals($value, hash('sha256', $_COOKIE['token']))) {
				$_SESSION['uid'] = $uid;
				setcookie('uid', $uid, time() + (86400 * 7), "/");
				$plaintext_token = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
				$hashed_token = hash('sha256', $plaintext_token);
				setcookie('token', $plaintext_token, time() + (86400 * 7), "/");
				$stmt = $pdo->prepare("UPDATE user_tokens set token = ?, session_id = ? WHERE token = ? LIMIT 1");
				$stmt->execute([$hashed_token, session_id(), $value]);
				$relogged_in = true;
				break;
			}
		}
	}
}
// If not logged-in and relogin fails(all tokens not matched), delete cookie's token because it expired in db.			
if (!MEMBER && !$relogged_in) {
	setcookie('uid', "", time() - 3600);
	setcookie('token', "", time() - 3600);
}

?>
