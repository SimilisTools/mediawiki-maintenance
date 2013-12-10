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
		// Check Approval -> Last revision is approved revision
		$this->addOption( 'approved', 'Only last approved' );
		$this->addOption( 'm', 'Maximum replication lag', false, true );
		$this->addOption( 'start', 'Page_id to start from, default 1', false, true );
		// Last modifications. Remove new-only
		$this->addOption( 'old', 'Handle only pages touched at most n minutes', false, true );
		$this->addOption( 'id', 'Check specific page ID', false, true );
		$this->addOption( 'namespace', 'Namespace number, default all', false, true );
		$this->addOption( 'rewrite', 'Rewriting of the content, default 1', false, true );
		$this->addOption( 'u', 'User to run the script', false, true );
		$this->setBatchSize( 100 );
		// Change options


	}

	public function execute() {
		$max = $this->getOption( 'm', 0 );
		$start = $this->getOption( 'start', 1 );
		$old = $this->getOption ( 'old', 0 );
		$id = $this->getOption ( 'id', 0 );
		$approved = $this->getOption( 'approved', true );
		$ns = $this->getOption( 'namespace', -1 );
		$category = $this->getOption( 'category', false );
		$rewrite = $this->getOption( 'rewrite', 1 );
		$u = $this->getOption( 'u', false );
		
		$this->doRefreshEdit( $start, $old, $id, $approved, $max, $ns, $category, $rewrite, $u );
	}

	/**
	 * Do the actual link refreshing.
	 * @param $start int Page_id to start from
	 * @param $newOnly bool Only do pages with 1 edit
	 * @param $maxLag int Max DB replication lag
	 * @param $end int Page_id to stop at
	 */

	private function doRefreshEdit( $start, $old = 0, $id = 0, $approved = true, $maxLag = false, $ns = -1, $category = false, $rewrite = 1, $u = false ) {

		global $wgDBprefix;
		
		$reportingInterval = 100;
		$dbr = wfGetDB( DB_SLAVE );
		$start = intval( $start );
		$old = intval( $old );
		$id = intval( $id );
		$ns_restrict = $wgDBprefix."page.page_namespace > -1";
		$tables = array('page');
		$seltables = array( $wgDBprefix.'page.page_id' );


		// Need to do for NS
		if ( $ns > -1 ) {
			if ( is_numeric( $ns ) ) {
				$ns_restrict = " ".$wgDBprefix."page.page_namespace = $ns";
			}
		}

		// Approved latest
		if ( $approved ) {
		
			array_push( $tables, "approved_revs" );
			// Only rev_id that are latest
			$ns_restrict.=" AND ( ".$wgDBprefix."approved_revs.rev_id = ".$wgDBprefix."page.page_latest )";
			
		}
		
		// Only if some values
		if ( $old > 0 ) {
			// From min to miliseconds
			$old = $old*3600;
			array_push( $tables, "revision" );
			$timestamp = wfTimestamp( TS_MW ) - $old;
			// We check page_touched
			$ns_restrict.= " AND ( ".$wgDBprefix."revision.rev_id = ".$wgDBprefix."page.page_latest ) ";
			$ns_restrict.= " AND ( ".$wgDBprefix."revision.rev_timestamp > $timestamp ) ";

		}
		// Only if a value
		if ( $id > 0 ) {
		
			$ns_restrict.= " AND ( ".$wgDBprefix."page.page_id = ".$id." ) ";
		}


		$res = $dbr->select( $tables,
			$seltables,
			array(
				$wgDBprefix."page.page_id >= $start" ,
				$ns_restrict ),
			__METHOD__
		);
		$num = $dbr->numRows( $res );
		$this->output( "$num articles...\n" );

		$i = 0;
		foreach ( $res as $row ) {
			if ( !( ++$i % $reportingInterval ) ) {
				$this->output( "$i\n" );
				wfWaitForSlaves();
			}

			self::fixEditFromArticle( $row->page_id, $rewrite, $u );
			
		}
	}


	/**
	 * Run fixEditFromArticle for all links on a given page_id
	 * @param $id int The page_id
	 */
	public static function fixEditFromArticle( $id, $rewrite, $u ) {

		// Default, no user
		$user = null;

		if ( $u ) {
			$user = User::newFromName( $u );
		}

		$page = WikiPage::newFromID( $id );

		if ( $page === null ) {
			return;
		}

		$text = $page->getRawText();
		if ( $text === false ) {
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin( __METHOD__ );

		$i = 0;

		while ( $i < $rewrite ) {

			// $page->doEdit( $text, 'Edit Maintenance', EDIT_FORCE_BOT, false, $user ); Not working in some cases
			self::externalCall( $page );
			$i++;
		}

		$dbw->commit( __METHOD__ );
	}

	private static function externalCall( $page ) {
	
		global $externalCallApp:
		
		$titleText = $page->getTitle()->getPrefixedText();
		
		if ( !empty( $title ) && !empty( $externalCallApp ) ) {
			
			$descriptorspec = array(
				array('pipe', 'r'),               // stdin
				array('pipe', 'r'), // stdout
				array('file', '/tmp/EditApprove.log', 'w'),               // stderr -> Generate one temp?
			);
			
			$proc = proc_open("$externalCallApp \"$titleText\"", $descriptorspec, $pipes);
		}
		
	
	}

}

$maintClass = 'RefreshEdit';
require_once( RUN_MAINTENANCE_IF_MAIN );
