<?php

$bsgSetupLDAPUsernameBlacklist = array();
$bsgSetupLDAPActionBlacklist = array();
$bsgSetupLDAPSetupFile = array();

function wfMaybeSetupLDAP() {
        global $IP;
        global $bsgSetupLDAPUsernameBlacklist, $bsgSetupLDAPActionBlacklist, $bsgSetupLDAPSetupFile;

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
        if( isset( $_GET['action'] ) && in_array( $_GET['action'], $bsgSetupLDAPActionBlacklist ) ) {
                return false;
        }

        wfRestoreWarnings();

        foreach( $bsgSetupLDAPSetupFile as $setupFile ) {
                require_once( "$IP/$setupFile" );
        }

        return true;
}
