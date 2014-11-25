# LizardLinky - A Wikilink Expansion Bot

* LizardNet Code Review to GitHub mirroring status: [![Build Status](https://integration.fastlizard4.org:444/jenkins/buildStatus/icon?job=lizardlinky github mirror)](https://integration.fastlizard4.org:444/jenkins/job/lizardlinky%20github%20mirror/)

**Please note that LizardLinky is still in its infancy.**  It is not yet ready
for production use, and does not yet do everything described in this document
(as of this writing, it doesn't even expand wikilinks -- yet).

## Introduction

With the rise of Wikipedia has come the adoption of the wikilink to refer to
Wikipedia articles (and, more generally, wiki articles).  Wikilinks look like
this: `[[Main Page]]`; text surrounded by double-brackets, with the text within
the double brackets being the name of the article referred to.

LizardLinky is a bot that sits in IRC channels and looks for wikilinks like this.
When it sees them, it will automatically "expand" them by sending a complete URL
to the channel pointing to the wikilink's target.  By default, LizardLinky bases
its URLs off of Wikipedia, but this can be configured per-channel.

The name LizardLinky is derived from the name of a now long-defunct bot on the
freenode IRC network, called unilinky, which LizardLinky attempts to replicate in
functionality and features.

## License

**LizardLinky**

By Andrew "FastLizard4" Adams and the LizardLinky Development Team (see
AUTHORS.txt file)

Copyright (C) 2014 by Andrew "FastLizard4" Adams and the LizardLinky Development
Team. Some rights reserved.

License GPLv3+: GNU General Public License version 3 or later (at your choice):
<http://gnu.org/licenses/gpl.html>. This is free software: you are free to
change and redistribute it at your will provided that your redistribution, with
or without modifications, is also licensed under the GNU GPL. (Although not
required by the license, we also ask that you attribute us!) There is **NO
WARRANTY FOR THIS SOFTWARE** to the extent permitted by law.

Portions of this code are reused from a sister LizardNet project, the [LizardIRC
Network Operations Bot][NOC-Bot], which is also licensed under the GNU GPLv3+.

This is an open source project. The source Git repositories, which you are
welcome to contribute to, can be found here:
* [LizardNet Code Review (gerrit)][gerrit-repo]
* [LizardNet Code Explorer (gitblit)][gitblit-repo]

Gerrit Code Review for the project:
* [Code Review Dashboard][gerrit]

Alternatively, the project source code can be found on the PUBLISH-ONLY [mirror
on GitHub][github-repo].

Note: Pull requests and patches submitted to GitHub will be transferred by a
developer to Gerrit before they are acted upon.

[NOC-Bot]: <https://git.fastlizard4.org/gitblit/summary/?r=LizardIRC/NOC-Bot.git>
[gerrit-repo]: <https://gerrit.fastlizard4.org/r/gitweb?p=lizardlinky.git;a=summary>
[gitblit-repo]: <https://git.fastlizard4.org/gitblit/summary/?r=lizardlinky.git>
[gerrit]: <https://gerrit.fastlizard4.org/r/#/q/project:lizardlinky,n,z>
[github-repo]: <https://github.com/LizardNet/lizardlinky>
