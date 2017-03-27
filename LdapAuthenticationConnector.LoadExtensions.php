<?php

$bsgSetupLDAPUsernameBlacklist = array();
$bsgSetupLDAPActionBlacklist = array();

function wfMaybeSetupLDAP( $setupFile ) {
        global $IP;
        global $bsgSetupLDAPUsernameBlacklist, $bsgSetupLDAPActionBlacklist;

        wfSuppressWarnings();

        //not on command line
        if( PHP_SAPI === 'cli' ) {
                return false;
        }

        //not when certain local account tries to log in
        if( isset( $_POST['wpName'] ) && in_array( $_POST['wpName'], $bsgSetupLDAPUsernameBlacklist ) ) {
                return false;
        }

        //Due to heavy issues with LDAP and the 'Installer' implementation also skip
        //on certain API requests made in the 'ROOT_WIKI'
        //Best example: Skip LDAP integration for farming actions like
        //array( 'sfrcreatewiki', 'sfrclonewiki' );
        if( isset( $_GET['action'] ) && in_array( $_GET['action'], $bsgSetupLDAPActionBlacklist ) ) {
                return false;
        }

        wfRestoreWarnings();

        require_once( "$IP/$setupFile" );

        return true;
}
