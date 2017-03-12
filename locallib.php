<?php
global $CFG,$DB;
#require_once("$CFG->libdir/soap/nusoap.php");
require_once("$CFG->libdir/soaplib.php");

define('WWASSIGNMENT_DEBUG',0);
// define('WWASSIGNMENT_DEBUG',1);
//define('WWASSIGNMENT_TRACE',0);
 define('WWASSIGNMENT_TRACE',1);


//////////////////////////////////////////////////////////////////
//Utility functions
//////////////////////////////////////////////////////////////////

/** 
 * @desc  prints message to the apache log
 * @param string  message
**/

function debugLog($message) {
	if (WWASSIGNMENT_DEBUG) {
		error_log($message);
		var_dump($message);
	}
}

function traceLog($message) {
	if (WWASSIGNMENT_TRACE) {
		error_log($message);
		mtrace($message);
	}
}
/** (reference from accesslib.php )
 * @desc  gets all the users assigned this role in this context or higher
 * @param int roleid (can also be an array of ints!)
 * @param int contextid
 * @param bool parent if true, get list of users assigned in higher context too
 * @param string fields - fields from user (u.) , role assignment (ra) or role (r.)
 * @param string sort  - sort from user (u.) , role assignment (ra) or role (r.)
 * @param bool gethidden - whether to fetch hidden enrolments too
 * @return array()
 */
//function get_role_users($roleid, $context, $parent=false, $fields='', $sort='u.lastname ASC', $gethidden=true, $group='', $limitfrom='', $limitnum='') {


/**
* @desc Finds all of the users in the course
* @param $courseid   -- the course id
* @return record containing user information ( username, userid)
*/

function _wwassignment_get_course_students($courseid) {
    traceLog("---------------Begin get_course_students($courseid )------------ ");
    traceLog("which fetches all the students in the course");
    debugLog("courseID is ". print_r($courseid, true));
	$context = context_course::instance($courseid);
	debugLog("context is ". print_r($context, true));
	
	$users = array();
	$roles_used_in_context = get_roles_used_in_context($context);
	debugLog("roles used ". print_r($roles_used_in_context, true));
	foreach($roles_used_in_context as $role) {
		$roleid = $role->id;
 		debugLog( "roleid should be 5 for a student $roleid");
 		debugLog(get_role_users($roleid, $context, true) );
 		if ($new_users = get_role_users($roleid, $context, true) ) {
			$users = array_merge($users, $new_users );
			//FIXME a user could be listed twice if they had two roles.
		}
		//debugLog("display users ".print_r($users,true));
	}
 	debugLog("getting logins of users in courseid $courseid");
	foreach($users as $item) {
		debugLog("user: ".$item->lastname);
	}
	
    traceLog("---------------End get_course_students($courseid )------------ ");
	return $users;


}
   
//////////////////////////////////////////////////////////////////
//EVENT CREATION AND DELETION
//////////////////////////////////////////////////////////////////

/**
* @desc Creates the corresponding events for a wwassignment.
* @param $wwsetname string The name of the set.
* @param $wwassignmentid string The ID of the wwassignment record.
* @param $opendate integer The UNIX timestamp of the open date.
* @param $duedate integer The UNIX timestamp of the due date.
* @return integer 0 on success. -1 on error.
*/

function _wwassignment_create_events($wwassignment,$wwsetdata ) {
    //global $COURSE;
    global $CFG;
    require_once("$CFG->dirroot/calendar/lib.php");
	traceLog("-----------------------Begin _wwassignment_create_events ---------------");
	traceLog(" create events for course: ". $wwassignment->name.
	         " assignment id ".$wwassignment->id." dates ".
	         $wwsetdata['open_date']." ".$wwsetdata['due_date'] );
    //$wwassignment->name is course name
    //$wwassignment->id   is name/id of set
    debugLog("set data".print_r($wwsetdata,true));
   if (! $opendate = $wwsetdata['open_date'] ) {
    	debugLog(" undefined open date ");
    }
    if (! $duedate = $wwsetdata["due_date"] ){
    	debugLog(" undefined due date ");
    } 
    if (! $wwassignmentid = $wwsetname = $wwassignment->id ) {
       	debugLog(" undefined set id/name "); 
    } 
    if (! $courseName = $wwassignment->name ) {
       	debugLog(" undefined course name ");
    } 
     if (! $courseid = $wwassignment->id ) {
       	debugLog(" undefined course name ");
    } 
  
//     unset($event);
    $event = new stdClass();
    $event->name = "$courseName $wwassignmentid";
    $event->description = 'WeBWorK Set Event';
    $event->courseid = $courseid;
    $event->groupid = 0;
    $event->userid = 0;
    //$event->format = 1;
    $event->modulename = 'wwassignment';
    $event->instance = $wwassignmentid;
    $event->visible  = 1;    
    $event->eventtype = 'due';
    $event->timestart = $duedate;
    $event->timeduration = 0;
    
// model
// 	$event = new stdClass;
// 	$event->name         = get_string('stop', 'feedback').' '.$feedback->name;
// 	$event->description  = format_module_intro('feedback', $feedback, $feedback->coursemodule);
// 	$event->courseid     = $feedback->course;
// 	$event->groupid      = 0;
// 	$event->userid       = 0;
// 	$event->modulename   = 'feedback';
// 	$event->instance     = $feedback->id;
// 	$event->eventtype    = 'feedbackcloses'; // For activity module's events, this can be used to set the alternative text of the event icon. Set it to 'pluginname' unless you have a better string.
// 	$event->timestart    = $feedback->timeclose;
// 	$event->visible      = instance_is_visible('feedback', $feedback);
// 	$event->timeduration = 0;
//  
//   calendar_event::create($event);
// 
	debugLog("adding a due event");
    $result = 0;
//     // $calendareventid = add_event($event); //calendar_event::create($event);//

// FIXME -- this throws an error. ????
//    $calendareventid = calendar_event::create($event);

    if(!$calendareventid) {
        debugLog("can't create calendarevent for set $wwsetname wwid $wwassignmentid date $opendate $duedate course $courseid");
        $result = -1;
    } else {
    	debugLog("created calendarevent for set $wwsetname wwid $wwassignmentid date $opendate $duedate course $courseid"
    	);
    }
	traceLog("-----------------------End _wwassignment_create_events ---------------");    
    return $result;
}


/**
* @desc Deletes all events relating to the wwassignment passed in.
* @param $wwassignmentid integer The wwassignment ID.
* @return integer 0 on success
*/
function _wwassignment_delete_events($wwassignmentid) {
    global $DB, $CFG;
    traceLog("----------Begin _wwassignment_delete_events ---------------"); 
    if ($events = $DB->get_records_select('event', "modulename = 'wwassignment' and instance = '$wwassignmentid'")) {
        foreach($events as $event) {
            debugLog("deleting  event ".$event->id);
            require_once("$CFG->dirroot/calendar/lib.php");
            $calEvent = new calendar_event($event);
            $calEvent->delete(true);
            //delete_event($event->id);  FIXME -- probably obsolete
        }
    }
    traceLog("----------End _wwassignment_delete_events ---------------"); 
    return 0;
}

function _wwassignment_update_dirty_sets() {  // update grades for all instances which have been modified since last cronjob
    global $CFG,$DB;
    global $CFG;
	require_once($CFG->dirroot.'/course/lib.php');
	require_once($CFG->dirroot.'/report/log/locallib.php');
	require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->dirroot.'/lib/tablelib.php');
    traceLog("-----------------Begin _wwassignment_update_dirty_sets---------------");
	$timenow = time();
	/////////////////////////////////////////////////////////////////////
	// Obtain the last time that wwassignment cron processes were triggered.
	/////////////////////////////////////////////////////////////////////
	$lastcron = $DB->get_field("modules","lastcron",array( "name"=>"wwassignment" ));
	//FIXME make sure to readjust lastcron value
	$lastcron = 1488778000; # so we get some examples
	
	debugLog("_wwassignment_update_dirty_sets:  lastcron is $lastcron and time now is $timenow");
	
	$logreader='';
     $logmanager = get_log_manager();
     $readers = $logmanager->get_readers();
    if (empty($logreader)) {
        $reader = reset($readers); //default is \core\log\sql_internal_table_reader
    } else {
        $reader = $readers[$logreader];
    }

	if ($reader instanceof \core\log\sql_internal_table_reader) {
		debugLog("wwassignment_update_dirty_sets: reader is instance of \core\log\sql_internal_table_reader" );
	}

    // If reader is not a sql_internal_table_reader and not legacy store then return.
    if (!($reader instanceof \core\log\sql_internal_table_reader) && 
        !($reader instanceof logstore_legacy\log\store)) {
        mtrace("wwassignment_update_dirty_sets:don't have access to the right kind of logs");
        debugLog("wwassignment_update_dirty_sets:bad logs ");
        return array();
    }
    /////////////////////////////////////////////////////////////////////
    //  This is the legacy code, it imitates reading from the old log file
    /////////////////////////////////////////////////////////////////////
    // $logRecords = get_logs("l.module LIKE \"wwassignment\" AND l.time >$lastcron ", null, "l.time ASC", $counter);	
    ////  The "log" file contains relevent fields
    ////    time   -- of record entry
    ////    course -- number of course targeted
    ////    module -- module targeted (e.g. wwassignment)
    ////    info   -- contains the number of wwassignment record (the homework set targeted)
    
    //////////////////////////////////////////////////////
    ////  legacy action: return the record for events targeting wwassignment 
    ////                 after the last cron run sorted by  time in ascending order
    /////////////////////////////////////////////////////
    
    //	foreach ($logRecords as $record) {     
  	//  		$wwmodtimes[$wwid =$record->info] = $record->time;
	//	}
	////  legacy action: create a hash $wwmodtimes with key= homework set (wwid), 
	////        value= last access to that homework set
	////  Notice that a given homework set is likely to have been accessed multiple times
	////  and this collapses all of those touches into a single reference
    
	// 	$idValues= implode(",", array_keys($wwmodtimes) );
	////  legacy action: Create an string with the wwid values ?? wouldn't an array have worked?
	
    //  list($usql,$params) = $DB->get_in_or_equal($idValues);
    
    //////////////////////////////////////////////////////////////////
    //// legacy action: result is a string $usql (sic) of the form 
    ////        "IN (?,?,?)"  and $params an array [23,12,45] 
    ////        where the numbers are wwid's
    //////////////////////////////////////////////////////////////////
    
     
//    	$sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid, cm.id as wwinstanceid " .
//                "FROM {wwassignment} a, {course_modules} cm, {modules} m WHERE m.name = 'wwassignment' " .
//                "AND m.id=cm.module AND cm.instance=a.id AND a.id $usql";
//       $rs = $DB->get_recordset_sql($sql,$params))


 ///////////////////////////////// /////////////////////////////////
 // Begin finding all of the wwassignment modules that have been touched since the last cron update
 ///////////////////////////////// /////////////////////////////////    

    //////////////////////////////////////////////////////////////////
    //// legacy action: select records from wwassignment with id's in $insql (or $usql (sic)) 
    //// -- and which therefore have been touched since the last cron update
    //// join each of these with the record in course_module which references (cm.instance) the record in wwassignmnent 
    //// and join each these also with the record in modules referenced from course_modules (cm.module).
    //// Return the complete wwassignment record, augmented with the course_module idnumber (has inconsistent data)
    //// the courseid (a number) and the course_module record id (cm.id)
    //////////////////////////////////////////////////////////////////


     if ($reader instanceof logstore_legacy\log\store) {
        debugLog("wwassignment_update_dirty_sets:Reading from the old style logs");   
        $logtable = 'log';
        $timefield = 'time';
        $coursefield = 'course';
        // Anonymous actions are never logged in legacy log.
        $nonanonymous = '';
    } else {
        $logtable = $reader->get_internal_log_table_name();
        $timefield = 'timecreated';
        $coursefield = 'courseid';
        $nonanonymous = 'AND anonymous = 0';
    }

// 
//     $courseselect = '';
//     if ($courseid) {
//         $courseselect = "AND $coursefield = :courseid";
//         $params['courseid'] = $courseid;
//     }

####################
# Look for activity involving wwassignment in the general log file
####################
     $params['module_type'] = 'course_module';
     $params['wwassignment']  = 'wwassignment';
 	debugLog("using the log table $logtable");

// 	// Could we speed this up by getting all of the log records pertaining to webwork in one go?
// 	// Or perhaps just the log records which have occured after the lastcron date
// 	// Then create a hash with wwassignment->id  => timemodified
// 	// means just one database lookup
//     $counter = 0;
//     // this is most likely what needs to be replaced
//     
//     
// 	// $logRecords = get_logs("l.module LIKE \"wwassignment\" AND l.time >$lastcron ", null, "l.time ASC", $counter,'');

//////////////////////////////////////////////////////////
// reproducing legacy effect with the call to the "log" table 
//////////////////////////////////////////////////////////
// Find all event records which have been created since the last cron update and which
// target a module
// AND the module table is labeled "wwassignment".
// return the time, the courseid, event id, 
//        the objecttable (named wwassign), and 
//        the target  (module_type)
// !!     the objectid (which wwassignment was touched)

	$logRecords = $DB->get_records_sql("SELECT $timefield  AS time, COUNT(*) AS num, 
	                             $coursefield AS courseid, target AS target, 
	                             objecttable AS module,
	                             objectid    AS wwassignmentid,
	                             id AS eventid
                                   FROM {" . $logtable . "}
                                  WHERE $timefield > $lastcron 
                                        AND target = :module_type
                                        AND objecttable = :wwassignment
                               GROUP BY $timefield", $params);
    $number_of_log_records = count($logRecords);
    debugLog("wwassignment_update_dirty_sets:number of logRecords $number_of_log_records");
    //debugLog(print_r($logRecords,true));
  	
	
	
 	$wwmodtimes=array();
 	foreach ($logRecords as $record) { 
    	  $wwmodtimes[$record->wwassignmentid] = $record->time;
 	}
 
// 
//  	// Create an array with the wwid values
//  	$idValues= implode(",", array_keys($wwmodtimes) );
//     //list($insql,$inparams) = $DB->get_in_or_equal($idValues,SQL_PARAMS_NAMED);
     $arraykeys = array_keys($wwmodtimes);
//     //debugLog("array_keys ".print_r($arraykeys,true));
     list($insql,$inparams) = $DB->get_in_or_equal($arraykeys,SQL_PARAMS_NAMED);
//     //list($insql, $inparams) = $DB->get_in_or_equal($wwmodtimes,SQL_PARAMS_NAMED);
//  	debugLog("values string: $idValues");
//     debugLog("last modification times".print_r($wwmodtimes,true));
     //debugLog("insql ".print_r($insql,true));
     //debugLog("array values".print_r(array_values($arraykeys),true));
     debugLog("inparams ".print_r($inparams, true));  


 ///////////////////////////////// /////////////////////////////////
 // End finding all of the wwassignment modules that have been touched since the last cron update
 ///////////////////////////////// /////////////////////////////////    
 // $insql looks like "IN(4,6,78)" where the numbers are ids (a.id) 
 //         of records in the wwassignment table
 //         these are records of homework sets that have been touched. 
 // 
 
 //////////////////////////////////////////////////////////////////
 // construct query for wwassignment table
 /////////////////////////////////////////////////////////////////
 
 // This should be the same query for both the legacy code
 // and the new code
 
 	$sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid, 
 	                    cm.id as wwinstanceid " .
                "FROM {wwassignment} a, 
                      {course_modules} cm, 
                      {modules} m 
                 WHERE a.id $insql          
                      AND cm.instance=a.id
                      AND m.id=cm.module 
                      AND m.name = 'wwassignment'
                ";
    debugLog("sql obtained from wwassignment is $sql");  
    $rs = $DB->get_recordset_sql($sql,$inparams);
 	
///////////////////////////////// 	
// 	
 	if ($rs = $DB->get_recordset_sql($sql,$inparams)) {
 		foreach ( $rs as $wwassignment ) {
 			debugLog("record set ".print_r($wwassignment, true));
 			if (!$wwassignment->cmidnumber) { // is this ever needed?
 				$wwassignment->cmidnumber =_wwassignment_cmid() ;
 			}
              $wwassignment->timemodified  = $wwmodtimes[$wwassignment->id];
              if ($wwassignment->timemodified > $lastcron) {
             	debugLog("instance needs update.  timemodified ".$wwassignment->timemodified.
             	     ", lastcron $lastcron, course id ".$wwassignment->course.",\n wwassignment id ".$wwassignment->id.
             	     ", set name ".$wwassignment->name.", cm.id ".$wwassignment->wwinstanceid. 
             	     ", grade ".$wwassignment->grade);
              	if (1) { //FIXME this should check something
              		wwassignment_update_grades($wwassignment);
              	    debugLog("update entire set ".print_r($wwassignment, true ) );
 					
 				} else {
 				   debugLog("do wwassignment_grade_item_update"  );
 				   wwassignment_grade_item_update($wwassignment);
 	
 				}
 				   
// 				// refresh events for this assignment
 				_wwassignment_refresh_event($wwassignment);
// 				
              } else {  // ?? shouldn't every record with id in $usql need an  update? why the extra check.
             	debugLog("no update needed.  timemodified ".$wwassignment->timemodified.
              	 ", lastcron $lastcron, course id ".$wwassignment->course.", wwassignment id ".$wwassignment->id.
              	", set name ".$wwassignment->name.", cm.id ".$wwassignment->wwinstanceid);
              }
 
 		}
 		$rs->close();
 	}
	traceLog("-----------------End _wwassignment_update_dirty_sets---------------");
	return(true);
}




function _wwassignment_refresh_event($wwassignment) {
        global $DB;
    traceLog("----------------Begin _wwassignment_refresh_event-------------");
	$cid = $wwassignment->course;
	$wwcoursename = _wwassignment_mapped_course($cid,false); 
	if ( $wwcoursename== -1) {
		error_log("Can't connect course $cid to webwork");
		return false;
	}
	$wwclient = new wwassignment_client();   
	$wwsetname = $wwassignment->webwork_set;
	traceLog("updating events for course: $wwcoursename assignment: $wwsetname");
	//get data from WeBWorK
	$wwsetdata = $wwclient->get_assignment_data($wwcoursename,$wwsetname,false);
	$wwassignment->grade = $wwclient->get_max_grade($wwcoursename,$wwsetname,false);
	$wwassignment->timemodified = time();
	$returnid = $DB->update_record('wwassignment',$wwassignment);
	// update event
	_wwassignment_delete_events($wwassignment->id);
	_wwassignment_create_events($wwassignment,$wwsetdata);
	traceLog("----------------End _wwassignment_refresh_event-------------");
	return true;
}


//////////////////////////////////////////////////////////////////
//Functions that ensure creation of WeBWorK Data
//////////////////////////////////////////////////////////////////

/**
* @desc Checks whether a user exists in a WW course. If it doesnt creates the user using the currently logged in one.
* @param $wwcoursename string The WW course.
* @param $username string The username to check.
* @param $permission string The permission the user needs if created.
* @return string the new username.
*/
function _wwassignment_mapcreate_user($wwcoursename,$username,$permission = '0') {
    $wwclient = new wwassignment_client();
    $exists = $wwclient->mapped_user($wwcoursename,$username);
    if($exists == -1) {
        global $USER;
        $tempuser = $USER;
        $newusername = $wwclient->create_user($wwcoursename,$tempuser,$permission);
        return $newusername;
    }
    return $username;
}

/**
* @desc Checks whether a set exists for a user in a WW course. If it doesnt autocreates.
* @param $wwcoursename string The WW course.
* @param $wwusername string The WW user.
* @param $wwsetname string The WW set.
* @return integer 0.
*/
function _wwassignment_mapcreate_user_set($wwcoursename,$wwusername,$wwsetname) {
    $wwclient = new wwassignment_client();
    $exists = $wwclient->mapped_user_set($wwcoursename,$wwusername,$wwsetname);
    if($exists == -1) {
        $wwclient->create_user_set($wwcoursename,$wwusername,$wwsetname);
    }
    return 0;
}

/**
* @desc Makes sure that a user is logged in to WW.
* @param $wwcoursename string The course to login to.
* @param $wwusername string The user to login.
* @return string The users key for WW.
*/
function _wwassignment_login_user($wwcoursename,$wwusername) {
    $wwclient = new wwassignment_client();
    return $wwclient->login_user($wwcoursename,$wwusername,false);
}

////////////////////////////////////////////////////////////////
//functions that check mapping existance in the local db
////////////////////////////////////////////////////////////////

/**
@desc Find the id of the wwassignment module class
*@param none
*@return  id
*/
function _wwassignment_cmid() {
   global $DB;
   $wwassignment_module = $DB->get_record('modules', array( 'name'=>'wwassignment' ));
   return $wwassignment_module->id;
}

/**
* @desc Finds the webwork course name from a moodle course id.
* @param integer $courseid Moodle Course ID.
* @param integer $silent whether to trigger an error message.
* @return string the name of the webwork course on success and -1 on failure.
*/
function _wwassignment_mapped_course($courseid,$silent = true) {
    global $DB;
    $blockinstance = $DB->get_record('block_instances', array(
               'blockname'=>'wwlink',
	       'parentcontextid'=>context_course::instance($courseid)->id, 
 	       'pagetypepattern'=>'course-view-*' ));    
    //error_log("block instance".print_r($blockinstance,true));
    $block_config = unserialize(base64_decode($blockinstance->configdata));
    //error_log("config_data ".print_r($block_config,true));
    if ( isset($block_config) &&  isset($block_config->wwlink_id)  ) {
    	return $block_config->wwlink_id;
    } else {
    	return -1;
    }
}

/**
* @desc Finds the webwork set name from a wwassignment id.
* @param integer $wwassignmentid Moodle wwassignment ID.
* @param integer $silent whether to trigger an error message.
* @return string the name of the webwork set on success and -1 on failure.
*/
function _wwassignment_mapped_set($wwassignmentid,$silent = true) {
    global $DB;
    $wwassignment = $DB->get_record('wwassignment', array('id'=>$wwassignmentid ));
    if((isset($wwassignment)) && (isset($wwassignment->webwork_set))) {
        return $wwassignment->webwork_set;
    }
    if(!$silent) {
        print_error('webwork_set_map_failure','wwassignment');
    }
    return -1;
}

////////////////////////////////////////////////////////////////
//functions that create links to the webwork site.
////////////////////////////////////////////////////////////////

/**
* @desc Returns URL link to a webwork course logging the user in.
* @param string $webworkcourse The webwork course.
* @param string $webworkset The webwork set.
* @param string $webworkuser The webwork user.
* @param string $key The key used to login the user.
* @return URL.
*/
function _wwassignment_link_to_edit_set_auto_login($webworkcourse,$webworkset,$username,$key) {
    return _wwassignment_link_to_course($webworkcourse) . "instructor/sets/$webworkset/?effectiveUser=$username&user=$username&key=$key";
}


/**
* @desc Returns URL link to a webwork course logging the user in.
* @param string $webworkcourse The webwork course.
* @param string $webworkuser The webwork user.
* @param string $key The key used to login the user.
* @return URL.
*/
function _wwassignment_link_to_instructor_auto_login($webworkcourse,$username,$key) {
    return _wwassignment_link_to_course($webworkcourse) . "instructor/?effectiveUser=$username&user=$username&key=$key";
}

/**
* @desc Returns the URL link to a webwork course and a particular set logged in.
* @param string $webworkcourse The webwork course.
* @param string $webworkset The webwork set.
* @param string $webworkuser The webwork user.
* @param string $key The key used to login the user.
* @return URL.
*/
function _wwassignment_link_to_set_auto_login($webworkcourse,$webworkset,$webworkuser,$key) {
    return _wwassignment_link_to_set($webworkcourse,$webworkset) .  "?effectiveUser=$webworkuser&user=$webworkuser&key=$key";
}

/**
* @desc Returns the URL link to a webwork course and a particular set.
* @param string $webworkcourse The webwork course.
* @param string $webworkset The webwork set.
* @return URL.
*/
function _wwassignment_link_to_set($webworkcourse,$webworkset) {
    return _wwassignment_link_to_course($webworkcourse) . "$webworkset/";
}

/**
* @desc Returns the URL link to a webwork course.
* @param string $webworkcourse The webwork course.
* @return URL.
*/
function _wwassignment_link_to_course($webworkcourse) {
    global $CFG;
    return $CFG->wwassignment_webworkurl."/$webworkcourse/";
}


///////////////////////////////////////////////////////////////
//wwassignment client class
///////////////////////////////////////////////////////////////

/**
* @desc This singleton class acts as the gateway for all communication from the Moodle Client to the WeBWorK SOAP Server.
* It encapsulates an instance of a SoapClient.
*/
class wwassignment_client {
        var $client;
        var $defaultparams;
        var $datacache;
        var $mappingcache;
        
        /**
         * @desc Constructs a singleton webwork_client.
         */
        function wwassignment_client()
        {
            global $CFG;
            // static associative array containing the real objects, key is classname
            static $instances=array();
            // get classname
            $class = get_class($this);
            if (!array_key_exists($class, $instances)) {
                // does not yet exist, save in array
#                $this->client = new SoapClient($CFG->wwassignment_rpc_wsdl,'wsdl');
                $this->client = soap_connect($CFG->wwassignment_rpc_wsdl);
#                $err = $this->client->getError();
#                if ($err) {
#                    error_log($err);
#                    error_log($CFG->wwassignment_rpc_wsdl);
#                    print_error('construction_error','wwassignment');
#                }
                $this->defaultparams = array();
                $this->defaultparams['authenKey']  = $CFG->wwassignment_rpc_key;
                $this->datacache = array(); 
                $this->mappingcache = array();
                $instances[$class] = $this;
                
            }
            foreach (get_class_vars($class) as $var => $value) {
                $this->$var =& $instances[$class]->$var;
            }
        }   
         
        /**
         *@desc Calls a SOAP function and passes (authenkey,course) automatically in the parameter list.
         *@param string $functioncall The function to call
         *@param array $params The parameters to the function.
         *@param integer $override=false whether to override the default parameters that are passed to the soap function (authenKey).
         *@return Result of the soap function.
         */
        function handler($functioncall,$params=array(),$override=false) {
                if(!is_array($params)) {
                        $params = array($params);   
                }
                if(!$override) {
                        $params = array_merge($this->defaultparams,$params);
                }
                if(WWASSIGNMENT_DEBUG) {
                    echo "Handler called: $functioncall <br>";
                    echo "Params: ";
                    var_dump($params);
                    echo "<br>"; 
                }
                $result = $this->client->__soapCall($functioncall,$params);
                debugLog("result is ".print_r($result, true));
                // FIXME what does call_user_func array do?);
//                 $result = call_user_func_array(array(&$this->client,$functioncall),$params);
//                 if($err = $this->client->getError()) {
//                         //print_error(get_string("rpc_fault","wwassignment') . " " . $functioncall. " ". $err);
//                         print_error('rpc_error','wwassignment');  
//                 }
                return $result;
        }
        
        /**
        * @desc Checks whether a user is in a webwork course.
        * @param string $webworkcourse The webwork course name.
        * @param string $webworkuser The webwork user name.
        * @param integer $silent whether to trigger an error message
        * @return string Returns the webwork user on success and -1 on failure.
        */
        function mapped_user($webworkcourse,$webworkuser,$silent = true) {
            if(isset($this->mappingcache[$webworkcourse]['user'][$webworkuser])) {
                return $this->mappingcache[$webworkcourse]['user'][$webworkuser];
            }
            $record = $this->handler('get_user',array('courseName' => $webworkcourse,'userID' => $webworkuser));
            if( is_a($record,'stdClass') ) {
                $this->mappingcache[$webworkcourse]['user'][$webworkuser] = $webworkuser;
                return $webworkuser;
            }
            if(!$silent) {
                print_error('webwork_user_map_failure',"wwassignment");
            }
            return -1;
        }
        
        /**
        * @desc Checks whether a user has his own copy of a set built in a webwork course.
        * @param string $webworkcourse The webwork course name.
        * @param string $webworkuser The webwork user name.
        * @param string $webworkset The webwork set name.
        * @param integer $silent whether to trigger an error message
        * @return integer Returns 1 on success and -1 on failure.
        */
        function mapped_user_set($webworkcourse,$webworkuser,$webworkset,$silent = true) {
            if(isset($this->mappingcache[$webworkcourse]['user_set'][$webworkuser][$webworkset])) {
                return $this->mappingcache[$webworkcourse]['user_set'][$webworkuser][$webworkset];
            }
            $record = $this->handler('get_user_set',array('courseName' => $webworkcourse,'userID' => $webworkuser,'setID' => $webworkset));
            if(is_a($record,'stdClass')) {
                $this->mappingcache[$webworkcourse]['user_set'][$webworkuser][$webworkset] = 1;
                return 1;
            }
            
            if(!$silent) {
                print_error('webwork_user_set_map_failure','wwassignment');
            }
            return -1;
        }
        
        /**
        * @desc Gets the record of the global set for a webwork course and set name.
        * @param string $webworkcourse The webwork course name.
        * @param string $webworkset The webwork set name.
        * @param integer $silent whether to trigger an error message
        * @return array Returns set information on success or -1 on failure.
        */
        function get_assignment_data($webworkcourse,$webworkset,$silent = true) {
            $record = $this->handler('get_global_set',array('courseName' => $webworkcourse, 'setID' => $webworkset));
            if(isset($record)) {
                $setinfo = array();
                $setinfo['open_date'] = $record->open_date;
                $setinfo['due_date'] = $record->due_date;
                $setinfo['set_id'] = $record->set_id;
                $setinfo['name'] = $record->set_id;
                return $setinfo;
            }
            if(!$silent) {
                print_error('webwork_set_map_failure','wwassignment');
            }
            return -1;
            
        }
        
        /**
        * @desc Gets all the user problems for a specfic course, user and set. 
        * @param string $webworkcourse The webwork course name.
        * @param string $webworkuser The webwork users name.
        * @param string $webworkset The webwork set name.
        * @param integer $silent whether to trigger an error message
        * @return array Returns an array of problems on success or -1 on failure.
        */
        function get_user_problems($webworkcourse,$webworkuser,$webworkset,$silent = true) {
            $record = $this->handler('get_all_user_problems',array('courseName' => $webworkcourse,'userID' => $webworkuser,'setID' => $webworkset));
            if(isset($record)) {
                return $record;
            }
            if(!$silent) {
                print_error('webwork_user_set_map_failure','wwassignment');
            }
            return -1;
        }
        
        /**
        * @desc Calculates the max grade on a set by counting the number of problems in the set. 
        * @param string $webworkcourse The webwork course name.
        * @param string $webworkset The webwork set name.
        * @param integer $silent whether to trigger an error message
        * @return integer The max grade on success or -1 on failure.
        */
        //FIXME -- this assumes each problem gets 1 point which is false
//         function get_max_grade($webworkcourse,$webworkset,$silent = true) {
//             $record = $this->handler('list_global_problems',array('courseName' => $webworkcourse,'setID' => $webworkset));
//             if(isset($record)) {
//                 return count($record);
//             }
//             if(!$silent) {
//                 print_error('webwork_set_map_failure','wwassignment');
//             }
//             return -1;
//             
//         }

        function get_max_grade($webworkcourse,$webworkset,$silent = true) {
            $record = $this->handler('get_all_global_problems',array('courseName' => $webworkcourse,'setID' => $webworkset));
            $totalpoints =0;
            if(isset($record)) {
            	foreach ($record as $set) {
            		$totalpoints = $totalpoints + $set->value;
                	
                }
                return $totalpoints;
            }
            if(!$silent) {
                print_error('webwork_set_map_failure','wwassignment');
            }
            return -1;
            
        }
        /**
        * @desc Forces a login of a user into a course.
        * @param string $webworkcourse The webwork course name.
        * @param string $webworkuser The webwork users name.
        * @param integer $silent whether to trigger an error message
        * @return string The webwork key for URL on success or -1 on failure.
        */
        function login_user($webworkcourse,$webworkuser,$silent = true) {
            $key = $this->handler('login_user',array('courseName' => $webworkcourse,'userID' => $webworkuser));
            if(isset($key)) {
                return $key;
            }
            if(!$silent) {
                print_error('webwork_user_map_failure','wwassignment');
            }
            return -1;
        }
        
        /**
        * @desc Retrieves a list of sets from a webwork course and converts it into form options format.
        * @param string $webworkcourse The webwork course name.
        * @param integer $silent whether to trigger an error message
        * @return array The form options.
        */
        function options_set($webworkcourse,$silent = true) {
            $setlist = $this->handler('list_global_sets',array('courseName' => $webworkcourse));
            if(isset($setlist)) {
                $setoptions = array();
                foreach($setlist as $setid) {

                    $setoptions[$setid] = $setid;
                }
                return $setoptions;
            }
            if(!$silent) {
                print_error('webwork_course_map_failure','wwassignment');
            }
            return -1;          
        }
        
        /**
        * @desc Retrieves a list of courses from a webwork course and converts it into form options format.
        * @param integer $silent whether to trigger an error message
        * @return array The form options.
        */
        function options_course($silent = true) {
            $courselist = $this->handler('list_courses');
            sort($courselist);
            if(isset($courselist)) {
                $courseoptions = array();
                foreach($courselist as $course) {
                    $courseoptions[$course] = $course;
                }
                return $courseoptions; 
            }
            if(!$silent) {
                print_error('webwork_course_list_map_failure','wwassignment');
            }
            return -1;
   
        }
        
        /**
        * @desc Creates a user in the WeBWorK course.
        * @param string $webworkcourse The webwork course name.
        * @param array $userdata The user data to use in creation.
        * @param string $permission The permissions of the new user, defaults to 0.
        * @return Returns username on success.
        
        */
        function create_user($webworkcourse,&$userdata,$permission='0') {
            $studentid = $userid;
            # FIXME:  find permission for this user and set permissions appropriately in webwork
            # FIXME:  find the group(s)  that this person is a member of 
            # FIXME:  I have used the following scheme:  gage_SEC  use groups ending like this to determine sections in webwork
            # FIXME:  use ordinary groups   taName    to correspond to recitation sections in WeBWorK
            #
            # FIXME:  make it so an update_user function is called whenever the user data in moodle is changed
            # FIXME:  so if a student switches groups this is reflected in WeBWorK
            $this->handler('add_user',array('courseName' => $webworkcourse, 'record' => array(
                'user_id' => $userdata->username,
                'first_name' => $userdata->firstname,
                'last_name' => $userdata->lastname,
                'email_address' => $userdata->email,
                'student_id' => $studentid,
                'status' => 'C',
                'section' => '',
                'recitation' => '',
                'comment' => 'moodle created user')));
            $this->handler('add_permission',array('courseName' => $webworkcourse,'record' => array(
                'user_id' => $userdata->username,
                'permission' => $permission)));
            $this->handler('add_password',array('courseName' => $webworkcourse,'record' => array(
                'user_id' => $userdata->username,
                'password' => $userdata->password)));
            return $userdata->username;
        }
       /**  NOT yet ready!!!!!!!!!
        * @desc Updates data for a user in the WeBWorK course.
        * @param string $webworkcourse The webwork course name.
        * @param array $userdata The user data to use in creation.
        * @param string $permission The permissions of the new user, defaults to 0.
        * @return Returns username on success.
        */
        function update_user($webworkcourse,&$userdata,$permission='0') {
            error_log("update_user called -- not yet ready");
            $studentid = $userid;
            # FIXME:  find permission for this user and set permissions appropriately in webwork
            # FIXME:  find the group(s)  that this person is a member of 
            # FIXME:  I have used the following scheme:  gage_SEC  use groups ending like this to determine sections in webwork
            # FIXME:  use ordinary groups   taName    to correspond to recitation sections in WeBWorK
            #
            # FIXME:  make it so an update_user function is called whenever the user data in moodle is changed
            # FIXME:  so if a student switches groups this is reflected in WeBWorK
            # do get_user first to get current status then update this??
            $this->handler('put_user',array('courseName' => $webworkcourse, 'record' => array(
                //'user_id' => $userdata->username,  // can't update this
                'first_name' => $userdata->firstname,
                'last_name' => $userdata->lastname,
                'email_address' => $userdata->email,
                'student_id' => $studentid,
                //'status' => 'C',  //can you update this from moodle?
                'section' => '',
                'recitation' => '',
                'comment' => 'moodle updated user')));
            $this->handler('add_permission',array('courseName' => $webworkcourse,'record' => array(
                'user_id' => $userdata->username,
                'permission' => $permission)));
            $this->handler('add_password',array('courseName' => $webworkcourse,'record' => array(
                'user_id' => $userdata->username,
                'password' => $userdata->password)));
            return $userdata->username;
        }
        /**
        * @desc Creates a user set in WeBWorK
        * @param string $webworkcourse The webwork course name.
        * @param string $webworkuser The webwork user name.
        * @param string $webworkset The webwork set name.
        * @return Returns 1 on success.
        */
        function create_user_set($webworkcourse,$webworkuser,$webworkset) {
            $this->handler('assign_set_to_user',array('courseName' => $webworkcourse,'userID' => $webworkuser, 'setID' => $webworkset));
            return 1;
        }
        
        /**
        * @desc Finds grades of many users for one set.
        * @param string $webworkcourse The webwork course name.
        * @param array $webworkusers A list of webwork users
        * @param string $webworkset The webwork set name
        * @return array Returns an array of grades   
        */
        function grade_users_sets($webworkcourse,$webworkusers,$webworkset) {
            return $this->handler('grade_users_sets',array('courseName' => $webworkcourse, 'userIDs' => $webworkusers, 'setID' => $webworkset));
        }
};
?>
