<?php

require_once( __DIR__."/includes/AutoLoader.php" );
require_once( __DIR__."/LdapAuthenticationConnector.hooks.php" );

$wgMessagesDirs['LdapAuthenticationConnector'] = __DIR__ . '/i18n';

$bsgLDAPAutoAuthChangeUser = false;

function BSAutoAuthSetup( $domain ) {
	global $wgLDAPAutoAuthUsername, $wgLDAPAutoAuthDomain;

	if( PHP_SAPI === 'cli' ) {
		return;
	}

	$aConfigVars = array(
						'wgLDAPActiveDirectory', 'wgLDAPAddLDAPUsers', 'wgLDAPAuthAttribute', 'wgLDAPBaseDNs',
						'wgLDAPDisableAutoCreate', 'wgLDAPDomainNames', 'wgLDAPEncryptionType', 'wgLDAPExcludedGroups',
						'wgLDAPGroupAttribute', 'wgLDAPGroupBaseDNs', 'wgLDAPGroupNameAttribute', 'wgLDAPGroupObjectclass',
						'wgLDAPGroupSearchNestedGroups', 'wgLDAPGroupsPrevail', 'wgLDAPGroupsUseMemberOf', 'wgLDAPGroupUseFullDN',
						'wgLDAPGroupUseRetrievedUsername', 'wgLDAPLocallyManagedGroups', 'wgLDAPLowerCaseUsername',
						'wgLDAPMailPassword', 'wgLDAPOptions', 'wgLDAPPasswordHash', 'wgLDAPPort', 'wgLDAPPreferences',
						'wgLDAPProxyAgent', 'wgLDAPProxyAgentPassword', 'wgLDAPRequiredGroups', 'wgLDAPRetrievePrefs',
						'wgLDAPSearchAttributes', 'wgLDAPSearchStrings', 'wgLDAPServerNames', 'wgLDAPUpdateLDAP',
						'wgLDAPUseLDAPGroups', 'wgLDAPUserBaseDNs', 'wgLDAPWriteLocation', 'wgLDAPWriterDN', 'wgLDAPWriterPassword'
					);

	$sAutoAuthDomain = $domain . '-AutoAuth';

	foreach( $aConfigVars as $var ) {
		if( !isset( $GLOBALS[$var] ) || !is_array( $GLOBALS[$var] ) ) {
			continue;
		}
		if( isset( $GLOBALS[$var][$domain] ) && !empty ($GLOBALS[$var][$domain]) )  {
			$GLOBALS[$var][$sAutoAuthDomain] = $GLOBALS[$var][$domain];
		}
		elseif( in_array( $domain, $GLOBALS[$var]) ) {
			$GLOBALS[$var][] = $sAutoAuthDomain;
		}
	}

	if ( strpos( $_SERVER['REMOTE_USER'], '@' ) !== false ) {
		$username = substr( $_SERVER['REMOTE_USER'], 0, strpos( $_SERVER['REMOTE_USER'], '@' ) );
	}
	else {
		$username = $username = preg_replace( '|^.*?\\\|', '', $_SERVER['REMOTE_USER'] );
	}
	$wgLDAPAutoAuthUsername = strtolower( $username );
	$wgLDAPAutoAuthDomain = $sAutoAuthDomain;

	AutoAuthSetup();
}

