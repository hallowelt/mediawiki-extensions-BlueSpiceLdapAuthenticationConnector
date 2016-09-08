<?php

$wgHooks['LDAPModifyUITemplate'][] = 'BlueSpiceLdapHooks::onLDAPModifyUITemplate';
$wgHooks['PersonalUrls'][] = 'BlueSpiceLdapHooks::onPersonalUrls';
$wgHooks['BeforePageDisplay'][] = 'BlueSpiceLdapHooks::onBeforePageDisplay';
