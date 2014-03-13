<?php
/**
 * MergeUserBatch from DB access
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
class MergeUserBatch extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Deletes a batch of pages";
		$this->addOption( 'u', "User to perform deletion", false, true );
		$this->addOption( 'r', "Reason to delete page", false, true );
		$this->addOption( 'i', "Interval to sleep between deletions" );
		$this->addOption( 'namespace', 'Namespace number, default all', false, true );
		$this->addOption( 'category', 'Category name, default none', false, true );
		$this->addOption( 'commit', 'Actually commit, otherwise print only', false, false, 'c' );
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

		$i = 0;
		foreach ( $res as $row ) {

			self::actualDelete( $dbw, $row->page_id, $reason, $user, $commit, $exclude );
			if ( $interval ) {
				sleep( $interval );
			}
			wfWaitForSlaves();
		}
		
		$objOldUser = User::newFromName( $olduser_text );
		$olduserID = $objOldUser->idForName();
		
		$objNewUser = User::newFromName( $newuser_text );
		$newuserID = $objNewUser->idForName();

		// Execute
		self::mergeEditcount( $newuserID, $olduserID );
		self::mergeUser( $newuser_text, $newuserID, $olduser_text, $olduserID );
		
		if ( $deleteUser ) {
			self::movePages( $newuser_text, $olduser_text );
			self::deleteUser( $olduserID, $olduser_text);
		}


	}
	

	/**
	 * Function to delete users following a successful mergeUser call
	 *
	 * Removes user entries from the user table and the user_groups table
	 *
	 * @param $olduserID int ID of user to delete
	 * @param $olduser_text string Username of user to delete
	 *
	 * @return Always returns true - throws exceptions on failure.
	 */
	private function deleteUser( $olduserID, $olduser_text ) {
		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'user_groups', array( 'ug_user' => $olduserID ) );
		$dbw->delete( 'user', array( 'user_id' => $olduserID ) );
		// $wgOut->addHTML( wfMsg( 'usermerge-userdeleted', $olduser_text, $olduserID ) );

		// $log = new LogPage( 'usermerge' );
		// $log->addEntry( 'deleteuser', $wgUser->getUserPage(), '', array( $olduser_text, $olduserID ) );

		$users = $dbw->selectField( 'user', 'COUNT(*)', array() );
		$dbw->update( 'site_stats',
			array( 'ss_users' => $users ),
			array( 'ss_row_id' => 1 ) );
		return true;
	}


	/**
	 * Function to merge database references from one user to another user
	 *
	 * Merges database references from one user ID or username to another user ID or username
	 * to preserve referential integrity.
	 *
	 * @param $newuser_text string Username to merge references TO
	 * @param $newuserID int ID of user to merge references TO
	 * @param $olduser_text string Username of user to remove references FROM
	 * @param $olduserID int ID of user to remove references FROM
	 *
	 * @return Always returns true - throws exceptions on failure.
	 */
	private function mergeUser( $newuser_text, $newuserID, $olduser_text, $olduserID ) {
		global $wgUser;

		$idUpdateFields = array(
			array('archive','ar_user'),
			array('revision','rev_user'),
			array('filearchive','fa_user'),
			array('image','img_user'),
			array('oldimage','oi_user'),
			array('recentchanges','rc_user'),
			array('logging','log_user'),
			array('ipblocks', 'ipb_id'),
			array('ipblocks', 'ipb_by'),
			array('watchlist', 'wl_user'),
		);

		$textUpdateFields = array(
			array('archive','ar_user_text'),
			array('revision','rev_user_text'),
			array('filearchive','fa_user_text'),
			array('image','img_user_text'),
			array('oldimage','oi_user_text'),
			array('recentchanges','rc_user_text'),
			array('ipblocks','ipb_address'),
			array('ipblocks','ipb_by_text'),
		);

		$dbw = wfGetDB( DB_MASTER );

		foreach ( $idUpdateFields as $idUpdateField ) {
			$dbw->update( $idUpdateField[0], array( $idUpdateField[1] => $newuserID ), array( $idUpdateField[1] => $olduserID ) );
			// $wgOut->addHTML( wfMsg( 'usermerge-updating', $idUpdateField[0], $olduserID, $newuserID ) . "<br />\n" );
		}

		foreach ( $textUpdateFields as $textUpdateField ) {
			$dbw->update( $textUpdateField[0], array( $textUpdateField[1] => $newuser_text ), array( $textUpdateField[1] => $olduser_text ) );
			// $wgOut->addHTML( wfMsg( 'usermerge-updating', $textUpdateField[0], $olduser_text, $newuser_text ) . "<br />\n" );
		}

		$dbw->delete( 'user_newtalk', array( 'user_id' => $olduserID ));

		// $wgOut->addHTML( "<hr />\n" . wfMsg( 'usermerge-success', $olduser_text, $olduserID, $newuser_text, $newuserID ) . "\n<br />" );

		// $log = new LogPage( 'usermerge' );
		// $log->addEntry( 'mergeuser', $wgUser->getUserPage(), '', array( $olduser_text, $olduserID, $newuser_text, $newuserID ) );

		return true;
	}
	

	/**
	 * Function to add edit count
	 *
	 * Adds edit count of both users
	 *
	 * @param $newuserID int ID of user to merge references TO
	 * @param $olduserID int ID of user to remove references FROM
	 *
	 * @return Always returns true - throws exceptions on failure.
	 *
	 * @author Matthew April <Matthew.April@tbs-sct.gc.ca>
	 */
	private function mergeEditcount( $newuserID, $olduserID ) {

		$dbw = wfGetDB( DB_MASTER );
		
		# old user edit count
		$result = $dbw->selectField( 'user',
				'user_editcount',
				array( 'user_id' => $olduserID ),
				__METHOD__
			  );
		$row = $dbw->fetchRow($result);
		
		$oldEdits = $row[0];
		
		# new user edit count
		$result = $dbw->selectField( 'user',
				'user_editcount',
				array( 'user_id' => $newuserID ),
				__METHOD__
			  );
		$row = $dbw->fetchRow($result);
		$newEdits = $row[0];
		
		# add edits
		$totalEdits = $oldEdits + $newEdits;
		
		# don't run querys if neither user has any edits
		if( $totalEdits > 0 ) {
			# update new user with total edits
			$dbw->update( 'user',
				array( 'user_editcount' => $totalEdits ),
				array( 'user_id' => $newuserID ),
				__METHOD__
			);
			
			#clear old users edits
			$dbw->update( 'user',
				array( 'user_editcount' => 0 ),
				array( 'user_id' => $olduserID ),
				__METHOD__
			);
		}
		
		// $wgOut->addHTML( wfMsgForContent( 'usermerge-editcount-success', $olduserID, $newuserID ) . "<br />\n" );

		return true;
	}
	

	/**
	 * Function to merge user pages
	 *
	 * Deletes all pages when merging to Anon
	 * Moves user page when the target user page does not exist or is empty
	 * Deletes redirect if nothing links to old page
	 * Deletes the old user page when the target user page exists
	 *
	 * @param $newuser_text string Username to merge pages TO
	 * @param $olduser_text string Username of user to remove pages FROM
	 *
	 * @return returns true on completion
	 *
	 * @author Matthew April <Matthew.April@tbs-sct.gc.ca>
	 */
	private function movePages( $newuser_text, $olduser_text ) {
		global $wgContLang, $wgUser;
		
		$oldusername = trim( str_replace( '_', ' ', $olduser_text ) );
		$oldusername = Title::makeTitle( NS_USER, $oldusername );
		$newusername = Title::makeTitleSafe( NS_USER, $wgContLang->ucfirst( $newuser_text ) );
		
		# select all user pages and sub-pages
		$dbr = wfGetDB( DB_SLAVE );
		$oldkey = $oldusername->getDBkey();
		$pages = $dbr->select( 'page',
				array( 'page_namespace', 'page_title' ),
				array( 'page_namespace IN (' . NS_USER . ',' . NS_USER_TALK . ')',
					'page_title' . $dbr->buildLike( $oldusername->getDBkey() . '/', $dbr->anyString() )
					.' OR page_title = ' . $dbr->addQuotes( $oldusername->getDBkey() )
				)
			 );

		// $output = '';
		// $skin = $wgUser->getSkin();

		foreach ( $pages as $row ) {
			$oldPage = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
			$newPage = Title::makeTitleSafe( $row->page_namespace, 
				preg_replace( '!^[^/]+!', $newusername->getDBkey(), $row->page_title ) );

			
			if( $newuser_text === "Anonymous" ) { # delete ALL old pages
				
				if( $oldPage->exists() ) {
					$oldPageArticle = new Article( $oldPage, 0 );
					$oldPageArticle->doDeleteArticle( wfMsgHtml( 'usermerge-autopagedelete' ) );
					
					// $oldLink = $skin->linkKnown( $oldPage );
					// $output .= '<li class="mw-renameuser-pe">' . wfMsgHtml( 'usermerge-page-deleted', $oldLink ) . '</li>';
				}
				
			} elseif( $newPage->exists() && !$oldPage->isValidMoveTarget( $newPage ) && $newPage->getLength() > 0) { # delete old pages that can't be moved
				
				$oldPageArticle = new Article( $oldPage, 0 );
				$oldPageArticle->doDeleteArticle( wfMsgHtml( 'usermerge-autopagedelete' ) );
				
				// $link = $skin->linkKnown( $oldPage );
				// $output .= '<li class="mw-renameuser-pe">' . wfMsgHtml( 'usermerge-page-deleted', $link ) . '</li>';
				
			} else { # move content to new page
				
				# delete target page if it exists and is blank
				if( $newPage->exists() ) {
					$newPageArticle = new Article( $newPage, 0 );
					$newPageArticle->doDeleteArticle( 'usermerge-autopagedelete' );
				}
				
				# move to target location
				$success = $oldPage->moveTo( $newPage, false, wfMsgForContent( 'usermerge-move-log', 
					$oldusername->getText(), $newusername->getText() ) );
				// if( $success === true ) {
				//	$oldLink = $skin->linkKnown(
				//			$oldPage,
				//			null,
				//			array(),
				//			array( 'redirect' => 'no' )
				//	);
				//	$newLink = $skin->linkKnown( $newPage );
					// $output .= '<li class="mw-renameuser-pm">' . wfMsgHtml( 'usermerge-page-moved', $oldLink, $newLink ) . '</li>';
				// } else {
				// 	$oldLink = $skin->linkKnown( $oldPage );
				// 	$newLink = $skin->linkKnown( $newPage );
					// $output .= '<li class="mw-renameuser-pu">' . wfMsgHtml( 'usermerge-page-unmoved', $oldLink, $newLink ) . '</li>';
				// }
				
				# check if any pages link here
				$res = $dbr->selectField( 'pagelinks',
						'pl_title',
						array( 'pl_title' => $olduser_text ),
						__METHOD__
				);
				if( !$dbr->numRows( $res ) ) {
						# nothing links here, so delete unmoved page/redirect
						$oldPageArticle = new Article( $oldPage, 0 );
						$oldPageArticle->doDeleteArticle( wfMsgHtml( 'usermerge-autopagedelete' ) );
				}

			}
		}
		
		// if ( $output ) {
		// 	$wgOut->addHTML( '<ul>' . $output . '</ul>' );
		// }
		
		return true;
	}
}

$maintClass = "MergeUserBatch";
require_once( RUN_MAINTENANCE_IF_MAIN );
