<?php

defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'mod_wwassignment\task\updatedirtysets',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '4',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    )
);
