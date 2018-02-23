<?php
global $CFG, $DB;
require_once(dirname(__FILE__) . "/locallib.php");

// debug switch defined in locallib.php  define('WWASSIGNMENT_DEBUG',0);

/** /////////////////////////////////////////////////////////////
* External grade triggers
*      1. wwassignment_update_grades(wwassignment,userid=0) is called from
*           grade_update_mod_grades in gradlib.php and also from wwassignment/upgrade.php file
*            grade_update_mod_grades is called by $grade_item->refresh_grades
*         * handles updating the actual grades
*      2. wwassignment_grade_item_update(wwassignment)
*           is called from grade_update_mod_grades (before update_grades(wwassignment,userid=0))) 
*         * updates the items (e.g. homework sets) that are graded
*
* High level grade calls are in gradelib.php  (see end of file)
*/
//

// Internal grade calling structure
//
//   1. wwassignment_update_grades($wwassignment=null, $userid=0, $nullifnone=true) 
//       -- updates grades for assignment instance or all instances
//              * wwassignment_get_user_grades($wwassignment,$userid=0)  
//                      -- fetches homework grades from WeBWorK
//					* _wwassignment_get_course_students($courseid) -- collects users from moodle database
//                  * $wwclient->grade_users_sets($webworkcourse,$webworkusers,$webworkset) 
//                        -- fetches grades from a given course, set and user collection
//   2. wwassignment_grade_item_update(wwassignment, grades)
//                      grade_update(...) -- fills record in grade_item table and possibly in grade_grades table as well
//
////////////////////////////////////////////////////////////////
// This functino is defined in gradeslib.php -- I believe
// function grade_update($source, $courseid, $itemtype, $itemmodule, $iteminstance, $itemnumber, $grades=NULL, $itemdetails=NULL) {
////////////////////////////////////////////////////////////////



////////////////////////////////////////////////////////////////
//Functions that are called by the Moodle System
////////////////////////////////////////////////////////////////

/**
* @desc Called when the module is installed into the Moodle System
*/
function wwassignment_install() {
}

/**
* @desc Creates a new Moodle assignment <-> Webwork problem set tie.
* @param $wwassignment object The data of the record to be entered in the DB.
* @return integer The ID of the new record.
*/
function wwassignment_add_instance($wwassignment) {
    global $COURSE,$DB;
    traceLog("-----------Begin wwassignment_add_instance-----------");
    debugLog("input wwassignment ");
    //debugLog( print_r($wwassignment, true) );
    
    //Get data about the set from WebWorK
    $wwclient = new wwassignment_client();
    $wwassignment->webwork_course = _wwassignment_mapped_course($COURSE->id,false);
    $wwsetdata = $wwclient->get_assignment_data($wwassignment->webwork_course,$wwassignment->webwork_set,false);
    

    
    //Attaching Moodle Set to WeBWorK Set
    debugLog("saving wwassignment ");
    debugLog( print_r($wwassignment,true));
    
     $wwassignment->timemodified = time();   
    if ($returnid = $DB->insert_record("wwassignment", $wwassignment)) {
    	$wwassignment->id = $returnid;

		//Creating events
		_wwassignment_create_events($wwassignment,$wwsetdata);
    debugLog("notify gradebook");
		//notify gradebook
		 wwassignment_grade_item_update($wwassignment);
	}
    traceLog("----------End wwassignment_add_instance------------");
    return $returnid;
}

/**
* @desc Updates and resynchronizes all information related to the a moodle assignment <-> webwork problem set tie.
*       except for grades
* @param $wwassignment object The data of the record to be updated in the DB.
* @return integer The result of the update_record function.
*/
function wwassignment_update_instance($wwassignment) {
    global $COURSE,$DB;
    traceLog("---------Begin wwassignment_update_instance---------");
    

    //checking mappings
    $wwclient = new wwassignment_client();
    $wwcoursename = _wwassignment_mapped_course($COURSE->id,false);
    $wwassignment->webwork_course = $wwcoursename;
    $wwsetname = $wwassignment->webwork_set;
    $wwsetdata = $wwclient->get_assignment_data($wwcoursename,$wwsetname,false);
    
    
    //get data from WeBWorK
    $wwsetdata = $wwclient->get_assignment_data($wwcoursename,$wwsetname,false);
    $wwassignment->id = $wwassignment->instance;
    $wwassignment->grade = $wwclient->get_max_grade($wwcoursename,$wwsetname,false);
    $wwassignment->timemodified = time();
    $returnid = $DB->update_record('wwassignment',$wwassignment);
    
    _wwassignment_delete_events($wwassignment->id);
    _wwassignment_create_events($wwassignment,$wwsetdata);
    
     //notify gradebook -- update  grades for this wwassignment only
     wwassignment_grade_item_update($wwassignment);
     wwassignment_update_grades($wwassignment);     
     traceLog("-------End wwassignment_update_instance---------");
    
    return $returnid;
}

/**
* @desc Deletes a tie in Moodle. Deletes nothing in webwork.
* @param integer $wwassignmentid The id of the assignment to delete.
* @return bool Delete was successful or not.
*/
function wwassignment_delete_instance($wwassignmentid) {    
    global $DB;
    traceLog("---------- Begin wwassignment_delete_instance -------------");
    debugLog("input wwassignmentid:".print_r($wwassignmentid,true));
    $result = true;

    #delete DB record
    if ( ! $wwassignment = $DB->get_record('wwassignment', array( 'id'=>$wwassignmentid ))) {
        $result = false;
    }
    
    $wwassignment->courseid = $wwassignment->course;

    #delete events
    _wwassignment_delete_events($wwassignmentid);
    
    
    // Get the cm id to properly clean up the grade_items for this assignment
    // bug 4976
//     if (! $cm = get_record('modules', 'name', 'wwassignment')) {
//         $result = false;
//     } else {
//         if (! delete_records('grade_item', 'modid', $cm->id, 'cminstance', $wwassignment->id)) {
//             $result = false;
//         }
//     }
     
      if (! $DB->delete_records('wwassignment', array( 'id'=>$wwassignment->id ))) {
            $result = false;
      }     
     
     //notify gradebook
     wwassignment_grade_item_delete($wwassignment);
    traceLog("------- End wwassignment_delete_instance --------");
    return $result;
}

/** gradebook upgrades
    * add xxx_update_grades() function into mod/xxx/lib.php
    * add xxx_grade_item_update() function into mod/xxx/lib.php
    * patch xxx_update_instance(),  xxx_insert_instance()? xxx_add_instance() and xxx_delete_instance() to call xxx_grade_item_update()
    * patch all places of code that change grade values to call xxx_update_grades()
    * patch code that displays grades to students to use final grades from the gradebook�
**/
    

/**
 * Return, for a given homework assignment, the grade for a single user or for all users.
 *
 * @param int $assignmentid id of assignment
 * @param int $userid optional user id, 0 means all users
 * @return a hash  of  studentid=>grade values , false if none
 */


function wwassignment_get_user_grades($wwassignment,$userid=0) {
	traceLog("------Begin wwassignment_get_user_grades -- fetch grades from WW -----");
	debugLog("inputs -- wwassignment" . print_r($wwassignment,true));
	debugLog("userid = $userid");
	
	//checking mappings
	$courseid = $wwassignment->course;
	$wwclient = new wwassignment_client();
	$wwcoursename = _wwassignment_mapped_course($courseid,false);
	$wwsetname = $wwassignment->webwork_set;
	$usernamearray = array();
	$students      = array();
	$studentgrades = array();
	if ($userid) {
		$user = get_complete_user_data('id',$userid);
		$username = $user->username;
		array_push($usernamearray, $username);
		array_push($students, $user);
	} else {  // get all student names
		$students = _wwassignment_get_course_students( $courseid);
		foreach($students as $student) {
			array_push($usernamearray,$student->username);
		}
	}
	// get data from WeBWorK
	debugLog("fetch grades from course: $wwcoursename set: $wwsetname");
	$gradearray = $wwclient->grade_users_sets($wwcoursename,$usernamearray,$wwsetname); 
	
	// returns an array of grades -- the number of questions answered correctly?
	// debugLog("usernamearray " . print_r($usernamearray, true));
	// debugLog("grades($wwcoursename,usernamearray,$wwsetname) = " . print_r($gradearray, true));
	// model for output of grades
	
	// FIXME? return key/value pairs instead? in grade_users_sets?
	// this next segment matches students and their grades by dead reckoning
	
	$i =0;
	foreach($students as $student) {
		$studentid = $student->id;
		$grade = new stdClass();
			$grade->userid = $studentid;
	        $grade->rawgrade = (is_numeric($gradearray[$i])) ? $gradearray[$i] : '';
			$grade->feedback = "some text";
			$grade->feedbackformat = 0;
			$grade->usermodified = 0;
			$grade->dategraded = 0;
			$grade->datesubmitted = 0;
			$grade->id = $studentid;
		$studentgrades[$studentid] = $grade;
		$i++;
	}

	
			
	// end model
	debugLog("output student grades:" . print_r($studentgrades,true) );
	traceLog("---------End wwassignment_get_user_grades---------");
	return $studentgrades;
}

/**
 * This can be called from outside wwassignment
 * Update grades by firing grade_updated event
 *
 * @param object $wwassignment object with extra cmidnumber  ??
 * @param object $wwassignment null means all wwassignments
 * @param int $userid specific user only, 0 mean all
**/
function wwassignment_update_grades($wwassignment=null, $userid=0, $nullifnone=true) {
    traceLog("------- Begin wwassignment_update_grades---------");
    //debugLog("inputs wwassignment = " . print_r($wwassignment,true));
    debugLog("userid = $userid");
    global $CFG, $DB;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($wwassignment != null) {
        // Make sure wwassignment has cmid defined isnce wwassignment_grade_item_update requires it
        if (!$wwassignment->cmidnumber) { // is this ever needed?
        	$wwassignment->cmidnumber =_wwassignment_cmid() ;
        	//error_log("adding cmidnumber to wwassignment".$wwassignment->cmidnumber);
        }

        if ($grades = wwassignment_get_user_grades($wwassignment, $userid)) { # fetches all students if userid=0
            foreach($grades as $k=>$v) {
                // doctor grades with a negative one
                if ($v->rawgrade == -1) {
                    $grades[$k]->rawgrade = null;
                }
            }
             wwassignment_grade_item_update($wwassignment, $grades);
        } else {
            wwassignment_grade_item_update($wwassignment);
        }

    } else {  // find all the wwassignments in all courses and update all of them.
        debugLog("import grades for all wwassignments for all courses");
        $sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
                  FROM {wwassignment} a, {course_modules} cm, {modules} m
                 WHERE m.name='wwassignment' AND m.id=cm.module AND cm.instance=a.id";

        //$sql = " SELECT a.*  FROM {$CFG->prefix}wwassignment a";
        //debugLog ("sql string = $sql");
        //$tmp = get_recordset_sql($sql);
        //error_log("result is ".print_r($tmp,true) );
       if ($rs = $DB->get_recordset_sql($sql)) {
            debugLog("record set found");
            foreach ($rs as $wwassignment) {
                if (!$wwassignment->cmidnumber) { // is this ever needed?
					$wwassignment->cmidnumber =_wwassignment_cmid() ;
				}
 
                //debugLog("processing next grade wwassignment is ".print_r($wwassignment,true) );
                if ($wwassignment->grade != 0) {
                    wwassignment_update_grades($wwassignment);
                } else {
                   wwassignment_grade_item_update($wwassignment);
                }
            }
            $rs->close();
        }
    }

	traceLog("--------End wwassignment_update_grades--------");

}
/**
 * Create grade item for given assignment
 *
 * @param object $wwassignment object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * side effect: uses contents of grades to change the values in the gradebook. 
 * grades contains gradeitem objects containing all of the necessary information
 *         	$grade->userid = $studentid;
 * 	        $grade->rawgrade = (is_numeric($gradearray[$i])) ? $gradearray[$i] : '';
 * 			$grade->feedback = "some text";
 * 			$grade->feedbackformat = 0;
 * 			$grade->usermodified = 0;
 * 			$grade->dategraded = 0;
 * 			$grade->datesubmitted = 0;
 * 			$grade->id = $studentid;
 * @return int 0 if ok, error code otherwise
**/
function wwassignment_grade_item_update ($wwassignment, $grades=NULL) {
    traceLog("------- Begin wwassignment_grade_item_update ------- ");
    $msg = "Begin wwassignment_grade_item_update";
    $msg = ($grades)? $msg . " with grades (updates grade_grades table)" :$msg;
	debugLog($msg);
	// debugLog("inputs wwassignment " . print_r($wwassignment, true));
	// debugLog("grades " . print_r($grades, true) );
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($wwassignment->courseid)) {
        $wwassignment->courseid = $wwassignment->course;
    }
    if (!isset($wwassignment->grade) ) {  // this case occurs when the set link is edited from moodle activity editor
    	$wwclient = new wwassignment_client();
    	$wwcoursename = _wwassignment_mapped_course($wwassignment->courseid,false); //last 'false' means report errors
        $wwsetname    = _wwassignment_mapped_set($wwassignment->id,false);
    	$wwassignment->grade = $wwclient->get_max_grade($wwcoursename,$wwsetname,false);
    }
	// set maximum grade in wwassignment record as "grade" for the homework set.
	
    $params = array('itemname'=>$wwassignment->name, 'idnumber'=>$wwassignment->cmidnumber);

    if ($wwassignment->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $wwassignment->grade;
        $params['grademin']  = 0;

    } else if ($wwassignment->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$wwassignment->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }
    // grade_update() defined in gradelib.php 
    // $grades=NULL means update grade_item table only, otherwise post grades in grade_grades
    debugLog("wwassignment_grade_item_update: update grades for courseid: ". $wwassignment->courseid . 
    " assignment id: ".$wwassignment->id." time modified ".
    $wwassignment->timemodified."grades".print_r($grades,true));
    traceLog("------- end wwassignment_grade_item_update ------- ");
    return grade_update('mod/wwassignment', $wwassignment->courseid, 'mod', 'wwassignment',
               $wwassignment->id, 0, $grades, $params);
}
/**
 * Delete grade item for given assignment
 *
 * @param object $wwassignment object
 * @return object wwassignment ????
 */
function wwassignment_grade_item_delete($wwassignment) {
	traceLog("-------Begin wwassignment_grade_item_delete------");
	debugLog("inputs wwassignment " . print_r($wwassignment, true) );

    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($wwassignment->courseid)) {
        $wwassignment->courseid = $wwassignment->course;
    }
	traceLog("-------End wwassignment_grade_item_delete---------");
    return grade_update('mod/wwassignment', $wwassignment->courseid, 'mod', 'wwassignment', $wwassignment->id, 0, NULL, array('deleted'=>1));


}
/**
 * Updates an assignment instance
 *
 * This is done by calling the update_instance() method of the assignment type class
 */
function wwassignment_item_update($wwassignment) {
	traceLog("------Begin wwassignment_item_update -- not yet defined!!!!------");
	debugLog("input wwassignment " . print_r($wwassignment,true) );
	traceLog("-------End wwassignment_item_update -- not yet defined!!!!-------");	
}
/**
* @desc Contacts webwork to find out the completion status of a problem set for all users in a course.
* @param integer $wwassignmentid The problem set
* @return object The student grades indexed by student ID.
*/
function wwassignment_grades($wwassignmentid) {
	traceLog("------ Begin wwassignment_grades -- legacy function?-------");
    global $COURSE,$DB;
    $wwclient = new wwassignment_client();
    $wwassignment = $DB->get_record('wwassignment', array( 'id'=>$wwassignmentid ));
    $courseid     = $wwassignment->course;
    
    $studentgrades = new stdClass;
    $studentgrades->grades = array();
    $studentgrades->maxgrade = 0;
    
    $gradeformula = '$finalgrade += ($problem->status > 0) ? 1 : 0;';
    
    $wwcoursename = _wwassignment_mapped_course($courseid,false);
    $wwsetname    = _wwassignment_mapped_set($wwassignmentid,false);
    
    // enumerate over the students in the course:
    $students = get_course_students( $courseid);
    
    $usernamearray = array();
    foreach($students as $student) {
        array_push($usernamearray,$student->username);
    }
    $gradearray  = $wwclient->grade_users_sets($wwcoursename,$usernamearray,$wwsetname);
    $i = 0;
    foreach($students as $student) {
        $studentgrades->grades[$student->id] = $gradearray[$i];
        $i++;
    }
    $studentgrades->maxgrade = $wwclient->get_max_grade($wwcoursename,$wwsetname); 
//    error_log("End wwassignment_grades -- legacy function?");
    traceLog("------ End wwassignment_grades -- legacy function?-------");
    return $studentgrades;
}


/**
* @desc Returns a small object with summary information about a wwassignment instance. Used for user activity repots.
* @param string $course The ID of the course.
* @param string $user The ID of the user.
* @param string $wwassignment The ID of the wwassignment instance.
* @return array Representing time, info pairing.
*/
function wwassignment_user_outline($course, $user, $mod, $wwassignment) {
    traceLog("--------Begin wwassignment_user_outline-----------------");
    $aLogs = get_logs("l.userid=$user AND l.course=$course AND l.cmid={$wwassignment->id}");
    if( count($aLogs) > 0 ) {
        $return->time = $aLogs[0]->time;
        $return->info = $aLogs[0]->info;
    }
    traceLog("--------End wwassignment_user_outline-----------------");
    return $return;
}

/**
* @desc Prints a detailed representation of what a user has done with a instance of this module.
* @param string $course The ID of the course.
* @param string $user The ID of the user.
* @param string $wwassignment The ID of the wwassignment instance.
* @return array Representing time, info pairing.
*/
function wwassignment_user_complete($course, $user, $mod, $wwassignment) {    
    return true;
}



function wwassignment_delete_course() {
//    error_log("Begin wwassignment_delete_course --not used yet");
}

function wwassignment_process_options() {
//    error_log("Begin wwassignment_process_options --not used yet");
}

function wwassignment_reset_course_form() {
//    error_log("Begin wwassignment_reset_course_form --not used yet");
}

function wwassignment_delete_userdata() {
//     error_log("Begin wwassignment_delete_userdata --not used yet");
}

/**
* @desc Finds recent activity that has occured in wwassignment activities.
*/
function wwassignment_print_recent_activity($course, $isteacher, $timestart) {
        global $CFG;
        traceLog("-------- Begin wwassignment_print_recent_activity --not used yet -------");
        traceLog("-------- End wwassignment_print_recent_activity --not used yet -------");

        return false;  //  True if anything was printed, otherwise false 
}

/**
* @desc Function that is run by the cron job. This makes sure that 
* the grades and all other data are pulled from webwork.
* returns true if successful
*/
function wwassignment_cron() {	
    traceLog("-------------Begin wwassignment_cron-------------------------");

    //FIXME: Add a call that updates all events with dates (in case people forgot to push)
    //wwassignment_refresh_events();
    //FIXME: Add a call that updates all grades in all courses
    //wwassignment_update_grades(null,0); 
   //try {    // try didn't work on some php systems -- leave it out.
    	 _wwassignment_update_dirty_sets();
    traceLog("---------------------End wwassignment_cron------------------------");
    return true;
}


// reference material for improving update dirty sets:
//  from wiki/lib /print_recent_activity 
//  $sql = "SELECT l.*, cm.instance FROM {$CFG->prefix}log l 
//                 INNER JOIN {$CFG->prefix}course_modules cm ON l.cmid = cm.id 
//             WHERE l.time > '$timestart' AND l.course = {$course->id} 
//                 AND l.module = 'wiki' AND action LIKE 'edit%'
//             ORDER BY l.time ASC";
//             
//     if (!$logs = get_records_sql($sql)){
//         return false;
//     }
// 
//     $modinfo = get_fast_modinfo($course);
//     

// function report_log_userday($userid, $courseid, $daystart, $logreader = '') {
//     global $DB;
//     $logmanager = get_log_manager();
//     $readers = $logmanager->get_readers();
//     if (empty($logreader)) {
//         $reader = reset($readers);
//     } else {
//         $reader = $readers[$logreader];
//     }
// 
//     // If reader is not a sql_internal_table_reader and not legacy store then return.
//     if (!($reader instanceof \core\log\sql_internal_table_reader) && !($reader instanceof logstore_legacy\log\store)) {
//         return array();
//     }
// 
//     $daystart = (int)$daystart; // Note: unfortunately pg complains if you use name parameter or column alias in GROUP BY.
// 
//     if ($reader instanceof logstore_legacy\log\store) {
//         $logtable = 'log';
//         $timefield = 'time';
//         $coursefield = 'course';
//         // Anonymous actions are never logged in legacy log.
//         $nonanonymous = '';
//     } else {
//         $logtable = $reader->get_internal_log_table_name();
//         $timefield = 'timecreated';
//         $coursefield = 'courseid';
//         $nonanonymous = 'AND anonymous = 0';
//     }
//     $params = array('userid' => $userid);
// 
//     $courseselect = '';
//     if ($courseid) {
//         $courseselect = "AND $coursefield = :courseid";
//         $params['courseid'] = $courseid;
//     }
//     return $DB->get_records_sql("SELECT FLOOR(($timefield - $daystart)/" . HOURSECS . ") AS hour, COUNT(*) AS num
//                                    FROM {" . $logtable . "}
//                                   WHERE userid = :userid
//                                         AND $timefield > $daystart $courseselect $nonanonymous
//                                GROUP BY FLOOR(($timefield - $daystart)/" . HOURSECS . ") ", $params);
// }
// 
// target = course_module
// objecttable = wwassignment
// id  = 
// timecreated
// courseid


/**
* @desc Finds all the participants in the course
* @param string $wwassignmentid The Moodle wwassignment ID.
* @return array An array of course users (IDs).
*/

function wwassignment_get_participants($wwassignmentid) {
    global $DB;
    $wwassignment = $DB->get_record('wwassignment', array( 'id'=>$wwassignmentid ));
    if(!isset($wwassignment)) {
        return array();
    }
    return _wwassignment_get_course_students( $wwassignment->course );
}

//FIXME -- this should be restored
function wwassignment_refresh_events($courseid = 0) {
     global $DB;
     traceLog("----------------- Begin wwassignment_refresh_events ---------------");
     _wwassignment_refresh_events($courseid);
     traceLog("----------------- End wwassignment_refresh_events ---------------");
}

     
// 
// // This standard function will check all instances of this module
// // and make sure there are up-to-date events created for each of them.
// // If courseid = 0, then every wwassignment event in the site is checked, else
// // only wwassignment events belonging to the course specified are checked.
// // This function is used, in its new format, by restore_refresh_events() and by the cron function
// // 
//     // find wwassignment instances associated with this course or all wwassignment modules
//      $courses = array();  # create array of courses
//     if ($courseid) {
//         if (! $wwassignments = $DB->get_records("wwassignment", array("course"=>$courseid) )) {
//             return true;
//         } else {
//         	$courses[$courseid]= array();      // collect wwassignments for this course
//         	array_push( $courses[$courseid],   $wwassignments );  
//         }
//     } else {
//         if (! $wwassignments = $DB->get_records("wwassignment")) {
//             return true;
//         } else {
//         	foreach ($wwassignments as $ww ) {
//         		// collect wwassignments for each course
// //        		error_log("course id ".$ww->course);
//         		if (! ($courses[$ww->course] ) ) {
//         			$courses[$ww->course] = array();
//         		}
//         		array_push($courses[$ww->course], $ww) ;  // push wwassignment onto an exisiting one
//         	}
//         }
//         	
//     }
// 
//  
//     // $courses now holds a list of courses with wwassignment modules
//     $moduleid = _wwassignment_cmid();
//     $cids = array_keys($courses);   # collect course ids
// //    error_log("cids".print_r($cids, true));
//     $wwclient = new wwassignment_client();
//     foreach ($cids as $cid) {
//     // connect to WeBWorK
// 	$wwcoursename = _wwassignment_mapped_course($cid,false); 
// 	$wwassignment->webwork_course = $wwcoursename;
// 	if ( $wwcoursename== -1) {
// //		error_log("Can't connect course $cid to webwork");
// 		break;
// 	}
// 	// retrieve wwassignments associated with this course
// 		foreach($courses[$cid] as $wwassignment ) {
//  		   //checking mappings
// 			$wwsetname = $wwassignment->webwork_set;
// // 			error_log("updating events for $wwcoursename $wwsetname");
//  			//get data from WeBWorK
// 			$wwsetdata = $wwclient->get_assignment_data($wwcoursename,$wwsetname,false);
// 			$wwassignment->grade = $wwclient->get_max_grade($wwcoursename,$wwsetname,false);
// 			$wwassignment->timemodified = time();
// 			$returnid = $DB->update_record('wwassignment',$wwassignment);
// 			// update event
// 			//this part won't work because these items implicitly require the course.
// 			_wwassignment_delete_events($wwassignment->id);
// 			_wwassignment_create_events($wwassignment, $wwsetdata);
// 		 }  
// 	 
// 	} 
//     traceLog("----------------- End wwassignment_refresh_events ---------------");
// 
//     return true;
// }

///////////////////////////////////////////////////////////////////////////////////////
// High level grade calls ins gradelib.php
///////////////////////////////////////////////////////////////////////////////////////

/** A. 
 * Returns grading information for given activity - optionally with users grades
 * Manual, course or category items can not be queried.
 * @public
 * @param int $courseid id of course
 * @param string $itemtype 'mod', 'block'
 * @param string $itemmodule 'forum, 'quiz', etc.
 * @param int $iteminstance id of the item module
 * @param int $userid_or_ids optional id of the graded user or array of ids; if userid not used, returns only information about grade_item
 * @return array of grade information objects (scaleid, name, grade and locked status, etc.) indexed with itemnumbers
 */
 
// A. function grade_get_grades($courseid, $itemtype, $itemmodule, $iteminstance, $userid_or_ids=null) {
 
 
 /** B.
 * Submit new or update grade; update/create grade_item definition. Grade must have userid specified,
 * rawgrade and feedback with format are optional. rawgrade NULL means 'Not graded', missing property
 * or key means do not change existing.
 *
 * Only following grade item properties can be changed 'itemname', 'idnumber', 'gradetype', 'grademax',
 * 'grademin', 'scaleid', 'multfactor', 'plusfactor', 'deleted' and 'hidden'. 'reset' means delete all current grades including locked ones.
 *
 * Manual, course or category items can not be updated by this function.
 * @public
 * @param string $source source of the grade such as 'mod/assignment'
 * @param int $courseid id of course
 * @param string $itemtype type of grade item - mod, block
 * @param string $itemmodule more specific then $itemtype - assignment, forum, etc.; maybe NULL for some item types
 * @param int $iteminstance instance it of graded subject
 * @param int $itemnumber most probably 0, modules can use other numbers when having more than one grades for each user
 * @param mixed $grades grade (object, array) or several grades (arrays of arrays or objects), NULL if updating grade_item definition only
 * @param mixed $itemdetails object or array describing the grading item, NULL if no change
 */
 
/** 
*   B. function grade_update($source, $courseid, $itemtype, $itemmodule, $iteminstance, 
*        $itemnumber, $grades=NULL, $itemdetails=NULL) {}

/**  C.
* Refetches data from all course activities
* @param int $courseid
* @param string $modname
* @return success
*   C. function grade_grab_course_grades($courseid, $modname=null) {}
*/
///////////////////////////////////////////////////////////////////////////////////////

function wwassignment_supports($feature) {
    switch($feature) {
      case FEATURE_BACKUP_MOODLE2:  return true;
	
      default: return null;
    }
}


/**
 * Given a coursemodule object, `wwassignment_get_coursemodule_info` function returns the extra
 * information needed to print this activity in various places.
 *
 * If folder needs to be displayed inline we store additional information
 * in customdata, so functions {@link folder_cm_info_dynamic()} and
 * {@link folder_cm_info_view()} do not need to do DB queries
 *
 * @param cm_info $cm
 * @return cached_cm_info info
 */
function wwassignment_get_coursemodule_info($cm) {
    global $DB;
    if (!($wwassign = $DB->get_record('wwassignment', array('id' => $cm->instance),
        'id, name, webwork_set, grade, intro, introformat'))) {
        return NULL;
    }
    $cminfo = new cached_cm_info();
    $cminfo->name = $wwassign->name;
    if ($cm->showdescription && strlen(trim($wwassign->intro))) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $cminfo->content = format_module_intro('wwassignment', $wwassign, $cm->id, false);
    }

    return $cminfo;
}

?>
