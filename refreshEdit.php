<?php
/**
 * Refresh edit tables.
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
 * Maintenance script to refresh link tables.
 *
 * @ingroup Maintenance
 */
class RefreshEdit extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Refresh link tables";
		$this->addOption( 'new-only', 'Only affect articles with just a single edit' );
		$this->addOption( 'purge', 'Do purge instead of edit' );
		$this->addOption( 'm', 'Maximum replication lag', false, true );
		$this->addArg( 'start', 'Page_id to start from, default 1', false );
		$this->addOption( 'namespace', 'Namespace number, default all', false, true );
		$this->addOption( 'category', 'Category name, default none', false, true );
		$this->addOption( 'rewrite', 'Rewriting of the content, default 1', false, true );
		$this->addOption( 'u', 'User to run the script', false, true );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$max = $this->getOption( 'm', 0 );
		$start = $this->getArg( 0, 1 );
		$new = $this->getOption( 'new-only', false );
		$purge = $this->getOption( 'purge', false );
		$ns = $this->getOption( 'namespace', -1 );
		$category = $this->getOption( 'category', false );
		$rewrite = $this->getOption( 'rewrite', 1 );
		$u = $this->getOption( 'u', false );

		$this->doRefreshEdit( $start, $new, $max, $ns, $category, $rewrite, $u, $purge );
	}

	/**
	 * Do the actual link refreshing.
	 * @param $start int Page_id to start from
	 * @param $newOnly bool Only do pages with 1 edit
	 * @param $maxLag int Max DB replication lag
	 * @param $end int Page_id to stop at
	 */

	private function doRefreshEdit( $start, $newOnly = false, $maxLag = false, $ns = -1, $category = false, $rewrite = 1, $u = false, $purge = false ) {

		$reportingInterval = 100;
		$dbr = wfGetDB( DB_SLAVE );
		$start = intval( $start );
		$ns_restrict = "page_namespace > -1";
		$tables = array('page');
		$seltables = array( 'page_id' );


		// Need to do for NS
		if ( $ns > -1 ) {
			if ( is_numeric( $ns ) ) {
				$ns_restrict = "page_namespace = $ns";
			}
		}

		// For categories
		if ( $category ) {
			array_push( $tables, "categorylinks" );
			$category = addslashes( $category );
			$category = str_replace(" ", "_", $category);
			$ns_restrict.=" && cl_from = page_id && cl_to = '$category'";
		}

		// Default, no user
		$user = null;

		if ( $u ) {
			$user = User::newFromName( $u );
		}

		if ( $newOnly ) {

			$res = $dbr->select( $tables,
				$seltables,
				array(
					'page_is_new' => 1,
					"page_id >= $start" ,
					$ns_restrict ),
				__METHOD__
			);
			$num = $dbr->numRows( $res );
			$this->output( "$num new articles...\n" );

			$i = 0;
			foreach ( $res as $row ) {
				if ( !( ++$i % $reportingInterval ) ) {
					$this->output( "$i\n" );
					wfWaitForSlaves(); //Doubt if necessary
				}

				self::fixEditFromArticle( $row->page_id, $rewrite, $user, $purge );
				
			}
		} else {

			$res = $dbr->select( $tables,
				$seltables,
				array(
					"page_id >= $start" ,
					$ns_restrict ),
				__METHOD__
			);
			$num = $dbr->numRows( $res );
			$this->output( "$num articles...\n" );

			$i = 0;
			foreach ( $res as $row ) {
				if ( !( ++$i % $reportingInterval ) ) {
					$this->output( "$i\n" );
					wfWaitForSlaves(); // Doubt if necessary
				}

				self::fixEditFromArticle( $row->page_id, $rewrite, $user, $purge );
				
			}
		}
	}


	/**
	 * Run fixEditFromArticle for all links on a given page_id
	 * @param $id int The page_id
	 */
	public static function fixEditFromArticle( $id, $rewrite, $user, $purge ) {

		$page = WikiPage::newFromID( $id );

		if ( $page === null ) {
			return;
		}

		//$dbw = wfGetDB( DB_MASTER );
		//$dbw->begin( __METHOD__ );

		$i = 0;

		while ( $i < $rewrite ) {

			if ( $purge ) {
				$page->doPurge();
			} else {
			
				$text = $page->getRawText();
				if ( $text === false ) {
					return;
				}
				$page->doEdit( $text, 'Edit Maintenance', EDIT_FORCE_BOT, false, $user );
			}
			$i++;
		}

		//$dbw->commit( __METHOD__ );
	}

}

$maintClass = 'RefreshEdit';
require_once( RUN_MAINTENANCE_IF_MAIN );
