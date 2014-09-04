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
class FillListText extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Fill pages from list";
		$this->addOption( 'list', 'File with list', false, true );
		$this->addOption( 'text', 'Text to introduce', false, true );
		$this->addOption( 'overwrite', 'Rewriting of the content, default 1', false, true );
		$this->addOption( 'u', 'User to run the script', false, true );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$list = $this->getOption( 'list', false );
		$textfile = $this->getOption( 'text', false );
		$overwrite = $this->getOption( 'overwrite', false );
		$u = $this->getOption( 'u', false );

		if ( $list !== false && $textfile !== false ) {

			$this->doFillText( $list, $textfile, $overwrite, $u );

		}
	}

	private function doFillText( $list, $textfile, $overwrite = false, $u = false ) {

		//TODO: Check text file exists

		// Get text content
		$text = file_get_contents( $textfile );

		//TODO: Check list file exists
		//TODO: Open list
		

	}

	/**
	 * Run fixEditFromArticle for all links on a given page_id
	 * @param $id int The page_id
	 */
	public static function submitArticle( $textTitle, $text, $overwrite, $u ) {

		// Default, no user
		$user = null;

		if ( $u ) {
			$user = User::newFromName( $u );
		}

		$title = Title::newFromText( $textTitle );

		$write = true;

		// Caution here
		if ( $title->exists() && $overwrite === false ) {
			$write = false;
		}

		if ( $write === true ) {

			$page = WikiPage::factory( $title );
	
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin( __METHOD__ );
	
			$page->doEdit( $text, 'Fill content', EDIT_FORCE_BOT, false, $user );
	
			$dbw->commit( __METHOD__ );
		}
	}

}

$maintClass = 'FillListText';
require_once( RUN_MAINTENANCE_IF_MAIN );