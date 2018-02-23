<?php
/**
* @desc This page lists all the instances of wwassignments within a particular course.
*/

require_once(dirname(__FILE__) . "/../../config.php");
require_once(dirname(__FILE__) . "/locallib.php");

// global database object
global $DB,$OUTPUT,$PAGE;

//get the course ID from GET line
$id = required_param('id', PARAM_INT);

//check this course exists
if (!$course = $DB->get_record("course", array( "id" => $id ))) {
    error("Course ID is incorrect");
}
 
//force login   
require_login($course->id);
//add_to_log($course->id, "wwassignment", "view all", "index.php?id=$course->id", "");
$event = \mod_wwassignment\event\course_module_instance_list_viewed::create(array(
    'context' => context_course::instance($course->id)
));
$event->trigger();

//Get all required strings
$strwwassignments = get_string("modulenameplural", "wwassignment");
$strwwassignment  = get_string("modulename", "wwassignment");

//Print the header
#if ($course->category) {
#    $navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> �";
#}
#"$course->shortname: $strwwassignments", "$course->fullname", "$navigation $strwwassignments", "", "", true, "", navmenu($course));

$PAGE->set_heading("$course->fullname");
$PAGE->set_title("$course->shortname: $strwwassignments");
$PAGE->set_cacheable(true);
$PAGE->set_focuscontrol("");
$PAGE->set_button("");
$PAGE->navbar->add("$strwwassignments");
$page_url = new moodle_url('/wwassignment/index.php',array('id' => $course->id) );
$PAGE->set_url($page_url);
echo $OUTPUT->header();

//Get all the appropriate data
if (!$wwassignments = get_all_instances_in_course("wwassignment", $course)) {
    notice("There are no $strwwassignments", "../../course/view.php?id=$course->id");
    die;
}

//Print the list of instances (your module will probably extend this)
$timenow = time();
$strname  = get_string("name");
$strweek  = get_string("week");
$strtopic  = get_string("topic");
$strdescription = get_string('description');
$stropendate = get_string("open_date", "wwassignment");
$strduedate = get_string("due_date", "wwassignment");
$strtotalpoints = get_string("total_points","wwassignment");

// create a table
$table = new html_table();

if ($course->format == "weeks") {
    $table->head  = array ($strweek, $strname,$strdescription, $stropendate, $strduedate, $strtotalpoints);
    $table->align = array ("center", "left", "left", "left", "left");
} else if ($course->format == "topics") {
    $table->head  = array ($strtopic, $strname,$strdescription, $stropendate, $strduedate, $strtotalpoints);
    $table->align = array ("center", "left", "left", "left", "left", "left", "left");
} else {
    $table->head  = array ($strname,$strdescription, $stropendate, $strduedate,$strtotalpoints);
    $table->align = array ("left", "left", "left", "left", "left", "left");
}

$wwclient = new wwassignment_client();
$wwcoursename = _wwassignment_mapped_course($COURSE->id,false);

foreach ($wwassignments as $wwassignment) {
    // grab specific info for this set:
    if(isset($wwassignment)) {
        $wwsetname = $wwassignment->webwork_set;
        $wwsetinfo = $wwclient->get_assignment_data($wwcoursename,$wwsetname,false);
        
        if (!$wwassignment->visible) {
            //Show dimmed if the mod is hidden
            $link = "<a class=\"dimmed\" href=\"view.php?id=$wwassignment->coursemodule\">$wwassignment->name</a>";
        } else {
            //Show normal if the mod is visible
            $link = "<a href=\"view.php?id=$wwassignment->coursemodule\">$wwassignment->name</a>";
        }
        if ($course->format == "weeks" or $course->format == "topics") {
            $totalpoints = $wwclient->get_max_grade($wwcoursename, $wwsetname,false);
            $table->data[] = array ($wwassignment->section,  $link, $wwassignment->description, strftime("%c", $wwsetinfo['open_date']), strftime("%c", $wwsetinfo['due_date']), $totalpoints);
        } else {
            $table->data[] = array ($link, $wwassignment->description, strftime("%c", $wwsetinfo['open_date']), strftime("%c", $wwsetinfo['due_date']));
        }
    }
}

echo "<br />";
    
echo html_writer::table($table);

/*if( isteacher($course->id) ) {
    $wwusername = $USER->username;
    
    $wwlink = _wwassignment_link_to_instructor_auto_login($wwcoursename,$wwusername,)
    print("<p style='font-size: smaller; color: #aaa; text-align: center;'><a style='color: #666;text-decoration:underline' href='".WWASSIGNMENT_WEBWORK_URL."/$course->shortname/instructor' target='_webwork_edit'>".get_string("go_to_webwork", "wwassignment")."</a></p>");
}*/

/// Finish the page
echo $OUTPUT->footer();

?>
