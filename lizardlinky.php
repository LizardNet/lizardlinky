#!/bin/php

LizardLinky by FastLizard4 and the LizardLinky Development Team (see AUTHORS.txt file)

Copyright (C) 2014 by FastLizard4 and the LizardLinky Development Team.  Some rights reserved.
License GPLv3+: GNU General Public License version 3 or later (at your choice): <http://gnu.org/licenses/gpl.html>.
This is free software: you are free to change and redistribute it at your will provided that your redistribution,
with or without modifications, is also licensed under the GNU GPL.

There is NO WARRANTY FOR THIS SOFTWARE to the extent permitted by law.

Portions of this code are reused from a sister LizardNet project, the LizardIRC Network Operations Bot
<https://git.fastlizard4.org/gitblit/summary/?r=LizardIRC/NOC-Bot.git>, which is also licensed under the GNU
GPLv3+.

This is an open source project. The source Git repositories, which you are
welcome to contribute to, can be found here:
<https://gerrit.fastlizard4.org/r/gitweb?p=lizardlinky.git;a=summary>
<https://git.fastlizard4.org/gitblit/summary/?r=lizardlinky.git>

Gerrit Code Review for the project:
<https://gerrit.fastlizard4.org/r/#/q/project:lizardlinky,n,z>

Alternatively, the project source code can be found on the PUBLISH-ONLY mirror
on GitHub: <https://github.com/LizardNet/lizardlinky>

Note: Pull requests and patches submitted to GitHub will be transferred by a
developer to Gerrit before they are acted upon.

<?php
if(PHP_SAPI != "cli") {
	die("LizardLinky must be used using the PHP CLI (Command Line Interface) binary.  The current reported PHP SAPI is: " . PHP_SAPI . "\n");
}

define('LIZARDLINKY_VERSION', trim(`git describe --always --dirty --long`));
define('LIZARDLINKY_DEFAULT_CONFIG_FILENAME', 'lizardlinky.conf.inc');

echo "This is LizardLinky version " . LIZARDLINKY_VERSION . ".\n\n";

//Parse arguments
//I probably really overengineered this....
try {
	//Set defaults for options set at the command line
	$configFilename = LIZARDLINKY_DEFAULT_CONFIG_FILENAME;
	$noMoreOptions = false;
	$argumentsRequired = array();

	if($_SERVER['argc'] > 1) {
		for($i = 1; $i < $_SERVER['argc']; $i++) {
			if(trim(substr($_SERVER['argv'][$i], 0, 2)) == '--' && !$noMoreOptions) {
				//Switches
				if(strcasecmp($_SERVER['argv'][$i], "--") === 0) {
					//POSIX "end-of-options-list" option
					$noMoreOptions = true;
					continue;
				} elseif(strcasecmp($_SERVER['argv'][$i], "--help") === 0) {
					echo "Usage: php lizardlinky.php [OPTIONS]\n\n";
					echo "Recognized options:\n";
					echo "(Note: Mandatory arguments to long options are mandatory for short options too. Long options are case-insensitive, short options are case-sensitive.)\n";
					echo "      --help\t\t\tThis help information.\n";
					echo "      --version\t\t\tPrint the license and version information shown at every run, but then exit instead of doing anything useful.\n";
					echo "  -c, --config FILENAME\t\tRun lizardlinky with the specified filename as the configuration file, instead of the default (" . LIZARDLINKY_DEFAULT_CONFIG_FILENAME . ").\n";
					die(0);
				} elseif(strcasecmp($_SERVER['argv'][$i], "--version") === 0) {
					die(0); //noop
				} elseif(strcasecmp($_SERVER['argv'][$i], "--config") === 0) {
					if(!isset($_SERVER['argv'][$i+1])) {
						throw new Exception("Option '{$_SERVER['argv'][$i]}' requires an argument.");
					} else {
						$configFilename = $_SERVER['argv'][$i+1];
						$i++;
						continue;
					}
				} else {
					throw new Exception("Unrecognized option '{$_SERVER['argv'][$i]}'");
				}
			} elseif(trim(substr($_SERVER['argv'][$i], 0, 1)) == '-' && !$noMoreOptions) {
				//Short switches
				for($j = 1; $j < strlen($_SERVER['argv'][$i]); $j++) {
					switch($shortOption = trim(substr($_SERVER['argv'][$i], $j, 1))) {
						case 'c':
							$argumentsRequired[] = 'c';
							break;
						default:
							throw new Exception("Unrecognized short option -{$shortOption}");
							break;
					}
				}
			} elseif(count($argumentsRequired) > 0) {
				switch($argumentsRequired[0]) {
					case 'c':
						$configFilename = $_SERVER['argv'][$i];
						break;
				}

				unset($argumentsRequired[0]);
			} else {
				//Arguments.  Since we don't have any non-switch arguments, throw an error.
				throw new Exception("Too many arguments (first unnecessary argument: {$_SERVER['argv'][$i]})");
			}
		}

		if(count($argumentsRequired) > 0) {
			throw new Exception("Option '-{$argumentsRequired[0]}' requires an argument.");
		}
	}
} catch(Exception $e) {
	echo "Error: {$e->getMessage()}\n";
	echo "Usage: php lizardlinky.php [OPTIONS]\n";
	echo "Try 'php lizardlinky.php --help' for more information.\n";
	die(1);
}
//End parse arguments

function getLine($ircLink, $debug = false) {
        $toRead = array($ircLink);
        $toWrite = NULL;
        $toExcept = NULL;
        $toTimeout = 5;
        if(@stream_select($toRead, $toWrite, $toExcept, $toTimeout))
		$lineIn = fgets($ircLink, 1024);
	else
		$lineIn = NULL;
	if($debug)
		echo $lineIn;
	$lineIn2 = explode(' ', $lineIn);
	$lineIn = $lineIn2;
	$lineIn2 = array();
	foreach($lineIn as $key => $line) {
		if($line[0] == ':') {
			if($key == 0) {
				$line[0] = '';
				$lineIn2[$key] = $line;
				continue;
			} else {
				$line[0] = '';
				$temp = $line;
				for($i = $key + 1; $i < count($lineIn); $i++) {
					$temp .= ' ' . $lineIn[$i];
				}
				$lineIn2[$key] = trim($temp);
				break;
			}
		} else {
			$lineIn2[$key] = $line;
		}
	}

	$lineIn = $lineIn2;

	if(preg_match("/^.*!.*@.*$/", $lineIn[0])) {
		$nickmask = $lineIn[0];
		$lineIn[0] = array();
		$lineIn[0]["full"] = trim($nickmask);
		$temp = explode('!', $nickmask);
		$lineIn[0]["nick"] = trim($temp[0]);
		$temp = explode('@', $temp[1]);
		$lineIn[0]["ident"] = trim($temp[0]);
		$lineIn[0]["host"] = trim($temp[1]);
	}

	return $lineIn;
}

//Send a message to the bot's report channels, if any.
function reportToIRC($ircLink, $reportText) {
	global $conf;

	if(count($conf['reportChannels']) > 0) {
		foreach($conf['reportChannels'] as $channel) {
			fwrite($ircLink, "PRIVMSG {$channel} :{$reportText}\r\n");
			sleep(0.5);
		}
		return;
	} else
		return;
}

function echoAndReport($ircLink, $reportText) {
	echo "[>] {$reportText}\n";
	reportToIRC($ircLink, $reportText);
	return;
}

//Opens a MySQL connection without running a query.
function openDB($ircLink) {
	global $conf;

	$mysqli = new mysqli($conf['dbHost'], $conf['dbUsername'], $conf['dbPassword'], $conf['dbSchema'], (int)$conf['dbPort']);
	if($mysqli->connect_error) {
		echoAndReport($ircLink, "[ERROR] MySQL Connect Failed - Unable to connect to MySQL database: {$mysqli->connect_error} ({$mysqli->connect_errno}).");
	}
	if(!$mysqli->set_charset("utf8")) {
		echoAndReport($ircLink, "[ERROR] MySQL Set Charset Failed - Failed setting the MySQL connection charset to \"utf8\"");
	}

	return $mysqli;
}

//Runs the given MySQL query.  Opens a new connection of $mysqli is not provided.
//Returns the $mysqli object.  This allows for one-off queries to be executed like this:
// queryDB($ircLink, "SELECT * FROM `blah`", $result)->close();
function queryDB($ircLink, $query, &$result, &$mysqli = false) {
	global $conf;

	if($mysqli === false) {
		$mysqli = new mysqli($conf['dbHost'], $conf['dbUsername'], $conf['dbPassword'], $conf['dbSchema'], (int)$conf['dbPort']);
		if($mysqli->connect_error) {
			echoAndReport($ircLink, "[ERROR] MySQL Connect Failed - Unable to connect to MySQL database: {$mysqli->connect_error} ({$mysqli->connect_errno}).");
			$result = false;
			return $mysqli;
		}
		if(!$mysqli->set_charset("utf8")) {
			echoAndReport($ircLink, "[ERROR] MySQL Set Charset Failed - Failed setting the MySQL connection charset to \"utf8\"");
			$result = false;
			return $mysqli;
		}
	}

	$result = $mysqli->query($query, MYSQLI_STORE_RESULT);
	if($result === false) {
		echoAndReport($ircLink, "[ERROR] MySQL Query Failed - A MySQL query failed with this error message: {$mysqli->error}");
	}

	return $mysqli;
}

//Enumerates the groups that a user is in, by matching hostmask.
//If the optional second parameter is set to anything except boolean false,
//duplicate group grants will be included.  Otherwise, they will be discarded
//(default).  Return an array if there are any group grants, boolean false otherwise.
function getGroups($hostmask, $dupes = false) {
	global $globalACL;

	$grantedGroups = false;

	foreach($globalACL as $aclEntry) {
		if(fnmatch($aclEntry['acl_hostmask'], $hostmask)) {
			//Matching hostmask in global ACL
			//Alas, this only works on POSIX systems, but we already have
			//POSIX-only signal handlers in this code.  So there, Windows.
			if($dupes === false) {
				$grantedGroups[$aclEntry['acl_group']] = $aclEntry['acl_id'];
			} else {
				$grantedGroups[][$aclEntry['acl_group']] = $aclEntry['acl_id'];
			}
		}
	}

	return $grantedGroups;
}

//Checks if a user has a certain privilege.
//Privileges are mapped to user groups, and hostmasks in the global ACL table are members
//of one or more groups, one row per group (a granted group is adding a row, revoking
//a group membership is deleting that row).  Note that hostmasks in the global ACL table
//cannot by definition be a member of zero groups - presence in zero groups is accomplished
//by having zero entries for that hostmask.  For a privilege check, the bot will first
//get a list of all hostmasks that match a user, and then a list of the groups granted by
//these hostmasks.  Eventually, there will be an array in the configuration file that
//will be used to define custom groups and what privileges they have, and this function
//will consult that array, but for now, there is only one recognized group hard-coded
//into the bot - 'root' - and it has all privileges.
//
//Function returns true if an action is authorized, false otherwise.
function hasPriv($hostmask, $privilege) {
	global $conf;

	$grantedGroups = getGroups($hostmask);

	if($grantedGroups === false) {
		if($conf['groupPermissions']['*'][$privilege] === true) {
			return true;
		}
	} else {
		foreach($grantedGroups as $group => $aclID) {
			if($conf['groupPermissions'][$group][$privilege] === true || $group === "root" || $conf['groupPermissions']['*'][$privilege] === true) {
				//We don't need to keep looking if we find a granted privilege - just one grant is enough
				return true;
			}
		}
	}

	return false;
}

function transmitLine($ircLink, $lineIn, $response, $reportLine = false) {
	global $conf;

	if($lineIn[2] == $conf['nick']) {
		//This is a private message
		fwrite($ircLink, "PRIVMSG {$lineIn[0]['nick']} :{$response}\r\n");
		if($reportLine) {
			$reportLine .= "a private message.";
			reportToIRC($ircLink, $reportLine);
		}
	} else {
		//This was a public message
		$response = $lineIn[0]['nick'] . ": " . $response;
		fwrite($ircLink, "PRIVMSG {$lineIn[2]} :{$response}\r\n");
		if($reportLine) {
			$reportLine .= "channel {$lineIn[2]}.";
			reportToIRC($ircLink, $reportLine);
		}
	}
}
function doLoadACL($startup = false) {
	global $globalACL, $conf;

	queryDB(null, "SELECT `acl_id`, `acl_hostmask`, `acl_group` FROM `{$conf['dbPrefix']}global_acl` ORDER BY `acl_id` ASC", $result)->close();
	if($result === false)
		throw new Exception(" ^  Error getting Global ACL data from MySQL.\n");
	else {
		if($startup) {
			echo "\n ^  Fetched {$result->num_rows} global ACL entries....";
		}
		while($globalACL[] = $result->fetch_assoc());
		foreach($conf['roots'] as $rootHostmask)
			if($startup && !preg_match('/[^ !@]*![^ !@]*@[^ !@]*/', $rootHostmask)) {
				die("\n[!] Error: Root hostmask entry '{$rootHostmask}' defined in configuration file (\$conf['roots']) is NOT a valid hostmask!\n");
			}
			$globalACL[] = array('acl_id' => "(config)", 'acl_hostmask' => $rootHostmask, 'acl_group' => 'root');
		$result->free();
	}
}

function doLoadChannels($startup = false) {
	global $channels, $conf;

	queryDB(null, "SELECT `channel_name`, `channel_default_url`, `channel_responsible_nick`, `channel_privileged_access`, `channel_status` FROM `{$conf['dbPrefix']}channels` ORDER BY `channel_id` ASC", $result)->close();
	if($result === false)
		throw new Exception(" ^  Error getting Channels data from MySQL.\n");
	else {
		if($startup) {
			echo "\n ^  Fetched {$result->num_rows} channel entries....";
		}
		while($channels[] = $result->fetch_assoc());
		$result->free();
	}
}

function doLoadInterwikis($startup = false) {
	global $interwikis, $conf;

	queryDB(null, "SELECT `interwiki_prefix`, `interwiki_target_url` FROM `{$conf['dbPrefix']}interwiki` ORDER BY `interwiki_prefix` ASC", $result)->close();
	if($result === false)
		throw new Exception(" ^  Error getting Interwiki data from MySQL.\n");
	else {
		if($startup) {
			echo "\n ^  Fetched {$result->num_rows} interwiki entries....";
		}
		while($interwikis[] = $result->fetch_assoc());
		$result->free();
	}
}

echo "[*] Defining POSIX signal handlers...";
function ONSIGHUP() {
	return;
}

function ONSIGTERM() {
	global $ircLink;

	echo "\n[!] Caught signal SIGINT/2 or SIGTERM/15.  Shutting down...";
	fwrite($ircLink, "QUIT :Caught SIGINT/2 or SIGTERM/15\r\n");
	echo "\010\010\010 [OK]\n";
	die("[*] Shutdown complete.\n");
}

declare(ticks = 1);
pcntl_signal(SIGHUP, "ONSIGHUP");
pcntl_signal(SIGINT, "ONSIGTERM");
pcntl_signal(SIGTERM, "ONSIGTERM");
echo "\010\010\010 [OK]\n";

$restartCount = 0;

start:
$globalACL = array();
$channels = array();
$interwikis = array();

echo "[*] Reading configuration file {$configFilename}...";
require($configFilename);
echo "\010\010\010 [OK]\n";

echo "[*] Checking required configuration values...";
try {
	if(!is_array($conf))
		throw new Exception(" ^  $conf array not found.  Please check your configuration file.\n");

	$requiredConfig = array('nick', 'ident', 'gecos', 'server', 'port', 'dbHost', 'dbPort', 'dbSchema', 'dbUsername', 'dbPassword', 'diepass', 'trigger');

	foreach($requiredConfig as $requiredConfigKey) {
		if(!$conf[$requiredConfigKey] || $conf[$requiredConfigKey] == "") {
			throw new Exception(" ^  Required configuration value \$conf['{$requiredConfigKey}'] not found.  Please check your\n" .
			"    configuration file.\n");
		}
	}

	if(!is_int($conf['port']))
		throw new Exception(" ^  Required configuration value \$conf['port'] is not an integer value.  Please check your configuration file.\n");

	if(count($conf['roots']) == 0)
		echo "\n ^  Warning: No roots are defined in \$conf['roots'].  If you are unable to control your own bot, setting this may help....";

} catch(Exception $e) {
	echo "\010\010\010 [fail]\n";
	echo "[!] Caught exception!\n";
	echo $e->getMessage();
	die();
}

echo "\010\010\010 [OK]\n";

echo "[*] Testing MySQL connection...";
if(isset($conf['dbPrefix'])) {
	$conf['dbPrefix'] .= "_";
}
$mysqli = queryDB(null, "SELECT 'test'", $result);
if($result === false) {
	die("\n[fail]\n");
} else
	echo "\010\010\010 [OK]\n";

echo "[*] Loading dynamic configuration from MySQL database...";
try {
	doLoadACL(true);
	doLoadChannels(true);
	doLoadInterwikis(true);
} catch(Exception $e) {
	echo "\010\010\010 [fail]\n";
	echo "[!] Error occurred - caught exception!\n";
	echo $e->getMessage();
	die();
}

echo "\010\010\010 [OK]\n";

echo "[*] Initiating IRC connection to {$conf['server']} on port {$conf['port']}...";

$server = (($conf['useSSL']) ? "ssl://" . $conf['server'] : $conf['server']);

$ircLink = fsockopen($server, $conf['port'], $errno, $errstr, 20);

if($ircLink === false) {
	//Connection failed
	echo "[!] Connection failed\n";
	echo " ^  Details: Error {$errstr} ({$errno}) [fail]\n";
	die();
}

echo "\010\010\010 [OK]\n";

echo "[*] Registering IRC connection...";
fwrite($ircLink, "USER {$conf['ident']} localhost {$conf['server']} :{$conf['gecos']}\r\n");
sleep(1);
fwrite($ircLink, "NICK {$conf['nick']}\r\n");

//430 - 434 are numerics indicating that the nickname chosen is bad, so let's first detect
//that and have the bot exit on those numerics.
//001 would be the numeric indicating successful registration

while(!feof($ircLink)) {
	$lineIn = getLine($ircLink);
	if($lineIn[1] >= 430 && $lineIn[1] <= 434) {
		echo "\n[!] Registration error: Invalid or erroneous nickname\n";
		$lineIn[4] = trim($lineIn[4]);
		echo " ^  The server said: {$lineIn[4]}";
		die(" [fail]\n");
	}
	if($lineIn[0] == "PING") {
		//Handle those pesky IRC networks that require a PONG to a pseudo-random hex string before registrations are processed.
		//I really don't know why any networks still do this, as it's a terrible anti-bot measure.  For reasons that are hopefully
		//obvious at this point.
		$pongTarget = trim($lineIn[1]);
		fwrite($ircLink, "PONG {$pongTarget}\r\n");
	}
	if($lineIn[1] == 001) {
		echo "\010\010\010 [OK]\n";

		//Registration successful
		if($conf['nickServ'] && $conf['nickServ'] != "") {
			echo "[*] Sending identification to NickServ...\n";
			echo " ^  Note: Since this isn't standardized in the IRC protocol, we cannot determine if identification was\n".
			     "    successful or not.";
			fwrite($ircLink, "PRIVMSG NICKSERV :IDENTIFY {$conf['nickServ']}\r\n");
			echo "  [OK]\n";
		} else
			echo "[*] Not identifying to NickServ, \$conf['nickServ'] not defined in configuration.\n";

		if(count($conf['reportChannels']) > 0) {
			echo "[*] Joining report channels...";
			foreach($conf['reportChannels'] as $channel)
				fwrite($ircLink, "JOIN {$channel}\r\n");
			echo "\010\010\010 [OK]\n";
		}

		//Begin main loop
		//Bot's main functions will go here.

		echo "[ ] Startup complete.  Now beginning main loop.\n";
		$lastPingTime = time();
		while(true) {
			//Main loop
			$lineIn = getLine($ircLink);

			//Detect ping timeouts
			if($lastPingTime + 300 <= time()) {
				echo "[!] ERROR: Ping timeout (server hasn't pinged us in 300 seconds).  Restarting bot!\n";
				reportToIRC($ircLink, "[ERROR] Ping timeout - the server hasn't pinged me in 300 seconds or more.  I'm now restarting.");
				fwrite($ircLink, "QUIT :Ping timeout, restarting\r\n");
				fclose($ircLink);
				unset($ircLink);
				$restartCount++;
				if($restartCount > 3) {
					echo "[!] ERROR: Bot has already restarted due to ping timeouts 3 times consecutively.\n";
					echo " ^  Something bad is happening.  Bot will now terminate itself.\n";
					die();
				} else
					goto start; //Please don't shoot me, please don't shoot me, please don't shoot me....
			}

			//Respond to server pings
			if($lineIn[0] == "PING") {
				$pongTarget = trim($lineIn[1]);
				fwrite($ircLink, "PONG {$pongTarget}\r\n");
				echo "[ ] Pinged by {$pongTarget}, responded with a pong.\n";
				$lastPingTime = time();
				$restartCount = 0;
			}

			//Detect kills and other disconnects
			if($lineIn[0] == "ERROR") {
				$lastPingTime = time();
				$lineIn[1] = trim($lineIn[1]);
				echo "[!] ERROR: Connection to server unexpectedly closed, server sent us an ERROR line!\n";
				echo " ^  The server said: {$lineIn[1]}\n";
				$restartCount++;
				if($restartCount > 3) {
					echo "[!] The bot has already restarted three times consecutively.  The bot will now terminate.\n";
					die();
				} else {
					echo " ^  The bot will now restart.\n";
					goto start;
				}
			}

			//Process any nickchanges, including forced
			if($lineIn[1] == "NICK") {
				$lastPingTime = time();
				if($lineIn[0]['nick'] == $conf['nick']) {
					$conf['nick'] = trim($lineIn[2]);
					echo "[ ] My nickname changed to {$conf['nick']}...\n";
					reportToIRC($ircLink, "[INFO] Nickchange - Logged a nickname change to {$conf['nick']}.");
				}
			}

			//CTCPs and such
			if($lineIn[1] == "PRIVMSG") {
				$lastPingTime = time();
				if($lineIn[2] == $conf["nick"]) {
					$lineIn[3] = trim($lineIn[3]);
					if($lineIn[3] == chr(1) . "VERSION" . chr(1)) {
						echo "[ ] Responding to CTCP VERSION.\n";
						reportToIRC($ircLink, "[INFO] CTCP - Responded to CTCP VERSION from {$lineIn[0]['nick']}.");
						fwrite($ircLink, "NOTICE {$lineIn[0]['nick']} :\001VERSION FastLizard4's Wikilinking Bot (LizardLinky) version " . LIZARDLINKY_VERSION . ", https://gerrit.fastlizard4.org/r/gitweb?p=lizardlinky.git;a=summary or https://github.com/LizardNet/lizardlinky\001\r\n");
					} elseif($lineIn[3] == chr(1) . "CLIENTINFO" . chr(1)) {
						echo "[ ] Responding to CTCP CLIENTINFO.\n";
						reportToIRC($ircLink, "[INFO] CTCP - Responded to CTCP CLIENTINFO from {$lineIn[0]['nick']}.");
						fwrite($ircLink, "NOTICE {$lineIn[0]['nick']} :\001CLIENTINFO CLIENTINFO VERSION\001\r\n");
					}
				}
			}

			//KICKs and PARTs
			if($lineIn[1] == "KICK" || $lineIn[1] == "PART") {
				$lastPingTime = time();
				if($lineIn[3] == $conf['nick'] || $lineIn[0]["nick"] == $conf['nick']) {
					$lineIn[2] = trim($lineIn[2]);
					echo "[ ] Uhoh, I've been PART'd or KICK'd from {$lineIn[2]}!\n";
					if($lineIn[1] == "KICK" && trim($lineIn[4]) != "") {
						$lineIn[4] = trim($lineIn[4]);
						echo " ^  The reason for the kick was: {$lineIn[4]}\n";
						reportToIRC($ircLink, "[WARN] Kick - I was kicked from {$lineIn[2]} by {$lineIn[0]['full']} with message {$lineIn[3]}.");
					} elseif($lineIn[1] == "PART" && trim($lineIn[3]) != "") {
						$lineIn[3] = trim($lineIn[3]);
						echo " ^  The reason for the part was: {$lineIn[3]}\n";
						reportToIRC($ircLink, "[INFO] Part - I parted from {$lineIn[2]} with message {$lineIn[3]}.");
					} else {
						echo " ^  No reason was provided by IRC.\n";
						if($lineIn[1] == "KICK")
							reportToIRC($ircLink, "[WARN] Kick - I was kicked from {$lineIn[2]} by {$lineIn[0]['full']} for no reason!");
						else
							reportToIRC($ircLink, "[INFO] Part - I parted from {$lineIn[2]} with no part message.");
					}
				}
			}

			//Main body

			//"myaccess" command - tells a user what groups they are members of in the global ACL
			if($lineIn[3] == "{$conf['trigger']}myaccess") {
				$reportLine = "[INFO] User {$lineIn[0]['nick']} requested their access rights using the myaccess command in ";

				$response = "You were granted ";
				$groups = getGroups($lineIn[0]['full'], true);
				$numGroups = count($groups);
				$entryNumber = 0;

				if($groups === false) {
					$response = "You have no special permissions granted to you.";
				} else {
					foreach($groups as $group) {
						foreach($group as $groupName => $aclID) {
							$entryNumber++;
							$response .= "group '{$groupName}' by ACL entry {$aclID}";

							if($numGroups > 1) {
								if($entryNumber === $numGroups - 1) {
									$response .= ", and ";
								} else {
									$response .= ", ";
								}
							} else {
								$response .= ".";
							}
						}
					}
				}

				transmitLine($ircLink, $lineIn, $response, $reportLine);
			}

			//"die" command - allows a user to kill the bot, provided that they know the password.  Requires 'die' privilege.
//			if(preg_match('/^' . preg_quote($conf['trigger'], '/') . 'die/', $lineIn[3])) {
			if(strstr($lineIn[3], "{$conf['trigger']}die", true) === "") {
				$parameters = explode(' ', $lineIn[3]);

				$reportLine = false;

				//This is like argv; the command called is always the "zeroth" parameter.
				if(count($parameters) < 2) {
					$response = "Error: Too few parameters.";
				} elseif(count($parameters) > 2) {
					$response = "Error: Too many parameters.";
				} else {
					if($parameters[1] == $conf['diepass']) {
						if(hasPriv($lineIn[0]['full'], 'die')) {
							reportToIRC($ircLink, "[INFO] {$lineIn[0]['full']} told me to kill myself.  :(");
							fwrite($ircLink, "QUIT :Ordered to death by {$lineIn[0]['nick']} :(\r\n");
							fclose($ircLink);
							die();
						} else {
							$response = "Error: You do not have the necessary privileges to run this command.";
							$reportLine = "[WARN] User {$lineIn[0]['full']} attempted to use the 'die' command in ";
						}
					} else {
						$response = "Error: You didn't say the magic word!";
					}
				}

				transmitLine($ircLink, $lineIn, $response, $reportLine);
			}

			//"restart" command - like "die", except the bot restarts afterwards
			if(strstr($lineIn[3], "{$conf['trigger']}restart", true) === "") {
				$parameters = explode(' ', $lineIn[3]);

				$reportLine = false;

				//This is like argv; the command called is always the "zeroth" parameter.
				if(count($parameters) < 2) {
					$response = "Error: Too few parameters.";
				} elseif(count($parameters) > 2) {
					$response = "Error: Too many parameters.";
				} else {
					if($parameters[1] == $conf['diepass']) {
						if(hasPriv($lineIn[0]['full'], 'restart')) {
							reportToIRC($ircLink, "[INFO] {$lineIn[0]['full']} told me to restart.  :(");
							fwrite($ircLink, "QUIT :{$lineIn[0]['nick']} has asked me to restart\r\n");
							fclose($ircLink);
							unset($ircLink);

							//Alright, let's talk about this line.
							goto start;
							//Yes, I've heard the lecture about how gotos are bad and whatnot.  I've read the xkcd, listened
							//to professors and "professionals" alike babble on about it.  Perhaps goto is bad form, but in
							//this case, it's also the *easiest* way to "start over from the top".  Yeah, I could do some
							//weird stuff with exec(), or restructure the program's flow, and perhaps that would make this
							//program "better formed", but it's not worth the time or the effort.  Using goto is the easiest
							//solution, and the best solution to any problem is the easiest one.
						} else {
							$response = "Error: You do not have the necessary privileges to run this command.";
							$reportLine = "[WARN] User {$lineIn[0]['full']} attempted to use the 'restart' command in ";
						}
					} else {
						$response = "Error: You didn't say the magic word!";
					}
				}

				transmitLine($ircLink, $lineIn, $response, $reportLine);
			}

			//This block contains the global ACL manipulation commands
			if(strstr($lineIn[3], "{$conf['trigger']}acl", true) === "") {
				$parameters = explode(' ', $lineIn[3]);

				if($parameters[1] == "list") {
					//"acl list" command - lists all ACL entries, in PM to prevent spam, one ACL entry per line with an appropriate anti-flood delay
					if(hasPriv($lineIn[0]['full'], 'acl-list')) {
						if(count($parameters) > 2) {
							transmitLine($ircLink, $lineIn, "Error: Too many parameters for 'acl list' command.");
						} else {
							reportToIRC($ircLink, "[INFO] {$lineIn[0]['nick']} is requesting the ACL list.");
							if($lineIn[2] != $conf['nick']) {
								fwrite($ircLink, "PRIVMSG {$lineIn[2]} :{$lineIn[0]['nick']}: Please see private message.\r\n");
							}

							fwrite($ircLink, "PRIVMSG {$lineIn[0]['nick']} :I am about to send you my complete global ACL.  This may take some time depending on the number of entries, and I will let you know when I'm done.  Please be patient.\r\n");
							foreach($globalACL as $aclEntry) {
								if(is_null($aclEntry)) {
									continue;
								}
								fwrite($ircLink, "PRIVMSG {$lineIn[0]['nick']} :Entry ID {$aclEntry['acl_id']}: Hostmask '{$aclEntry['acl_hostmask']}', granted group '{$aclEntry['acl_group']}'\r\n");
								sleep(0.5);
							}
							fwrite($ircLink, "PRIVMSG {$lineIn[0]['nick']} :--- End of global ACL ---\r\n");
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} elseif($parameters[1] == "add") {
					//"acl add" command - takes two parameters, the hostmask and the group, and adds to the global ACL
					if(hasPriv($lineIn[0]['full'], 'acl-modify')) {
						if(count($parameters) < 4) {
							transmitLine($ircLink, $lineIn, "Error: Too few parameters for 'acl add' command.");
						} elseif(count($parameters) > 4) {
							transmitLine($ircLink, $lineIn, "Error: Too many parameters for 'acl add' command.");
						} else {
							if(!preg_match('/[^ !@]*![^ !@]*@[^ !@]*/', $parameters[2])) {
								transmitLine($ircLink, $lineIn, "Error: This hostmask doesn't look valid.  Did you get the argument order wrong?");
							} else {
								$mysqli = openDB($ircLink);
								$hostmask = $mysqli->real_escape_string($parameters[2]);
								$group = $mysqli->real_escape_string($parameters[3]);

								queryDB($ircLink, "INSERT INTO `{$conf['dbPrefix']}global_acl` (`acl_hostmask`, `acl_group`) VALUES ('{$hostmask}', '{$group}')", $result, $mysqli);
								if($result === false || $mysqli->affected_rows != 1) {
									$mysqli->close();
									transmitLine($ircLink, $lineIn, "Error: MySQL error.  Please see the reporting channel for details.");
								} else {
									$mysqli->close();
									transmitLine($ircLink, $lineIn, "Successfully added ACL entry to database, rereading ACL data....");
									reportToIRC($ircLink, "[INFO] {$lineIn[0]['nick']} added ACL entry for hostmask '{$parameters[2]}' granting group '{$parameters[3]}'.");
									goto aclReload;
								}
							}
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} elseif($parameters[1] == "setgroup") {
					//"acl setgroup" command - takes two parameters, the ACL entry ID and the group, and changes the indicated ACL entry to use the given group
					if(hasPriv($lineIn[0]['full'], 'acl-modify')) {
						if(count($parameters) < 4) {
							transmitLine($ircLink, $lineIn, "Error: Too few parameters for 'acl setgroup' command.");
						} elseif(count($parameters) > 4) {
							transmitLine($ircLink, $lineIn, "Error: Too many parameters for 'acl setgroup' command.");
						} else {
							$mysqli = openDB($ircLink);
							$entryID = $mysqli->real_escape_string($parameters[2]);
							$group = $mysqli->real_escape_string($parameters[3]);

							queryDB($ircLink, "UPDATE `{$conf['dbPrefix']}global_acl` SET `acl_group`='{$group}' WHERE `acl_id`='{$entryID}' LIMIT 1", $result, $mysqli);
							if(!$result || $mysqli->affected_rows != 1) {
								$mysqli->close();
								transmitLine($ircLink, $lineIn, "Error: MySQL error.  The reporting channel may have more details.  Check that you did not specify a nonexistent or invalid ACL entry ID.");
							} else {
								$mysqli->close();
								transmitLine($ircLink, $lineIn, "Successfully updated ACL entry, rereading ACL data....");
								foreach($globalACL as $aclEntry) {
									if($aclEntry['acl_id'] == $parameters[2]) {
										$oldGroup = $aclEntry['acl_group'];
										break;
									}
								}
								reportToIRC($ircLink, "[INFO] {$lineIn[0]['nick']} updated ACL entry {$parameters[2]}, setting group to '{$parameters[3]}' (previously was '{$oldGroup}').");
								goto aclReload;
							}
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} elseif($parameters[1] == "rm") {
					//"acl rm" command - takes one parameter, the ACL entry ID, and deletes that ACL entry
					if(hasPriv($lineIn[0]['full'], 'acl-modify')) {
						if(count($parameters) < 3) {
							transmitLine($ircLink, $lineIn, "Error: Too few parameters for 'acl rm' command.");
						} elseif(count($parameters) > 3) {
							transmitLine($ircLink, $lineIn, "Error: Too many parameters for 'acl rm' command.");
						} else {
							$mysqli = openDB($ircLink);
							$entryID = $mysqli->real_escape_string($parameters[2]);

							queryDB($ircLink, "DELETE FROM `{$conf['dbPrefix']}global_acl` WHERE `acl_id`='{$entryID}' LIMIT 1", $result, $mysqli);
							if(!$result || $mysqli->affected_rows != 1) {
								$mysqli->close();
								transmitLine($ircLink, $lineIn, "Error: MySQL error.  The reporting channel may have more details.  Check that you did not specify a nonexistent or invalid ACL entry ID.");
							} else {
								$mysqli->close();
								transmitLine($ircLink, $lineIn, "Successfully deleted ACL entry, rereading ACL data....");
								foreach($globalACL as $aclEntry) {
									if($aclEntry['acl_id'] == $parameters[2]) {
										$oldGroup = $aclEntry['acl_group'];
										$oldHostmask = $aclEntry['acl_hostmask'];
										break;
									}
								}
								reportToIRC($ircLink, "[INFO] {$lineIn[0]['nick']} \002deleted\002 ACL entry {$parameters[2]} (hostmask: '{$oldHostmask}', group: '{$oldGroup}').");
								goto aclReload;
							}
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} elseif($parameters[1] == "reload") {
					//"acl reload" command - forces a reload
					aclReload:
					if(hasPriv($lineIn[0]['full'], 'acl-modify')) {
						$globalACLOld = $globalACL;
						$globalACL = array();
						$success = true;
						try {
							doLoadACL();
						} catch(Exception $e) {
							$success = false;
						}
						if($success) {
							transmitLine($ircLink, $lineIn, "Done.");
							reportToIRC($ircLink, "[INFO] Global ACL data reloaded by {$lineIn[0]['nick']}.");
						} else {
							transmitLine($ircLink, $lineIn, "Failed.  Continuing with old ACL data!");
							reportToIRC($ircLink, "[WARN] Failed reloading global ACL data, as requested by {$lineIn[0]['nick']}; continuing with old data");
							$globalACL = $globalACLOld;
							unset($globalACLOld);
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} else {
					//No subcommand specified
					transmitLine($ircLink, $lineIn, "Error: Too few arguments.  Use one of 'acl list', 'acl add', 'acl setgroup', 'acl rm', or 'acl reload'.");
				}
			}
			//This block contains the commands used to manipulate the interwiki list.
			if(strstr($lineIn[3], "{$conf['trigger']}interwiki", true) === "") {
				$parameters = explode(' ', $lineIn[3]);

				if($parameters[1] == "list") {
					//"interwiki add" command - adds a new interwiki prefix
					if(hasPriv($lineIn[0]['full'], 'interwiki-list')) {
						if(count($parameters) > 2) {
							transmitLine($ircLink, $lineIn, "Error: Too many parameters for 'interwiki list' command.");
						} else {
							reportToIRC($ircLink, "[INFO] {$lineIn[0]['nick']} is requesting the interwiki prefix list.");
							if($lineIn[2] != $conf['nick']) {
								fwrite($ircLink, "PRIVMSG {$lineIn[2]} :{$lineIn[0]['nick']}: Please see private message.\r\n");
							}

							fwrite($ircLink, "PRIVMSG {$lineIn[0]['nick']} :I am about to send you my complete interwiki prefix list.  This may take some time depending on the number of entries, and I will let you know when I'm done.  Please be patient.\r\n");
							foreach($interwikis as $interwikiEntry) {
								if(is_null($interwikiEntry)) {
									continue;
								}
								fwrite($ircLink, "PRIVMSG {$lineIn[0]['nick']} :Interwiki prefix '{$interwikiEntry['interwiki_prefix']}:' points to '{$interwikiEntry['interwiki_target_url']}'\r\n");
								sleep(0.5);
							}
							fwrite($ircLink, "PRIVMSG {$lineIn[0]['nick']} :--- End of interwiki list ---\r\n");
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} elseif($parameters[1] == "add") {
					//"interwiki add" command - Takes two parameters, adds an interwiki prefix to the table.
					if(hasPriv($lineIn[0]['full'], 'interwiki-modify')) {
						if(count($parameters) < 4) {
							transmitLine($ircLink, $lineIn, "Error: Too few parameters for 'interwiki add' command.");
						} elseif(count($parameters) > 4) {
							transmitLine($ircLink, $lineIn, "Error: Too many parameters for 'interwiki add' command.");
						} else {
							if(!preg_match('/^([a-z][a-z:]*)?[a-z]$/', $parameters[2])) {
								transmitLine($ircLink, $lineIn, "Error: Invalid interwiki prefix.  Interwiki prefixes may only consist of lower-case letters 'a' through 'z', and though they may *contain* colons (':') to simulate \"nested\" prefixes, they must not begin or end with a colon.");
							} elseif(strstr($parameters[3], '$1') === false) {
								transmitLine($ircLink, $lineIn, "Error: Invalid target URL.  Target URL must contain the string \"\$1\", which is replaced by the page title during link expansion.");
							} else {
								$mysqli = openDB($ircLink);
								$prefix = $mysqli->real_escape_string($parameters[2]);
								$targetURL = $mysqli->real_escape_string($parameters[3]);

								queryDB($ircLink, "INSERT INTO `{$conf['dbPrefix']}interwiki` (`interwiki_prefix`, `interwiki_target_url`) VALUES ('{$prefix}', '{$targetURL}')", $result, $mysqli);
								if($result === false || $mysqli->affected_rows != 1) {
									$mysqli->close();
									transmitLine($ircLink, $lineIn, "Error: MySQL error.  Please see the reporting channel for details.");
								} else {
									$mysqli->close();
									transmitLine($ircLink, $lineIn, "Successfully added interwiki prefix to database, rereading interwiki data....");
									reportToIRC($ircLink, "[INFO] {$lineIn[0]['nick']} added to the database interwiki prefix '{$parameters[2]}:' pointing to target URL '{$parameters[3]}'.");
									goto interwikiReload;
								}
							}
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} elseif($parameters[1] == "rm") {
					//"interwiki rm" command - takes one parameter, the interwiki prefix, and deletes it from the interwiki list
					if(hasPriv($lineIn[0]['full'], 'interwiki-modify')) {
						if(count($parameters) < 3) {
							transmitLine($ircLink, $lineIn, "Error: Too few parameters for 'interwiki rm' command.");
						} elseif(count($parameters) > 3) {
							transmitLine($ircLink, $lineIn, "Error: Too many parameters for 'interwiki rm' command.");
						} else {
							$mysqli = openDB($ircLink);
							$prefix = $mysqli->real_escape_string($parameters[2]);

							queryDB($ircLink, "DELETE FROM `{$conf['dbPrefix']}interwiki` WHERE `interwiki_prefix`='{$prefix}' LIMIT 1", $result, $mysqli);
							if(!$result || $mysqli->affected_rows != 1) {
								$mysqli->close();
								transmitLine($ircLink, $lineIn, "Error: MySQL error.  The reporting channel may have more details.  Check that the interwiki prefix you specified exists (don't include the trailing colon).");
							} else {
								$mysqli->close();
								transmitLine($ircLink, $lineIn, "Successfully deleted interwiki entry, rereading interwiki data....");
								foreach($interwikis as $interwikiEntry) {
									if($interwikiEntry['interwiki_prefix'] == $parameters[2]) {
										$oldTarget = $interwikiEntry['interwiki_target_url'];
										break;
									}
								}
								reportToIRC($ircLink, "[INFO] {$lineIn[0]['nick']} \002deleted\002 interwiki prefix {$parameters[2]} (target URL: '{$oldTarget}').");
								goto interwikiReload;
							}
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} elseif($parameters[1] == "settarget") {
					//"interwiki settarget" command - takes two parameters, the interwiki prefix and a target URL, and changes the indicated prefix to use the new URL
					if(hasPriv($lineIn[0]['full'], 'interwiki-modify')) {
						if(count($parameters) < 4) {
							transmitLine($ircLink, $lineIn, "Error: Too few parameters for 'interwiki settarget' command.");
						} elseif(count($parameters) > 4) {
							transmitLine($ircLink, $lineIn, "Error: Too many parameters for 'interwiki settarget' command.");
						} else {
							if(strstr($parameters[3], '$1') === false) {
								transmitLine($ircLink, $lineIn, "Error: Invalid target URL.  Target URL must contain the string \"\$1\", which is replaced by the page title during link expansion.");
							} else {
								$mysqli = openDB($ircLink);
								$prefix = $mysqli->real_escape_string($parameters[2]);
								$target = $mysqli->real_escape_string($parameters[3]);

								queryDB($ircLink, "UPDATE `{$conf['dbPrefix']}interwiki` SET `interwiki_target_url`='{$target}' WHERE `interwiki_prefix`='{$prefix}' LIMIT 1", $result, $mysqli);
								if(!$result || $mysqli->affected_rows != 1) {
									$mysqli->close();
									transmitLine($ircLink, $lineIn, "Error: MySQL error.  The reporting channel may have more details.  Check that the interwiki prefix you specified exists (don't include the trailing colon).");
								} else {
									$mysqli->close();
									transmitLine($ircLink, $lineIn, "Successfully updated interwiki entry, rereading interwiki data....");
									foreach($interwikis as $interwikiEntry) {
										if($interwikiEntry['interwiki_prefix'] == $parameters[2]) {
											$oldTarget = $interwikiEntry['interwiki_target_url'];
											break;
										}
									}
									reportToIRC($ircLink, "[INFO] {$lineIn[0]['nick']} updated interwiki prefix {$parameters[2]}, setting target URL to '{$parameters[3]}' (previously was '{$oldTarget}').");
									goto interwikiReload;
								}
							}
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} elseif($parameters[1] == "reload") {
					interwikiReload:
					if(hasPriv($lineIn[0]['full'], 'interwiki-modify')) {
						$interwikisOld = $interwikis;
						$interwikis = array();
						$success = true;
						try {
							doLoadInterwikis();
						} catch(Exception $e) {
							$success = false;
						}
						if($success) {
							transmitLine($ircLink, $lineIn, "Done.");
							reportToIRC($ircLink, "[INFO] Interwiki data reloaded by {$lineIn[0]['nick']}.");
						} else {
							transmitLine($ircLink, $lineIn, "Failed.  Continuing with old interwiki data!");
							reportToIRC($ircLink, "[WARN] Failed reloading interwiki data, as requested by {$lineIn[0]['nick']}; continuing with old data");
							$interwikis = $interwikisOld;
							unset($interwikisOld);
						}
					} else {
						transmitLine($ircLink, $lineIn, "Error: You do not have the necessary privileges to run this command.");
					}
				} else {
					transmitLine($ircLink, $lineIn, "Error: Too few arguments.  Use one of 'intertwiki list', 'interwiki add', 'interwiki rm', 'interwiki settarget', or 'interwiki reload'.");
				}
			}
		}
	}
}

echo "\n[!] ERROR: Unexpected EOF.  Connection lost.\n";
$restartCount++;
$lastPingTime = time();
if($restartCount > 3) {
	echo "[!] The bot has already restarted three times consecutively.  The bot will now terminate.\n";
	die();
} else {
	echo " ^  Restarting bot....\n";
	goto start;
}
?>
