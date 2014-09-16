<?php
/**
 * Deletes a batch of pages from DB access
 * Usage: php deleteBatch.php [-u <user>] [-r <reason>] [-i <interval>] [-namespace <namespace number>] [-category <category name>]
 * [-commit] [-exclude <exclude list>]
 * where
 *	[listfile] is a file where each line contains the title of a page to be
 *             deleted, standard input is used if listfile is not given.
 *	<user> is the username
 *	<reason> is the delete reason
 *	<interval> is the number of seconds to sleep for after each delete
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
 * Maintenance script to delete a batch of pages.
 *
 * @ingroup Maintenance
 */
class DeleteBatchExtra extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Deletes a batch of pages";
		$this->addOption( 'u', "User to perform deletion", false, true );
		$this->addOption( 'r', "Reason to delete page", false, true );
		$this->addOption( 'i', "Interval to sleep between deletions" );
		$this->addOption( 'namespace', 'Namespace number, default all', false, true );
		$this->addOption( 'category', 'Category name, default none', false, true );
		$this->addOption( 'commit', 'Actually commit, otherwise print only', false, false, 'c' );
		$this->addOption( 'supress', 'Remove history from pages', false, false, 's' );
		$this->addOption( 'exclude', 'Pages not to be deleted', false, true );
	}

	public function execute() {
		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );
		$start = 0;

		# Options processing
		$username = $this->getOption( 'u', 'Delete page script' );
		$reason = $this->getOption( 'r', '' );
		$interval = $this->getOption( 'i', 0 );

		$ns = $this->getOption( 'namespace', -1 );
		$category = $this->getOption( 'category', false );
		$commit = $this->getOption('commit', false );
		$supress = $this->getOption('supress', false );

		$exclude = $this->getOption( 'exclude', '' );

		$user = User::newFromName( $username );
		if ( !$user ) {
			$this->error( "Invalid username", true );
		}
		$wgUser = $user;

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
			// $category = mysql_real_escape_string( $category, $dbr );
			$category = str_replace(" ", "_", $category);
			$ns_restrict.=" && cl_from = page_id && cl_to = '$category'";
		}


		$res = $dbw->select( $tables,
				$seltables,
				array(
				        "page_id >= $start" ,
				        $ns_restrict ),
				__METHOD__
		);
		
		$num = $dbw->numRows( $res );
		$this->output( "$num articles...\n" );
		$this->output( "EXCLUDED: ".$exclude."\n" );
		
		$i = 0;
		foreach ( $res as $row ) {

			self::actualDelete( $dbw, $row->page_id, $reason, $user, $commit, $exclude, $supress );
			if ( $interval ) {
				sleep( $interval );
			}
			wfWaitForSlaves();
		}

	}
	
	public function actualDelete( $dbw, $page_id, $reason, $user, $commit, $exclude, $supress ) {
	
		$title = Title::newFromID( $page_id );
		if ( is_null( $title ) ) {
			$this->output( "Invalid $page_id\n" );
			continue;
		}
		if ( !$title->exists() ) {
			$this->output( "Skipping nonexistent page with ID $page_id\n" );
			continue;
		}

		$titleText = $title->getPrefixedText();
		$this->output( $titleText."\t" );
		
		$dbw->begin( __METHOD__ );
		if ( $title->getNamespace() == NS_FILE ) {
			$img = wfFindFile( $title );
			if ( $img && $img->isLocal() && !$img->delete( $reason ) ) {
				$this->output( " FAILED to delete associated file... \n" );
			}
		}
		$page = WikiPage::factory( $title );
		$error = array();
		
		$excludeArr = array();
		
		if (! empty( $exclude ) ) {
			$excludeArr = explode( ";", $exclude );
		}
		
		if ( $commit && ! ( in_array( $titleText, $excludeArr ) ) ) {
			$success = $page->doDeleteArticle( $reason, $supress, 0, false, $error, $user );
			$dbw->commit( __METHOD__ );
			if ( $success ) {
				$this->output( " Deleted!\n" );
			} else {
				$this->output( " FAILED to delete article\n" );
			}
	
		}
	}
}

$maintClass = "DeleteBatchExtra";
require_once( RUN_MAINTENANCE_IF_MAIN );
