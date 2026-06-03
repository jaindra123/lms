<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   theme_iiidem2
 * @copyright 2016 Ryan Wyllie
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_iiidem2_admin_settingspage_tabs('themesettingiiidem2', get_string('configtitle', 'theme_iiidem2'));
    $page = new admin_settingpage('theme_iiidem2_general', get_string('generalsettings', 'theme_iiidem2'));

    // Unaddable blocks.
    // Blocks to be excluded when this theme is enabled in the "Add a block" list: Administration, Navigation, Courses and
    // Section links.
    $default = 'navigation,settings,course_list,section_links';
    $setting = new admin_setting_configtext('theme_iiidem2/unaddableblocks',
        get_string('unaddableblocks', 'theme_iiidem2'), get_string('unaddableblocks_desc', 'theme_iiidem2'), $default, PARAM_TEXT);
    $page->add($setting);

    // Preset.
    $name = 'theme_iiidem2/preset';
    $title = get_string('preset', 'theme_iiidem2');
    $description = get_string('preset_desc', 'theme_iiidem2');
    $default = 'default.scss';

    $context = context_system::instance();
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'theme_iiidem2', 'preset', 0, 'itemid, filepath, filename', false);

    $choices = [];
    foreach ($files as $file) {
        $choices[$file->get_filename()] = $file->get_filename();
    }
    // These are the built in presets.
    $choices['default.scss'] = 'default.scss';
    $choices['plain.scss'] = 'plain.scss';

    $setting = new admin_setting_configthemepreset($name, $title, $description, $default, $choices, 'iiidem2');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Preset files setting.
    $name = 'theme_iiidem2/presetfiles';
    $title = get_string('presetfiles','theme_iiidem2');
    $description = get_string('presetfiles_desc', 'theme_iiidem2');

    $setting = new admin_setting_configstoredfile($name, $title, $description, 'preset', 0,
        array('maxfiles' => 20, 'accepted_types' => array('.scss')));
    $page->add($setting);

    // Background image setting.
    $name = 'theme_iiidem2/backgroundimage';
    $title = get_string('backgroundimage', 'theme_iiidem2');
    $description = get_string('backgroundimage_desc', 'theme_iiidem2');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'backgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Login Background image setting.
    $name = 'theme_iiidem2/loginbackgroundimage';
    $title = get_string('loginbackgroundimage', 'theme_iiidem2');
    $description = get_string('loginbackgroundimage_desc', 'theme_iiidem2');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'loginbackgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // We use an empty default value because the default colour should come from the preset.
    $name = 'theme_iiidem2/brandcolor';
    $title = get_string('brandcolor', 'theme_iiidem2');
    $description = get_string('brandcolor_desc', 'theme_iiidem2');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Header logo (must be on this page before $settings->add($page)).
    $name = 'theme_iiidem2/headerlogo';
    $title = get_string('headerlogo', 'theme_iiidem2');
    $description = get_string('headerlogo_desc', 'theme_iiidem2');
    $setting = new admin_setting_configstoredfile(
        $name,
        $title,
        $description,
        'headerlogo'
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Must add the page after definiting all the settings!
    $settings->add($page);

    // Advanced settings.
    $page = new admin_settingpage('theme_iiidem2_advanced', get_string('advancedsettings', 'theme_iiidem2'));

    // Raw SCSS to include before the content.
    $setting = new admin_setting_scsscode('theme_iiidem2/scsspre',
        get_string('rawscsspre', 'theme_iiidem2'), get_string('rawscsspre_desc', 'theme_iiidem2'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Raw SCSS to include after the content.
    $setting = new admin_setting_scsscode('theme_iiidem2/scss', get_string('rawscss', 'theme_iiidem2'),
        get_string('rawscss_desc', 'theme_iiidem2'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // Create settings page ---------------------------------------------------
    $page = new admin_settingpage(
        'theme_iiidem2_slider',
        'Homepage Slider'
    );

    if ($ADMIN->fulltree) {

        for ($i = 1; $i <= 2; $i++) {

            // Slide Heading
            $page->add(new admin_setting_heading(
                'theme_iiidem2/slideheading' . $i,
                'Slide ' . $i,
                'Manage slide ' . $i
            ));

            // Slide Title
            $page->add(new admin_setting_configtext(
                'theme_iiidem2/slidetitle' . $i,
                'Slide Title',
                '',
                'Welcome to IIIDEM'
            ));

            // Slide Description
            $page->add(new admin_setting_confightmleditor(
                'theme_iiidem2/slidedesc' . $i,
                'Slide Description',
                '',
                '<p>Professional LMS Platform</p>'
            ));

            // Button Text
            $page->add(new admin_setting_configtext(
                'theme_iiidem2/slidebtntext' . $i,
                'Button Text',
                '',
                'Explore Courses'
            ));

            // Button URL
            $page->add(new admin_setting_configtext(
                'theme_iiidem2/slidebtnurl' . $i,
                'Button URL',
                '',
                '/course'
            ));

            // Slide Image
            $setting = new admin_setting_configstoredfile(
                'theme_iiidem2/slideimage' . $i,
                'Slide Image',
                '',
                'slideimage' . $i
            );

            $setting->set_updatedcallback('theme_reset_all_caches');

            $page->add($setting);
        }

    }

    // Add page into theme tabs
    $settings->add($page);


/*
|--------------------------------------------------------------------------
| Footer Settings Tab
|--------------------------------------------------------------------------
*/
$page = new admin_settingpage(
    'theme_iiidem2_footer',
    'Copyright Settings'
);

// Footer Heading.
$name = 'theme_iiidem2/footerheading';
$title = 'Copyright Settings';

$setting = new admin_setting_heading(
    $name,
    $title,
    '',
    ''
);

$page->add($setting);

// Footer logo.
$name = 'theme_iiidem2/footerlogo';
$title = get_string('footerlogo', 'theme_iiidem2');
$description = get_string('footerlogo_desc', 'theme_iiidem2');
$setting = new admin_setting_configstoredfile(
    $name,
    $title,
    $description,
    'footerlogo'
);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Copyright Text.
$name = 'theme_iiidem2/copyrighttext';
$title = 'Copyright Text';
$description = 'Footer copyright text';
$default = '© 2026 IIIDEM. All Rights Reserved.';

$setting = new admin_setting_configtext(
    $name,
    $title,
    $description,
    $default
);

$page->add($setting);

// Save Footer Settings Page.
$settings->add($page);


/*
|--------------------------------------------------------------------------
| Social Media Tab
|--------------------------------------------------------------------------
*/
$page = new admin_settingpage(
    'theme_iiidem2_social',
    'Social Media'
);

// Facebook URL.
$name = 'theme_iiidem2/facebookurl';
$title = 'Facebook URL';

$setting = new admin_setting_configtext(
    $name,
    $title,
    '',
    ''
);

$page->add($setting);

// Twitter/X URL.
$name = 'theme_iiidem2/twitterurl';
$title = 'Twitter/X URL';

$setting = new admin_setting_configtext(
    $name,
    $title,
    '',
    ''
);

$page->add($setting);

// Instagram URL.
$name = 'theme_iiidem2/instagramurl';
$title = 'Instagram URL';

$setting = new admin_setting_configtext(
    $name,
    $title,
    '',
    ''
);

$page->add($setting);

// Youtube URL.
$name = 'theme_iiidem2/youtubeurl';
$title = 'Youtube URL';

$setting = new admin_setting_configtext(
    $name,
    $title,
    '',
    ''
);

$page->add($setting);

// Save Social Media Page.
$settings->add($page);


/*
|--------------------------------------------------------------------------
| Quick Links Tab
|--------------------------------------------------------------------------
*/
$page = new admin_settingpage(
    'theme_iiidem2_quicklinks',
    'Quick Links'
);

// Quick Link 1 Text.
$name = 'theme_iiidem2/quicklink1text';
$title = 'Quick Link 1 Text';

$setting = new admin_setting_configtext(
    $name,
    $title,
    '',
    'About Us'
);

$page->add($setting);

// Quick Link 1 URL.
$name = 'theme_iiidem2/quicklink1url';
$title = 'Quick Link 1 URL';

$setting = new admin_setting_configtext(
    $name,
    $title,
    '',
    '#'
);

$page->add($setting);

// Save Quick Links Page.
$settings->add($page);


/*
|--------------------------------------------------------------------------
| Footer Contacts Tab
|--------------------------------------------------------------------------
*/
$page = new admin_settingpage(
    'theme_iiidem2_contact',
    'IIIDEM Contacts'
);

/*
|--------------------------------------------------------------------------
| IIIDEM Description
|--------------------------------------------------------------------------
*/
$name = 'theme_iiidem2/description';
$title = 'IIIDEM Description';
$description = 'Footer description text';
$default = '';

$setting = new admin_setting_configtextarea(
    $name,
    $title,
    $description,
    $default,
    PARAM_RAW,
    5,
    50
);

$page->add($setting);


/*
|--------------------------------------------------------------------------
| IIIDEM Address
|--------------------------------------------------------------------------
*/
$name = 'theme_iiidem2/address';
$title = 'IIIDEM Address';
$description = 'Enter institute address';
$default = '';

$setting = new admin_setting_configtext(
    $name,
    $title,
    $description,
    $default
);

$page->add($setting);


/*
|--------------------------------------------------------------------------
| IIIDEM Email
|--------------------------------------------------------------------------
*/
$name = 'theme_iiidem2/email';
$title = 'IIIDEM Email';
$description = 'Enter institute email';
$default = '';

$setting = new admin_setting_configtext(
    $name,
    $title,
    $description,
    $default
);

$page->add($setting);


/*
|--------------------------------------------------------------------------
| IIIDEM Phone
|--------------------------------------------------------------------------
*/
$name = 'theme_iiidem2/phone';
$title = 'IIIDEM Phone';
$description = 'Institute phone number (footer and Contact Us page)';
$default = '+91-11-25303512';

$setting = new admin_setting_configtext(
    $name,
    $title,
    $description,
    $default
);

$page->add($setting);

// Save Contacts Page.
$settings->add($page);
}