<?php

namespace mod_wwassignment\task;

defined('MOODLE_INTERNAL') || die();

class update_dirty_sets extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('updatedirtysets', 'mod_wwassignment');
    }

    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/wwassignment/locallib.php');

        _wwassignment_update_dirty_sets();
    }
}
