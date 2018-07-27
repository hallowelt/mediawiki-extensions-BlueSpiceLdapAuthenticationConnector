<?php

class BlueSpiceLdapHooks {

	public static function checkLocalDomain() {
		global $bsgLDAPShowLocal, $wgLDAPUseLocal;
		if ((isset( $wgLDAPUseLocal ) && $wgLDAPUseLocal !== true) ||
			(isset( $bsgLDAPShowLocal ) && $bsgLDAPShowLocal !== true)) {
			return true;
			}
		else {
			return false;
		}
	}

	public static function onLDAPModifyUITemplate( &$template ) {
		global $bsgLDAPRenameLocal, $wgLDAPDomainNames, $wgLDAPAutoAuthDomain;
		$localname = 'local';
		if (isset( $bsgLDAPRenameLocal ) && !empty( $bsgLDAPRenameLocal ) ) {
			$localname = $bsgLDAPRenameLocal;
		}
		$domains = $wgLDAPDomainNames;
		array_push( $domains, $localname );
		unset( $domains[array_search( $wgLDAPAutoAuthDomain, $domains )] );
		if( BlueSpiceLdapHooks::checkLocalDomain() === true ) {
			unset( $domains[array_search( $localname, $domains )]);
		}
		$template->set( 'domainnames', $domains );
		return true;
	}

	public static function onPersonalUrls( &$personal_urls ) {
		global $bsgLDAPAutoAuthChangeUser;
		if ( $bsgLDAPAutoAuthChangeUser === true ) {
			$personal_urls["changeuser"] = array(
				"text" => wfMessage( "bs-ldapc-changeuser-label" )->plain(),
				"title" => wfMessage( "tooltip-pt-changeuser" )->plain(),
				"href" => SpecialPage::getTitleFor( 'Userlogin' )->getLinkURL()
			);
		}
		return true;
	}

	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		global $wgLDAPDomainNames;
		$domains = $wgLDAPDomainNames;
		if( BlueSpiceLdapHooks::checkLocalDomain() === false ) {
			array_push( $domains, 'local' );
		}
		if( count ($domains) <= 2 ) {
			$style = "<style type=\"text/css\"><!-- #mw-user-domain-section { display: none; } //--></style>\n";
			$out->addHeadItem("jsonTree script", $style);
		}
		return true;
	}

	public static function onWebDAVValidateUserPass( $oUser, $sUsername, $sPassword, &$bResult ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$bResult = $ldap->authenticate( $sUsername, $sPassword );
		return true;
	}
}
