<?php

/**
 * Parses a user agent string into its important parts
 *
 * @author Jesse G. Donat <donatj@gmail.com>
 * @link https://github.com/donatj/PhpUserAgent
 * @link http://donatstudios.com/PHP-Parser-HTTP_USER_AGENT
 * @param string|null $u_agent User agent string to parse or null. Uses $_SERVER['HTTP_USER_AGENT'] on NULL
 * @throws InvalidArgumentException on not having a proper user agent to parse.
 * @return string[] an array with browser, version and platform keys
 */
function parse_user_agent( $u_agent = null ) {
	if( is_null($u_agent) ) {
		if( isset($_SERVER['HTTP_USER_AGENT']) ) {
			$u_agent = $_SERVER['HTTP_USER_AGENT'];
		} else {
			throw new \InvalidArgumentException('parse_user_agent requires a user agent');
		}
	}

	$platform = null;
	$browser  = null;
	$version  = null;

	$empty = array( 'platform' => $platform, 'browser' => $browser, 'version' => $version );

	if( !$u_agent ) return $empty;

	if( preg_match('/\((.*?)\)/im', $u_agent, $parent_matches) ) {

		preg_match_all('/(?P<platform>BB\d+;|Android|CrOS|Tizen|iPhone|iPad|Linux|Macintosh|Windows(\ Phone)?|Silk|linux-gnu|BlackBerry|PlayBook|(New\ )?Nintendo\ (WiiU?|3?DS)|Xbox(\ One)?)
				(?:\ [^;]*)?
				(?:;|$)/imx', $parent_matches[1], $result, PREG_PATTERN_ORDER);

		$priority           = array( 'Xbox One', 'Xbox', 'Windows Phone', 'Tizen', 'Android' );
		$result['platform'] = array_unique($result['platform']);
		if( count($result['platform']) > 1 ) {
			if( $keys = array_intersect($priority, $result['platform']) ) {
				$platform = reset($keys);
			} else {
				$platform = $result['platform'][0];
			}
		} elseif( isset($result['platform'][0]) ) {
			$platform = $result['platform'][0];
		}
	}

	if( $platform == 'linux-gnu' ) {
		$platform = 'Linux';
	} elseif( $platform == 'CrOS' ) {
		$platform = 'Chrome OS';
	}

	preg_match_all('%(?P<browser>Camino|Kindle(\ Fire\ Build)?|Firefox|Iceweasel|Safari|MSIE|Trident|AppleWebKit|TizenBrowser|Chrome|
			Vivaldi|IEMobile|Opera|OPR|Silk|Midori|Edge|CriOS|
			Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
			NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
			(?:\)?;?)
			(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
		$u_agent, $result, PREG_PATTERN_ORDER);

	// If nothing matched, return null (to avoid undefined index errors)
	if( !isset($result['browser'][0]) || !isset($result['version'][0]) ) {
		if( preg_match('%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?%ix', $u_agent, $result) ) {
			return array( 'platform' => $platform ?: null, 'browser' => $result['browser'], 'version' => isset($result['version']) ? $result['version'] ?: null : null );
		}

		return $empty;
	}

	if( preg_match('/rv:(?P<version>[0-9A-Z.]+)/si', $u_agent, $rv_result) ) {
		$rv_result = $rv_result['version'];
	}

	$browser = $result['browser'][0];
	$version = $result['version'][0];

	$lower_browser = array_map('strtolower', $result['browser']);
	
	$key  = 0;
	$ekey = 0;
	if( $browser == 'Iceweasel' ) {
		$browser = 'Firefox';
	} elseif( false !== ( $key = array_search('playstation vita', $lower_browser) ) ) {
		$platform = 'PlayStation Vita';
		$browser  = 'Browser';
	} elseif( false !== ( $key = array_search('kindle fire build', $lower_browser) ) || false !== ( $key = array_search('silk', $lower_browser ) ) ) {
		$browser  = $result['browser'][$key] == 'Silk' ? 'Silk' : 'Kindle';
		$platform = 'Kindle Fire';
		if( !($version = $result['version'][$key]) || !is_numeric($version[0]) ) {
			$version = $result['version'][array_search('Version', $result['browser'])];
		}
	} elseif( false !== ( $key = array_search('nintendobrowser', $lower_browser) ) || $platform == 'Nintendo 3DS' ) {
		$browser = 'NintendoBrowser';
		$version = $result['version'][$key];
	} elseif( false !== ( $key = array_search('kindle', $lower_browser) ) ) {
		$browser  = $result['browser'][$key];
		$platform = 'Kindle';
		$version  = $result['version'][$key];
	} elseif( false !== ( $key = array_search('opr', $lower_browser) ) ) {
		$browser = 'Opera Next';
		$version = $result['version'][$key];
	} elseif( false !== ( $key = array_search('opera', $lower_browser) ) ) {
		$browser = 'Opera';
	
		if( false !== ( $ekey = array_search('version', $lower_browser) ) ) { $key = $ekey; }
		$version = $result['version'][$key];
	} elseif( false !== ( $key = array_search('midori', $lower_browser) ) ) {
		$browser = 'Midori';
		$version = $result['version'][$key];
	} elseif( $browser == 'MSIE' || ($rv_result && false !== ( $key = array_search('trident', $lower_browser) ) ) || false !== ( $ekey = array_search('edge', $lower_browser) ) ) {
		$browser = 'MSIE';
		if( false !== ( $key = array_search('iemobile', $lower_browser) ) ) {
			$browser = 'IEMobile';
			$version = $result['version'][$key];
		} elseif( $ekey ) {
			$version = $result['version'][$ekey];
		} else {
			$version = $rv_result ?: $result['version'][$key];
		}
	} elseif( false !== ( $key = array_search('vivaldi', $lower_browser) ) ) {
		$browser = 'Vivaldi';
		$version = $result['version'][$key];
	} elseif( false !== ( $key = array_search('chrome', $lower_browser) ) || false !== ( $ekey = array_search('crios', $lower_browser) ) ) {
		$browser = 'Chrome';
		if( $ekey !== false ) { $key = $ekey; }
		$version = $result['version'][$key];
	} elseif( $browser == 'AppleWebKit' ) {
		if( $platform == 'Android' ) {
			$key = 0;
		} elseif( strpos($platform, 'BB') === 0 ) {
			$browser  = 'BlackBerry Browser';
			$platform = 'BlackBerry';
		} elseif( $platform == 'BlackBerry' || $platform == 'PlayBook' ) {
			$browser = 'BlackBerry Browser';
		} elseif( false !== ( $key = array_search('safari', $lower_browser) ) ) {
			$browser = 'Safari';
		} elseif( false !== ( $key = array_search('tizenbrowser', $lower_browser) ) ) {
			$browser = 'TizenBrowser';
		}

		if( false !== ( $ekey = array_search('version', $lower_browser) ) ) { $key = $ekey; }

		$version = $result['version'][$key];
	} elseif( $key = preg_grep('/playstation \d/i', array_map('strtolower', $result['browser'])) ) {
		$key = reset($key);

		$platform = 'PlayStation ' . preg_replace('/[^\d]/i', '', $key);
		$browser  = 'NetFront';
	}

	return array( 'platform' => $platform ?: null, 'browser' => $browser ?: null, 'version' => $version ?: null );
}
