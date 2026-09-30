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
 * Privacy Subsystem implementation for theme_iiidem2.
 *
 * @package    theme_iiidem2
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_iiidem2\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * The iiidem2 theme stores drawer preferences and shared reading uploads.
 *
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\user_preference_provider {

    /** The user preferences for the course index. */
    const DRAWER_OPEN_INDEX = 'drawer-open-index';

    /** The user preferences for the blocks drawer. */
    const DRAWER_OPEN_BLOCK = 'drawer-open-block';

    /**
     * Returns meta data about this system.
     *
     * @param  collection $items The initialised item collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_user_preference(self::DRAWER_OPEN_INDEX, 'privacy:metadata:preference:draweropenindex');
        $items->add_user_preference(self::DRAWER_OPEN_BLOCK, 'privacy:metadata:preference:draweropenblock');
        $items->add_database_table('theme_iiidem2_shared_reading', [
            'userid' => 'privacy:metadata:sharedreading:userid',
            'courseid' => 'privacy:metadata:sharedreading:courseid',
            'title' => 'privacy:metadata:sharedreading:title',
            'timecreated' => 'privacy:metadata:sharedreading:timecreated',
        ], 'privacy:metadata:sharedreading');
        $items->add_subsystem_link('core_files', [], 'privacy:metadata:sharedreadingfiles');
        return $items;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (!\theme_iiidem2\shared_readings::table_ready()) {
            return $contextlist;
        }
        $sql = "SELECT ctx.id
                  FROM {theme_iiidem2_shared_reading} r
                  JOIN {context} ctx ON ctx.instanceid = r.courseid AND ctx.contextlevel = :contextlevel
                 WHERE r.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'userid' => $userid,
            'contextlevel' => CONTEXT_COURSE,
        ]);
        return $contextlist;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !\theme_iiidem2\shared_readings::table_ready()) {
            return;
        }
        $sql = "SELECT userid
                  FROM {theme_iiidem2_shared_reading}
                 WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!\theme_iiidem2\shared_readings::table_ready()) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $fs = get_file_storage();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $records = $DB->get_records('theme_iiidem2_shared_reading', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ], 'timecreated ASC');
            if (!$records) {
                continue;
            }
            $exported = [];
            foreach ($records as $record) {
                $exported[] = [
                    'title' => $record->title,
                    'timecreated' => \core_privacy\local\request\transform::datetime((int) $record->timecreated),
                ];
                writer::with_context($context)->export_area_files(
                    [get_string('sharedreadingsheading', 'theme_iiidem2')],
                    'theme_iiidem2',
                    \theme_iiidem2\shared_readings::FILEAREA,
                    (int) $record->id
                );
            }
            writer::with_context($context)->export_data(
                [get_string('sharedreadingsheading', 'theme_iiidem2')],
                (object) ['readings' => $exported]
            );
        }
    }

    /**
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE || !\theme_iiidem2\shared_readings::table_ready()) {
            return;
        }
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'theme_iiidem2', \theme_iiidem2\shared_readings::FILEAREA);
        $DB->delete_records('theme_iiidem2_shared_reading', ['courseid' => $context->instanceid]);
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!\theme_iiidem2\shared_readings::table_ready()) {
            return;
        }
        $userid = (int) $contextlist->get_user()->id;
        $fs = get_file_storage();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $records = $DB->get_records('theme_iiidem2_shared_reading', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
            foreach ($records as $record) {
                $fs->delete_area_files(
                    $context->id,
                    'theme_iiidem2',
                    \theme_iiidem2\shared_readings::FILEAREA,
                    (int) $record->id
                );
            }
            $DB->delete_records('theme_iiidem2_shared_reading', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !\theme_iiidem2\shared_readings::table_ready()) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        $fs = get_file_storage();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = $inparams + ['courseid' => $context->instanceid];
        $records = $DB->get_records_select(
            'theme_iiidem2_shared_reading',
            "courseid = :courseid AND userid {$insql}",
            $params
        );
        foreach ($records as $record) {
            $fs->delete_area_files(
                $context->id,
                'theme_iiidem2',
                \theme_iiidem2\shared_readings::FILEAREA,
                (int) $record->id
            );
        }
        $DB->delete_records_select(
            'theme_iiidem2_shared_reading',
            "courseid = :courseid AND userid {$insql}",
            $params
        );
    }

    /**
     * Store all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {

        $draweropenindexpref = get_user_preferences(self::DRAWER_OPEN_INDEX, null, $userid);

        if (isset($draweropenindexpref)) {
            $preferencestring = get_string('privacy:drawerindexclosed', 'theme_iiidem2');
            if ($draweropenindexpref == 1) {
                $preferencestring = get_string('privacy:drawerindexopen', 'theme_iiidem2');
            }
            \core_privacy\local\request\writer::export_user_preference(
                'theme_iiidem2',
                self::DRAWER_OPEN_INDEX,
                $draweropenindexpref,
                $preferencestring
            );
        }

        $draweropenblockpref = get_user_preferences(self::DRAWER_OPEN_BLOCK, null, $userid);

        if (isset($draweropenblockpref)) {
            $preferencestring = get_string('privacy:drawerblockclosed', 'theme_iiidem2');
            if ($draweropenblockpref == 1) {
                $preferencestring = get_string('privacy:drawerblockopen', 'theme_iiidem2');
            }
            \core_privacy\local\request\writer::export_user_preference(
                'theme_iiidem2',
                self::DRAWER_OPEN_BLOCK,
                $draweropenblockpref,
                $preferencestring
            );
        }
    }
}
