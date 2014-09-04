mediawiki-maintenance
=====================

Convenient maintenance Scripts used for MediaWiki

* mergeUserBatch.php
Apply UserMerge (https://www.mediawiki.org/wiki/Extension:UserMerge) in batch for users that have no edits after a certain time.

* cleanupJobs.php
This script cleans all ongoing jobs. This should adapt both to MySQL and Redis environments.

* upgrade-MW.pl
Simple Perl script for upgrading MW.

* fillListText.php
Maintenance script for filling pages from a list using the text in a wiki. Convenient for DB-powered templates.