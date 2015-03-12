<?php

/**
 * This file adds the settings pages to the navigation menu
 *
 * @package   mod_wwassignment
 * @copyright
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

//require_once($CFG->dirroot.'/mod/wwassignment/lib.php');
// Messages d'erreur
//global $CFG;

$ADMIN->add('modsettings', new admin_category('modwwassignmentfolder', new lang_string('pluginname', 'mod_wwassignment'), $module->is_enabled() === false));

$settings = new admin_settingpage($section, get_string('pluginadministration', 'mod_wwassignment'), 'moodle/site:config', $module->is_enabled() === false);

if ($ADMIN->fulltree) {


    $settings->add(new admin_setting_configtext('wwassignment_webworkurl',
        get_string('webwork_url', 'wwassignment'),
        get_string('webwork_url_desc', 'wwassignment'),
        'https://test.edu/webwork2',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext('wwassignment_rpc_wsdl',
        get_string('rpc_wsdl', 'wwassignment'),
        get_string('rpc_wsdl_desc', 'wwassignment'),
        'https://test.edu/webwork2_wsdl',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext('wwassignment_rpc_key',
        get_string('rpc_key', 'wwassignment'),
        get_string('rpc_key_desc', 'wwassignment'),
        '123456789123456789',
        PARAM_ALPHANUMEXT

    ));

    $settings->add(new admin_setting_configtext('wwassignment_iframewidth',
        get_string('iframe_width', 'wwassignment'),
        get_string('iframe_width_desc', 'wwassignment'),
        '95%',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext('wwassignment_iframeheight',
        get_string('iframe_height', 'wwassignment'),
        get_string('iframe_height_desc', 'wwassignment'),
        '500px',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configcheckbox('wwassignment/requiremodintro',
        get_string('requiremodintro', 'admin'), get_string('configrequiremodintro', 'admin'), 0));

}