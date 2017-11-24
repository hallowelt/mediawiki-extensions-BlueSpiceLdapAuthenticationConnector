<?php

/**
 * Maintenance script
 *
 * @file
 * @ingroup Maintenance
 * @author Patric Wirth <wirth@hallowelt.com>
 * @licence GNU General Public Licence 3.0
 */
$sBaseDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
require_once( "$sBaseDir/maintenance/Maintenance.php" );
require_once( "$sBaseDir/extensions/BlueSpiceDistribution/UserMerge/MergeUser.php" );
//Moving user pages is already encapsulated in UserMerge
//make this public give access for move only
class RenameMergeUser extends MergeUser {
	public function movePages(User $performer, /* callable */ $msg) {
		return parent::movePages( $performer, $msg );
	}
}

class RenameAndMergeUserToStrLower extends Maintenance {
	protected static $aRequiredClasses = array(
		'SpecialUserMerge', //Extension UserMerge
		'UserMergeConnector', //Extension UserMergeConnector
		'BsCoreHooks', //Extension BlueSpiceFoundation
		'RenameuserSQL', //Extension RenameUser
	);
	protected $iStartID = 2;
	protected $bProtectSysops = true;
	protected $bExecute = false;
	protected $oPerformer = null;

	private static $aUser = null;
	private static $aHandledUserIDs = array();

	public function __construct() {
		parent::__construct();

		$this->addOption(
			'startID',
			'Define the userID to start with (default is 2)',
			false,
			false
		);
		//not implemented jet
		/*$this->addOption(
			'protectSysops',
			'Sysops will not be merged (default is true)',
			false,
			false
		);*/
		$this->addOption(
			'execute',
			'Really executes the script (default is false)',
			false,
			false
		);
		$this->addOption(
			'performer',
			'Username of User to use as perfromer of the script (default is WikiSysop)',
			false,
			false
		);
	}

	public function execute() {
		$this->iStartID = (int) $this->getOption(
			'startID',
			2
		);
		$this->bProtectSysops = (bool) $this->getOption(
			'protectSysops',
			true
		);
		$this->bExecute = (bool) $this->getOption(
			'execute',
			false
		);
		$sPerformerName = (string) $this->getOption(
			'perfromer',
			'WikiSysop'
		);
		$this->oPerformer = User::newFromName( $sPerformerName );
		$oSpecialUserMerge = SpecialPageFactory::getPage('UserMerge');

		echo "Getting started...\n\n";
		$oStatus = $this->checkRequirements();
		if( !$oStatus->isGood() ) {
			echo $oStatus->getWikiText()."\n";
			return;
		}

		echo "Getting double users...\n";
		$aUser = $this->getMergeUsers();
		if( empty($aUser) ) {
			echo "...nothing to do here\n";
		}

		foreach( $aUser as $sLowerName => $aIDs ) {
			$i = 0;
			while( isset($aIDs[$i+1]) ) {
				self::$aHandledUserIDs[] = $aIDs[$i];
				$oUserFrom = User::newFromid( $aIDs[$i] );
				$oUserTo = User::newFromid( $aIDs[$i+1] );
				echo "merge {$oUserFrom->getName()} => {$oUserTo->getName()}\n";

				$oMergeUser = new MergeUser(
					$oUserFrom,
					$oUserTo,
					new UserMergeLogger()
				);
				if( $this->bExecute ) {
					try {
						$oMergeUser->merge( $this->oPerformer );
						$oMergeUser->delete(
							$this->oPerformer,
							array( $oSpecialUserMerge, 'msg' )
						);
					} catch( Exception $e ) {
						echo $e->getMessage();
					}
				}
				$i++;
			}
		}

		echo "Getting users to rename...\n";
		$aUsers = $this->getRenamingUsers();

		if( empty($aUsers) ) {
			echo "...nothing to do here\n";
		}
		foreach( $aUsers as $iID => $sToName ) {
			$oUserFrom = User::newFromId($iID);
			echo "Renameing {$oUserFrom->getName()} => $sToName...";
			$oUserTo = User::newFromName($sToName);
			if( is_null($oUserTo) ) {
				echo "...$sToName is not a valid username\n";
			}
			if( $oUserTo->getId() > 0 ) {
				echo "...can not merge into an existing user: $sToName\n";
			}
			$rename = new RenameuserSQL(
				$oUserFrom->getName(),
				$oUserTo->getName(),
				$iID
			);
			if( $this->bExecute )
				try {
					if( !$rename->rename() ) {
						echo "...could not be renamed\n";
						continue;
					}
					$this->movePages(
						$oUserFrom,
						$oUserTo
					);
				} catch( Exception $e ) {
					echo $e->getMessage();
				}
				echo "...done\n";
		}
	}

	protected function checkRequirements() {
		foreach( static::$aRequiredClasses as $sClassName ) {
			if( class_exists($sClassName) ) {
				continue;
			}
			return Status::newFatal(
				"Searched very hard but couldnt find the class: $sClassName"
			);
		}
		if( is_null($this->oPerformer) || $this->oPerformer->getId() == 0 ) {
			$sPerformerName = (string) $this->getOption(
				'perfromer',
				'WikiSysop'
			);
			return Status::newFatal(
				"Performing is is not valid $sPerformerName"
			);
		}
		return Status::newGood(':)');
	}

	protected function getRenamingUsers( $a = array() ) {
		foreach($this->getUsers() as $iID => $sName) {
			if( in_array($iID, self::$aHandledUserIDs) ) {
				continue;
			}

			$sToName = ucfirst( strtolower($sName) );
			if( $sName === $sToName ) {
				continue;
			}
			$a[$iID] = $sToName;
		}
		return $a;
	}
	/**
	 * Gets caseinsensitive double users (name=>(1,2,3))
	 * @param array $a
	 * @return array
	 */
	protected function getMergeUsers( $a = array() ) {
		//Welcome to array nightmare
		$aLower = array_map( 'strtolower', $this->getUsers() );
		$aUniqueLower = array_unique( $aLower );
		$aDoubleEntries = array_diff_assoc( $aLower, $aUniqueLower );
		if( empty($aDoubleEntries) ) {
			return $a;
		}

		$aAllDoubleEntries = array_intersect( $aLower, $aDoubleEntries );
		foreach( $aAllDoubleEntries as $iID => $sValue ) {
			if( !isset($a[$sValue]) ) {
				$a[$sValue] = array();
			}
			$a[$sValue][] = $iID;
		}
		return $a;
	}

	protected function getUsers( ) {
		if( !is_null(self::$aUser) ) {
			return self::$aUser;
		}
		$aFields = array('user_id', 'user_name');
		$aConditions = $this->iStartID > 1
			? array("user_id >= $this->iStartID")
			: array()
		;
		$aOptions = array(
			"ORDER BY" => 'user_id asc'
		);
		self::$aUser = array();
		foreach( wfGetDB( DB_SLAVE )->select('user', $aFields, $aConditions, __METHOD__, $aOptions) as $o ) {
			self::$aUser[$o->user_id] = $o->user_name;
		}

		return self::$aUser;
	}

	/**
	 * Function to merge user pages
	 * See: MergeUser::movePages
	 * move pages is private and can not be overritten :(
	 *
	 * Deletes all pages when merging to Anon
	 * Moves user page when the target user page does not exist or is empty
	 * Deletes redirect if nothing links to old page
	 * Deletes the old user page when the target user page exists
	 *
	 * @todo This code is a duplicate of Renameuser and GlobalRename
	 *
	 * @param User $performer
	 * @param callable $msg Function that returns a Message object
	 * @return array Array of old name (string) => new name (Title) where the move failed
	 */
	protected function movePages( User $oOldUser, User $oNewUser ) {
		global $wgContLang, $wgUser;

		$oldusername = trim( str_replace( '_', ' ', $oOldUser->getName() ) );
		$oldusername = Title::makeTitle( NS_USER, $oldusername );
		$newusername = Title::makeTitleSafe( NS_USER, $wgContLang->ucfirst( $oNewUser->getName() ) );

		# select all user pages and sub-pages
		$dbr = wfGetDB( DB_SLAVE );
		$pages = $dbr->select( 'page',
			array( 'page_namespace', 'page_title' ),
			array(
				'page_namespace' => array( NS_USER, NS_USER_TALK ),
				$dbr->makeList( array(
						'page_title' => $dbr->buildLike( $oldusername->getDBkey() . '/', $dbr->anyString() ),
						'page_title' => $oldusername->getDBkey()
					),
					LIST_OR
				)
			)
		);

		// Need to set $wgUser to attribute log properly.
		$oldUser = $wgUser;
		$wgUser = $this->oPerformer;

		$failedMoves = array();
		foreach ( $pages as $row ) {

			$oldPage = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
			$newPage = Title::makeTitleSafe( $row->page_namespace,
				preg_replace( '!^[^/]+!', $newusername->getDBkey(), $row->page_title ) );

			if ( $oNewUser->getName() === "Anonymous" ) { # delete ALL old pages
				if ( $oldPage->exists() ) {
					$oldPageArticle = new Article( $oldPage, 0 );
					$oldPageArticle->doDeleteArticle(wfMessage( 'usermerge-autopagedelete' )->inContentLanguage()->text() );
				}
			} elseif ( $newPage->exists()
				&& !$oldPage->isValidMoveTarget( $newPage )
				&& $newPage->getLength() > 0 ) { # delete old pages that can't be moved

				$oldPageArticle = new Article( $oldPage, 0 );
				$oldPageArticle->doDeleteArticle( wfMessage( 'usermerge-autopagedelete' )->inContentLanguage()->text() );

			} else { # move content to new page
				# delete target page if it exists and is blank
				if ( $newPage->exists() ) {
					$newPageArticle = new Article( $newPage, 0 );
					$newPageArticle->doDeleteArticle( wfMessage( 'usermerge-autopagedelete' )->inContentLanguage()->text() );
				}

				# move to target location
				$errors = $oldPage->moveTo(
					$newPage,
					false,
					wfMessage(
						'usermerge-move-log',
						$oldusername->getText(),
						$newusername->getText() )->inContentLanguage()->text()
				);
				if ( $errors !== true ) {
					$failedMoves[$oldPage->getPrefixedText()] = $newPage;
				}

				# check if any pages link here
				$res = $dbr->selectField( 'pagelinks',
					'pl_title',
					array( 'pl_title' => $oOldUser->getName() ),
					__METHOD__
				);
				if ( !$dbr->numRows( $res ) ) {
					# nothing links here, so delete unmoved page/redirect
					$oldPageArticle = new Article( $oldPage, 0 );
					$oldPageArticle->doDeleteArticle( wfMessage( 'usermerge-autopagedelete' )->inContentLanguage()->text() );
				}
			}
		}

		$wgUser = $oldUser;

		return $failedMoves;
	}
}

$maintClass = 'RenameAndMergeUserToStrLower';
if ( defined( 'RUN_MAINTENANCE_IF_MAIN' ) ) {
	require_once( RUN_MAINTENANCE_IF_MAIN );
} else {
	require_once( DO_MAINTENANCE ); # Make this work on versions before 1.17
}
