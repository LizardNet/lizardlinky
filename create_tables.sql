/*
* LizardLinky by FastLizard4 and the LizardLinky Development Team (see AUTHORS.txt file)
*
* Copyright (C) 2014 by FastLizard4 and the LizardLinky Development Team.  Some rights reserved.
* License GPLv3+: GNU General Public License version 3 or later (at your choice): <http://gnu.org/licenses/gpl.html>.
* This is free software: you are free to change and redistribute it at your will provided that your redistribution,
* with or without modifications, is also licensed under the GNU GPL.
*
* There is NO WARRANTY FOR THIS SOFTWARE to the extent permitted by law.
*
* Portions of this code are reused from a sister LizardNet project, the LizardIRC Network Operations Bot
* <https://git.fastlizard4.org/gitblit/summary/?r=LizardIRC/NOC-Bot.git>, which is also licensed under the GNU
* GPLv3+.
*
* This is an open source project. The source Git repositories, which you are
* welcome to contribute to, can be found here:
* <https://gerrit.fastlizard4.org/r/gitweb?p=lizardlinky.git;a=summary>
* <https://git.fastlizard4.org/gitblit/summary/?r=lizardlinky.git>
*
* Gerrit Code Review for the project:
* <https://gerrit.fastlizard4.org/r/#/q/project:lizardlinky,n,z>
*
* Alternatively, the project source code can be found on the PUBLISH-ONLY mirror
* on GitHub: <https://github.com/LizardNet/lizardlinky>
*
* Note: Pull requests and patches submitted to GitHub will be transferred by a
* developer to Gerrit before they are acted upon.
*/

-- This basic SQL script creates the tables needed to operate LizardLinky
-- You may wish to change the database name as appropriate, and add prefixes
-- to the table as appropriate (see $conf['dbPrefix'] in the LizardLinky
-- configuration file.

CREATE DATABASE `lizardlinky`;

USE `lizardlinky`;

CREATE TABLE `global_acl` (
 `acl_id` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `acl_hostmask` varchar(128) NOT NULL,
 `acl_group` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `channels` (
 `channel_id` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `channel_name` varchar(64) NOT NULL,
 `channel_default_url` varchar(256) NOT NULL DEFAULT 'https://en.wikipedia.org/wiki/$1',
 `channel_responsible_nick` varchar(64) NOT NULL,
 `channel_privileged_access` enum('+','%','@','&','~') NOT NULL DEFAULT '@',
 `channel_status` enum('active','request','disabled') NOT NULL DEFAULT 'disabled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `interwiki` (
 `interwiki_id` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `interwiki_prefix` varchar(32) NOT NULL,
 `interwiki_target_url` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
