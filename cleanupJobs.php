<?php
/**
 * cleanupJobs.php deletes all ongoing jobs of a DB
 * It should adapt to MySQL and Redis paradigms 
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Maintenance
 */

require_once( __DIR__ . '/Maintenance.php' );

/**
 * Maintenance script to clean Jobs list
 *
 * @ingroup Maintenance
 */
class cleanupJobs extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Cleans up jobs queue";
		$this->addOption( 'storage', 'Choose storing mechanism', false, 'mysql', 's' );
		$this->addOption( 'commit', 'Actually do the process', false, false, 'c' );
	}

	public function execute() {

		global $wgDBprefix;

		$dbw = wfGetDB( DB_MASTER );

		$jobsTable = $wgDBprefix."job";

		# Options processing
		$storage = $this->getOption('storage', 'mysql' );
		$commit = $this->getOption('commit', false );
		
		// $this->output( $storage."\n" );
		
		if ( $commit ) {
		
			if ( $storage == 'redis' ) {

				// We should check
				global $wgJobTypeConf;
				// We assume default
				if ( array_key_exists( 'redisServer', $wgJobTypeConf['default'] ) ) {
					
					$redisServer = $wgJobTypeConf['default']['redisServer'];
					
					$redisServerArr = explode( ":", $redisServer );
					
					// we ensure and check we have a port number 
					if ( isset( $redisServerArr[1] ) && is_numeric( $redisServerArr[1] ) ) {
					
						$redis = new Redis();
						$redis->connect( $redisServerArr[0], $redisServerArr[1] );
						// We assume 0 DB. So we should have different instances of Redis
						// Be careful!
						$redis->flushDB();
					}
				}
				
		
			} else {
				// Actual cleaning
				$res = $dbw->query("truncate table ".$jobsTable);
			}
		
		}
		
		return true;

	}

}

$maintClass = "cleanupJobs";
require_once( RUN_MAINTENANCE_IF_MAIN );
