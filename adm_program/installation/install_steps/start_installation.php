<?php
/**
 ***********************************************************************************************
 * Installation step: start_installation
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'start_installation.php')
{
    exit('This page may not be called directly!');
}

// Check if configuration file exists. This file must be copied to the base folder of the Admidio installation.
if (!is_file($configPath))
{
    $page = new HtmlPageInstallation('admidio-installation-message');
    $page->showMessage('error', $gL10n->get('SYS_NOTE'), $gL10n->get('INS_CONFIGURATION_FILE_NOT_FOUND', array('config.php')), $gL10n->get('SYS_BACK'),
        'fa-arrow-circle-left', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_config')));
    // => EXIT
}

// first check if session is filled (if installation was aborted then this is not filled)
// if previous dialogs were filled then check if the settings are equal to config file
if (isset($_SESSION['table_prefix'])
&&    ($_SESSION['db_engine']      !== DB_ENGINE
    || $_SESSION['db_host']        !== DB_HOST
    || $_SESSION['db_port']        !== DB_PORT
    || $_SESSION['db_name']        !== DB_NAME
    || $_SESSION['db_username']    !== DB_USERNAME
    || $_SESSION['db_password']    !== DB_PASSWORD
    || $_SESSION['table_prefix']   !== TABLE_PREFIX
    || $_SESSION['orga_shortname'] !== $g_organization))
{
    $page = new HtmlPageInstallation('admidio-installation-message');
    $page->showMessage('error', $gL10n->get('SYS_NOTE'), $gL10n->get('INS_DATA_DO_NOT_MATCH', array('config.php')), $gL10n->get('SYS_BACK'),
        'fa-arrow-circle-left', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'connect_database')));
    // => EXIT
}

// set execution time to 5 minutes because we have a lot to do
PhpIniUtils::startNewExecutionTimeLimit(300);

// read data from sql script db.sql and execute all statements to the current database
$sqlQueryResult = querySqlFile($db, 'db.sql');

if (is_string($sqlQueryResult))
{
    $page = new HtmlPageInstallation('admidio-installation-message');
    $page->showMessage('error', $gL10n->get('SYS_NOTE'), $sqlQueryResult, $gL10n->get('SYS_BACK'),
        'fa-arrow-circle-left', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_config')));
    // => EXIT
}

// create default data

// add system component to database
$component = new ComponentUpdate($db);
$component->setValue('com_type', 'SYSTEM');
$component->setValue('com_name', 'Admidio Core');
$component->setValue('com_name_intern', 'CORE');
$component->setValue('com_version', ADMIDIO_VERSION);
$component->setValue('com_beta', ADMIDIO_VERSION_BETA);
$component->setValue('com_update_step', $component->getMaxUpdateStep());
$component->save();

// create a hidden system user for internal use
// all recordsets created by installation will get the create id of the system user
$gCurrentUser = new TableAccess($db, TBL_USERS, 'usr');
$gCurrentUser->setValue('usr_login_name', $gL10n->get('SYS_SYSTEM'));
$gCurrentUser->setValue('usr_valid', '0');
$gCurrentUser->setValue('usr_timestamp_create', DATETIME_NOW);
$gCurrentUser->save(false); // no registered user -> UserIdCreate couldn't be filled
$currUsrId = (int) $gCurrentUser->getValue('usr_id');

// create all modules components
$sql = 'INSERT INTO '.TBL_COMPONENTS.'
               (com_type, com_name, com_name_intern, com_version, com_beta)
        VALUES (\'MODULE\', \'SYS_ANNOUNCEMENTS\',       \'ANNOUNCEMENTS\',  \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_DATABASE_BACKUP\',     \'BACKUP\',         \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_CATEGORIES\',          \'CATEGORIES\',     \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_CATEGORY_REPORT\',     \'CATEGORY-REPORT\',\''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'DAT_DATES\',               \'DATES\',          \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_DOCUMENTS_FILES\',     \'DOCUMENTS-FILES\',\''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'GBO_GUESTBOOK\',           \'GUESTBOOK\',      \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_WEBLINKS\',            \'LINKS\',          \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_GROUPS_ROLES\',        \'GROUPS-ROLES\',   \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_USER_MANAGEMENT\',     \'MEMBERS\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_MESSAGES\',            \'MESSAGES\',       \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_MENU\',                \'MENU\',           \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_PHOTOS\',              \'PHOTOS\',         \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_SETTINGS\',            \'PREFERENCES\',    \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'PRO_PROFILE\',             \'PROFILE\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_REGISTRATION\',        \'REGISTRATION\',   \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_ROOM_MANAGEMENT\',     \'ROOMS\',          \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')';
$db->query($sql); // TODO add more params

// create organization independent categories
$sql = 'INSERT INTO '.TBL_CATEGORIES.'
               (cat_org_id, cat_type, cat_name_intern, cat_name, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
        VALUES (NULL, \'USF\', \'BASIC_DATA\', \'SYS_BASIC_DATA\', 1, 1, ?, ?) -- $currUsrId, DATETIME_NOW';
$db->queryPrepared($sql, array($currUsrId, DATETIME_NOW));
$categoryIdMasterData = $db->lastInsertId();

$sql = 'INSERT INTO '.TBL_CATEGORIES.'
               (cat_org_id, cat_type, cat_name_intern, cat_name, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
        VALUES (NULL, \'USF\', \'SOCIAL_NETWORKS\', \'SYS_SOCIAL_NETWORKS\', 0, 2, ?, ?) -- $currUsrId, DATETIME_NOW';
$db->queryPrepared($sql, array($currUsrId, DATETIME_NOW));
$categoryIdSocialNetworks = $db->lastInsertId();

$sql = 'INSERT INTO '.TBL_CATEGORIES.'
               (cat_org_id, cat_type, cat_name_intern, cat_name, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
        VALUES (NULL, \'USF\', \'ADDIDIONAL_DATA\', \'INS_ADDIDIONAL_DATA\', 0, 0, 3, ?, ?) -- $currUsrId, DATETIME_NOW';
$db->queryPrepared($sql, array($currUsrId, DATETIME_NOW));

// create roles rights
$sql = 'INSERT INTO '.TBL_ROLES_RIGHTS.'
               (ror_name_intern, ror_table)
        VALUES (\'folder_view\',   \'adm_folders\')
             , (\'folder_upload\', \'adm_folders\')
             , (\'category_view\', \'adm_categories\')
             , (\'event_participation\', \'adm_dates\')
             , (\'menu_view\',     \'adm_menu\')';
$db->queryPrepared($sql);

// add edit categories right with reference to parent right
$sql = 'INSERT INTO '.TBL_ROLES_RIGHTS.'
               (ror_name_intern, ror_table, ror_ror_id_parent)
        VALUES (\'category_edit\', \'adm_categories\', (SELECT rr.ror_id FROM '.TBL_ROLES_RIGHTS.' rr WHERE rr.ror_name_intern = \'category_view\'))';
$db->queryPrepared($sql);

// create profile fields of category basic data
$sql = 'INSERT INTO '.TBL_USER_FIELDS.'
               (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_value_list, usf_system, usf_disabled, usf_mandatory, usf_registration, usf_sequence, usf_usr_id_create, usf_timestamp_create)
        VALUES ('.$categoryIdMasterData.', \'TEXT\',         \'LAST_NAME\',  \'SYS_LASTNAME\',  NULL, NULL, 1, 1, 1, 1, 1,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'FIRST_NAME\', \'SYS_FIRSTNAME\', NULL, NULL, 1, 1, 1, 1, 2,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'STREET\',     \'SYS_STREET\',    NULL, NULL, 0, 0, 0, 0, 3,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'POSTCODE\',   \'SYS_POSTCODE\',  NULL, NULL, 0, 0, 0, 0, 4,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'CITY\',       \'SYS_CITY\',      NULL, NULL, 0, 0, 0, 0, 5,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'COUNTRY\',    \'SYS_COUNTRY\',   NULL, NULL, 0, 0, 0, 0, 6,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'PHONE\',        \'PHONE\',      \'SYS_PHONE\',     NULL, NULL, 0, 0, 0, 0, 7,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'PHONE\',        \'MOBILE\',     \'SYS_MOBILE\',    NULL, NULL, 0, 0, 0, 0, 8,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'PHONE\',        \'FAX\',        \'SYS_FAX\',       NULL, NULL, 0, 0, 0, 0, 9,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'DATE\',         \'BIRTHDAY\',   \'SYS_BIRTHDAY\',  NULL, NULL, 0, 0, 0, 0, 10, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'RADIO_BUTTON\', \'GENDER\',     \'SYS_GENDER\',    NULL, \'fa-mars|SYS_MALE
fa-venus|SYS_FEMALE\', 0, 0, 0, 0, 11, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'EMAIL\',        \'EMAIL\',      \'SYS_EMAIL\',     NULL, NULL, 1, 0, 1, 1, 12, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'URL\',          \'WEBSITE\',    \'SYS_WEBSITE\',   NULL, NULL, 0, 0, 0, 0, 13, '.$currUsrId.', \''. DATETIME_NOW.'\')';
$db->query($sql); // TODO add more params

// create profile fields of category social networks
$sql = 'INSERT INTO '.TBL_USER_FIELDS.'
               (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_icon, usf_url, usf_system, usf_sequence, usf_usr_id_create, usf_timestamp_create)
        VALUES ('.$categoryIdSocialNetworks.', \'TEXT\', \'FACEBOOK\',              \'INS_FACEBOOK\',    \''.$gL10n->get('INS_FACEBOOK_DESC').'\',    \'fab fa-facebook\',    \'https://www.facebook.com/#user_content#\',      0, 1, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'ICQ\',                   \'INS_ICQ\',         \''.$gL10n->get('INS_ICQ_DESC').'\',         \'icq.png\',            \'https://www.icq.com/people/#user_content#\',    0, 2, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'SKYPE\',                 \'INS_SKYPE\',       \''.$gL10n->get('INS_SKYPE_DESC').'\',       \'fab fa-skype\',       NULL,                                             0, 3, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'TWITTER\',               \'INS_TWITTER\',     \''.$gL10n->get('INS_TWITTER_DESC').'\',     \'fab fa-twitter\',     \'https://twitter.com/#user_content#\',           0, 4, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'XING\',                  \'INS_XING\',        \''.$gL10n->get('INS_XING_DESC').'\',        \'fab fa-xing\',        \'https://www.xing.com/profile/#user_content#\',  0, 5, '.$currUsrId.', \''. DATETIME_NOW.'\')';
$db->query($sql); // TODO add more params

// create user relation types
$sql = 'INSERT INTO '.TBL_USER_RELATION_TYPES.'
               (urt_id, urt_name, urt_name_male, urt_name_female, urt_id_inverse, urt_usr_id_create, urt_timestamp_create)
        VALUES (1, \''.$gL10n->get('INS_PARENT').'\',      \''.$gL10n->get('INS_FATHER').'\',           \''.$gL10n->get('INS_MOTHER').'\',          null, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (2, \''.$gL10n->get('INS_CHILD').'\',       \''.$gL10n->get('INS_SON').'\',              \''.$gL10n->get('INS_DAUGHTER').'\',           1, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (3, \''.$gL10n->get('INS_SIBLING').'\',     \''.$gL10n->get('INS_BROTHER').'\',          \''.$gL10n->get('INS_SISTER').'\',             3, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (4, \''.$gL10n->get('INS_SPOUSE').'\',      \''.$gL10n->get('INS_HUSBAND').'\',          \''.$gL10n->get('INS_WIFE').'\',               4, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (5, \''.$gL10n->get('INS_COHABITANT').'\',  \''.$gL10n->get('INS_COHABITANT_MALE').'\',  \''.$gL10n->get('INS_COHABITANT_FEMALE').'\',  5, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (6, \''.$gL10n->get('INS_COMPANION').'\',   \''.$gL10n->get('INS_BOYFRIEND').'\',        \''.$gL10n->get('INS_GIRLFRIEND').'\',         6, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (7, \''.$gL10n->get('INS_SUPERIOR').'\',    \''.$gL10n->get('INS_SUPERIOR_MALE').'\',    \''.$gL10n->get('INS_SUPERIOR_FEMALE').'\', null, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (8, \''.$gL10n->get('INS_SUBORDINATE').'\', \''.$gL10n->get('INS_SUBORDINATE_MALE').'\', \''.$gL10n->get('INS_SUBORDINATE_FEMALE').'\', 7, '.$currUsrId.', \''. DATETIME_NOW.'\')';
$db->query($sql); // TODO add more params

$sql = 'UPDATE '.TBL_USER_RELATION_TYPES.'
           SET urt_id_inverse = 2
         WHERE urt_id = 1';
$db->queryPrepared($sql);

$sql = 'UPDATE '.TBL_USER_RELATION_TYPES.'
           SET urt_id_inverse = 8
         WHERE urt_id = 7';
$db->queryPrepared($sql);

disableSoundexSearchIfPgSql($db);

// create new organization
$gCurrentOrganization = new Organization($db, $_SESSION['orga_shortname']);
$gCurrentOrganization->setValue('org_longname', $_SESSION['orga_longname']);
$gCurrentOrganization->setValue('org_shortname', $_SESSION['orga_shortname']);
$gCurrentOrganization->setValue('org_homepage', ADMIDIO_URL);
$gCurrentOrganization->save();

// create administrator and assign roles
$administrator = new User($db);
$administrator->setValue('usr_login_name', $_SESSION['user_login']);
$administrator->setPassword($_SESSION['user_password']);
$administrator->setValue('usr_usr_id_create', $currUsrId);
$administrator->setValue('usr_timestamp_create', DATETIME_NOW);
$administrator->save(false); // no registered user -> UserIdCreate couldn't be filled
$adminUsrId = (int) $administrator->getValue('usr_id');

// write all preferences from preferences.php in table adm_preferences
require_once(ADMIDIO_PATH . '/adm_program/installation/db_scripts/preferences.php');

// set some specific preferences whose values came from user input of the installation wizard
$defaultOrgPreferences['email_administrator'] = $_SESSION['orga_email'];
$defaultOrgPreferences['system_language']     = $language;

// calculate the best cost value for your server performance
$benchmarkResults = PasswordUtils::costBenchmark($gPasswordHashAlgorithm);
if (is_int($benchmarkResults['options']['cost'])) {
    $defaultOrgPreferences['system_hashing_cost'] = $benchmarkResults['options']['cost'];
}

// create all necessary data for this organization
$gSettingsManager =& $gCurrentOrganization->getSettingsManager();
$gSettingsManager->setMulti($defaultOrgPreferences, false);
$gCurrentOrganization->createBasicData($adminUsrId);

// create default room for room module in database
$sql = 'INSERT INTO '.TBL_ROOMS.'
               (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
        VALUES (?, ?, 15, ?, ?) -- $gL10n->get(\'INS_CONFERENCE_ROOM\'), $gL10n->get(\'INS_DESCRIPTION_CONFERENCE_ROOM\'), $currUsrId, DATETIME_NOW';
$params = array(
    $gL10n->get('INS_CONFERENCE_ROOM'),
    $gL10n->get('INS_DESCRIPTION_CONFERENCE_ROOM'),
    $currUsrId,
    DATETIME_NOW
);
$db->queryPrepared($sql, $params);

// first create a user object "current user" with administrator rights
// because administrator is allowed to edit firstname and lastname
$gCurrentUser = new User($db, $gProfileFields, $adminUsrId);
$gCurrentUser->setValue('LAST_NAME',  $_SESSION['user_last_name']);
$gCurrentUser->setValue('FIRST_NAME', $_SESSION['user_first_name']);
$gCurrentUser->setValue('EMAIL',      $_SESSION['user_email']);
$gCurrentUser->save(false);

// now create a full user object for system user
$systemUser = new User($db, $gProfileFields, $currUsrId);
$systemUser->setValue('LAST_NAME', $gL10n->get('SYS_SYSTEM'));
$systemUser->save(false); // no registered user -> UserIdCreate couldn't be filled

// now set current user to system user
$gCurrentUser->readDataById($currUsrId);

// Menu entries for the standard installation
$sql = 'INSERT INTO '.TBL_MENU.'
               (men_com_id, men_men_id_parent, men_node, men_order, men_standard, men_name_intern, men_url, men_icon, men_name, men_description)
        VALUES (NULL, NULL, 1, 1, 1, \'modules\', NULL, \'\', \'SYS_MODULES\', \'\')
             , (NULL, NULL, 1, 2, 1, \'administration\', NULL, \'\', \'SYS_ADMINISTRATION\', \'\')
             , (NULL, NULL, 1, 3, 1, \'plugins\', NULL, \'\', \'SYS_PLUGINS\', \'\')
             , (NULL, 1, 0, 1, 1, \'overview\', \'/adm_program/overview.php\', \'fa-home\', \'SYS_OVERVIEW\', \'\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'DOCUMENTS-FILES\'), 1, 0, 3, 1, \'documents-files\', \''.FOLDER_MODULES.'/documents-files/documents_files.php\', \'fa-file-download\', \'SYS_DOCUMENTS_FILES\', \'SYS_DOCUMENTS_FILES_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'GROUPS-ROLES\'), 1, 0, 7, 1, \'groups-roles\', \''.FOLDER_MODULES.'/groups-roles/groups_roles.php\', \'fa-users\', \'SYS_GROUPS_ROLES\', \'SYS_GROUPS_ROLES_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'ANNOUNCEMENTS\'), 1, 0, 2, 1, \'announcements\', \''.FOLDER_MODULES.'/announcements/announcements.php\', \'fa-newspaper\', \'SYS_ANNOUNCEMENTS\', \'SYS_ANNOUNCEMENTS_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'PHOTOS\'), 1, 0, 5, 1, \'photo\', \''.FOLDER_MODULES.'/photos/photos.php\', \'fa-image\', \'SYS_PHOTOS\', \'PHO_PHOTOS_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'GUESTBOOK\'), 1, 0, 6, 1, \'guestbook\', \''.FOLDER_MODULES.'/guestbook/guestbook.php\', \'fa-book\', \'GBO_GUESTBOOK\', \'GBO_GUESTBOOK_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'DATES\'), 1, 0, 8, 1, \'dates\', \''.FOLDER_MODULES.'/dates/dates.php\', \'fa-calendar-alt\', \'DAT_DATES\', \'DAT_DATES_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'CATEGORY-REPORT\'), 1, 0, 9, 1, \'category-report\', \''.FOLDER_MODULES.'/category-report/category_report.php\', \'fa-list-ul\', \'SYS_CATEGORY_REPORT\', \'SYS_CATEGORY_REPORT_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'LINKS\'), 1, 0, 9, 1, \'weblinks\', \''.FOLDER_MODULES.'/links/links.php\', \'fa-link\', \'SYS_WEBLINKS\', \'SYS_WEBLINKS_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'BACKUP\'), 2, 0, 4, 1, \'dbback\', \''.FOLDER_MODULES.'/backup/backup.php\', \'fa-database\', \'SYS_DATABASE_BACKUP\', \'SYS_DATABASE_BACKUP_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'PREFERENCES\'), 2, 0, 6, 1, \'orgprop\', \''.FOLDER_MODULES.'/preferences/preferences.php\', \'fa-cog\', \'SYS_SETTINGS\', \'ORG_ORGANIZATION_PROPERTIES_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'MESSAGES\'), 1, 0, 4, 1, \'mail\', \''.FOLDER_MODULES.'/messages/messages_write.php\', \'fa-envelope\', \'SYS_EMAIL\', \'SYS_EMAIL_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'REGISTRATION\'), 2, 0, 1, 1, \'newreg\', \''.FOLDER_MODULES.'/registration/registration.php\', \'fa-address-card\', \'SYS_NEW_REGISTRATIONS\', \'SYS_MANAGE_NEW_REGISTRATIONS_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'MEMBERS\'), 2, 0, 2, 1, \'usrmgt\', \''.FOLDER_MODULES.'/members/members.php\', \'fa-users-cog\', \'SYS_USER_MANAGEMENT\', \'SYS_USER_MANAGEMENT_DESC\')
             , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'MENU\'), 2, 0, 5, 1, \'menu\', \''.FOLDER_MODULES.'/menu/menu.php\', \'fa-stream\', \'SYS_MENU\', \'SYS_MENU_DESC\')';
$db->query($sql);

// delete session data
session_unset();
session_destroy();

$gLogger->info('INSTALLATION: Installation successfully complete');

// show dialog with success notification
$page = new HtmlPageInstallation('admidio-installation-successful');
$page->addTemplateFile('installation_successful.tpl');
$page->addJavascript('$("#next_page").focus();', true);

$form = new HtmlForm('installation-form', ADMIDIO_HOMEPAGE.'donate.php', null, array('setFocus' => false));
$form->addButton(
    'main_page', $gL10n->get('SYS_LATER'),
    array('icon' => 'fa-home', 'class' => 'admidio-margin-bottom',
        'link' => ADMIDIO_URL . '/adm_program/overview.php')
);
$form->addSubmitButton('next_page', $gL10n->get('SYS_DONATE'), array('icon' => 'fa-heart'));

$page->addHtml($form->show());
$page->show();
