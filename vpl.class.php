<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * VPL class definition
 *
 * @package mod_vpl
 * @copyright 2013 onwards Juan Carlos Rodríguez-del-Pino
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Juan Carlos Rodríguez-del-Pino <jcrodriguez@dis.ulpgc.es>
 */

/**
 * Module instance files
 * path= vpl_data/vpl_instance#
 * General info
 * path/required_files.lst
 * path/required_files/
 * path/execution_files.lst
 * path/execution_files/
 * path/execution_files/vpl_run.sh
 * path/execution_files/vpl_debug.sh
 * path/execution_files/vpl_evaluate.sh
 *  * Submission info
 * path/usersdata/userid#/submissionid#/submissionfiles/
 * path/usersdata/userid#/submissionid#/grade_comments.txt
 * path/usersdata/userid#/submissionid#/teachertest.txt
 * path/usersdata/userid#/submissionid#/studenttest.txt
 */

require_once(dirname(__FILE__).'/filegroup.class.php');
require_once(dirname(__FILE__).'/lib.php');

class file_group_execution extends file_group_process {
    /**
     * Name of fixed file names
     *
     * @var string[]
     */
    protected static $basefiles = array (
            'vpl_run.sh',
            'vpl_debug.sh',
            'vpl_evaluate.sh',
            'vpl_evaluate.cases'
    );

    /**
     * Number of $basefiles elements
     *
     * @var int
     */
    protected static $numbasefiles;

    /**
     * Constructor
     *
     * @param string $filelistname
     * @param string $dir
     */
    public function __construct($filelistname, $dir) {
        self::$numbasefiles = count( self::$basefiles );
        parent::__construct( $filelistname, $dir, 1000, self::$numbasefiles );
    }

    /**
     * Get list of files
     *
     * @return string[]
     */
    public function getfilelist() {
        return array_values( array_unique( array_merge( self::$basefiles, parent::getfilelist() ) ) );
    }

    /**
     * Get the file comment by number
     *
     * @param int $num
     * @return string
     */
    public function getfilecomment($num) {
        if ($num < self::$numbasefiles) {
            return get_string( self::$basefiles [$num], VPL );
        } else {
            return get_string( 'file' ) . ' ' . ($num + 1 - self::$numbasefiles);
        }
    }

    /**
     * Get list of files to keep when running
     *
     * @return string[]
     */
    public function getfilekeeplist() {
        return vpl_read_list_from_file( $this->filelistname . '.keep' );
    }

    /**
     * Set the file list to keep when running
     *
     * @param string[] $filelist
     */
    public function setfilekeeplist($filelist) {
        vpl_write_list_to_file( $this->filelistname . '.keep', $filelist );
    }
}
class mod_vpl {
    /**
     * Internal var for course_module
     *
     * @var object $cm
     */
    protected $cm;

    /**
     * Internal var for course
     *
     * @var object $course
     */
    protected $course;

    /**
     * Internal var for vpl
     *
     * @var object $instance
     */
    protected $instance;

    /**
     * Internal var object to requied file group manager
     *
     * @var object of file group manager
     */
    protected $requiredfgm;

    /**
     * Internal var object to execution file group manager
     *
     * @var object of file group manager
     */
    protected $executionfgm;

    /**
     * Constructor
     *
     * @param $id int
     *            optional course_module id
     * @param $a int
     *            optional module instance id
     */
    public function __construct($id, $a = null) {
        global $OUTPUT;
        global $DB;
        if ($id) {
            if (! $this->cm = get_coursemodule_from_id( VPL, $id )) {
                print_error( 'invalidcoursemodule' );
            }
            if (! $this->course = $DB->get_record( "course", array (
                    "id" => $this->cm->course
            ) )) {
                print_error( 'unknowncourseidnumber', '', $this->cm->course );
            }
            if (! $this->instance = $DB->get_record( VPL, array (
                    "id" => $this->cm->instance
            ) )) {
                print_error( 'module instance id unknow' );
            }
        } else {
            if (! $this->instance = $DB->get_record( VPL, array (
                    "id" => $a
            ) )) {
                print_error( 'module instance id unknow' );
            }
            if (! $this->course = $DB->get_record( "course",
                    array (
                            "id" => $this->instance->course
                    ) )) {
                print_error( 'unknowncourseidnumber', '', $this->instance->course );
            }
            if (! $this->cm = get_coursemodule_from_instance( VPL, $this->instance->id, $this->course->id )) {
                echo $OUTPUT->box( get_string( 'invalidcoursemodule' ) . ' VPL id=' . $a );
                // Don't stop on error. This let delete a corrupted course.
            }
        }
        $this->requiredfgm = null;
        $this->executionfgm = null;
    }

    /**
     *
     * @return module db instance
     */
    public function get_instance() {
        return $this->instance;
    }

    /**
     *
     * @return course
     *
     */
    public function get_course() {
        return $this->course;
    }

    /**
     *
     * @return course_module
     *
     */
    public function get_course_module() {
        return $this->cm;
    }

    /**
     * Delete a vpl instance
     *
     * @return bool true if all OK
     */
    public function delete_all() {
        global $DB;
        // Delete all data files.
        vpl_delete_dir( $this->get_data_directory() );
        // Delete grade_item.
        vpl_delete_grade_item( $this->instance );
        // Delete event.
        $DB->delete_records( 'event',
                array (
                        'modulename' => VPL,
                        'instance' => $this->instance->id
                ) );
        // Delete all submissions records.
        $DB->delete_records( 'vpl_submissions', array (
                'vpl' => $this->instance->id
        ) );
        // Delete vpl record.
        $DB->delete_records( VPL, array (
                'id' => $this->instance->id
        ) );
        // Allways true.
        return true;
    }

    /**
     *
     * @return instance data directory
     */
    public function get_data_directory() {
        global $CFG;
        return $CFG->dataroot . '/vpl_data/' . $this->instance->id;
    }

    /**
     *
     * @return instance config data directory
     */
    public function get_users_data_directory() {
        return $this->get_data_directory() . '/usersdata';
    }

    /**
     *
     * @return directory to stored initial required files
     */
    public function get_required_files_directory() {
        return $this->get_data_directory() . '/required_files/';
    }

    /**
     *
     * @return filename to store required files
     */
    public function get_required_files_filename() {
        return $this->get_data_directory() . '/required_files.lst';
    }

    /**
     *
     * @return array of files required name
     */
    public function get_required_files() {
        return vpl_read_list_from_file( $this->get_required_files_filename() );
    }

    /**
     *
     * @param $files array
     *            of required files
     */
    public function set_required_files($files) {
        vpl_write_list_to_file( $this->get_required_files_filename(), $files );
    }

    /**
     *
     * @return object file group manager for required files
     */
    public function get_required_fgm() {
        if (! $this->requiredfgm) {
            $this->requiredfgm = new file_group_process( $this->get_required_files_filename()
                                                       , $this->get_required_files_directory()
                                                       , $this->instance->maxfiles );
        }
        return $this->requiredfgm;
    }

    /**
     *
     * @return directory to stored execution files
     */
    public function get_execution_files_directory() {
        return $this->get_data_directory() . '/execution_files/';
    }

    /**
     *
     * @return filename to store execution files
     */
    public function get_execution_files_filename() {
        return $this->get_data_directory() . '/execution_files.lst';
    }

    /**
     *
     * @return array of files execution name
     */
    public function get_execution_files() {
        return vpl_read_list_from_file( $this->get_execution_files_filename() );
    }

    /**
     *
     * @return object file group manager for execution files
     */
    public function get_execution_fgm() {
        if (! $this->executionfgm) {
            $this->executionfgm = new file_group_execution( $this->get_execution_files_filename(),
                    $this->get_execution_files_directory() );
        }
        return $this->executionfgm;
    }

    /**
     * Returns name of file storing list of output files.
     * @return string filename to store list of output files
     **/
    function get_output_files_filename(){
        return $this->get_data_directory().'/output_files.lst';
    }

    /**
     * Returns names of output files.
     * @return array names of output files
     **/
    function get_output_files(){
        return vpl_read_list_from_file($this->get_output_files_filename());
    }

    /**
     * Sets names of output files.
     * @param $files array list of filenames of output files.
     */
    function set_output_files($files) {
        vpl_write_list_to_file($this->get_output_files_filename(), $files);
    }

    // FIXME check and remove function.
    public function set_initial_file($name, $files) {
        $filelist = '';
        $basepath = $this->get_submission_directory();
        foreach ($files as $file) {
            $name = basename( $file ['name'] );
            if ($name > '') {
                if ($filelist > '') {
                    $filelist .= "\n";
                }
                $filelist .= $name;
                $fp = vpl_fopen( $basepath . $name );
                fwrite( $fp, $file ['data'] );
                fclose( $fp );
            }
        }
        $fp = vpl_fopen( $this->get_submissionfilelistname() );
        fwrite( $fp, $filelist );
        fclose( $fp );
    }

    /**
     * get instance name with groupping name if available
     *
     * @return string with name+(grouping name)
     */
    public function get_printable_name() {
        global $CFG;
        $ret = $this->instance->name;
        if (! empty( $CFG->enablegroupings ) && ($this->cm->groupingid > 0)) {
            $grouping = groups_get_grouping( $this->cm->groupingid );
            if ($grouping !== false) {
                $ret .= ' (' . $grouping->name . ')';
            }
        }
        return $ret;
    }

    /**
     * get fulldescription
     *
     * @return fulldescription
     *
     */
    public function get_fulldescription() {
        $instance = $this->get_instance();
        if ($instance->intro) {
            return format_module_intro( VPL, $this->get_instance(), $this->get_course_module()->id );
        } else {
            return '';
        }
    }

    /**
     * get fulldescription adding basedon
     *
     * @return fulldescription
     *
     */
    public function get_fulldescription_with_basedon() {
        $ret = '';
        if ($this->instance->basedon) { // Show recursive varaitions.
            $basevpl = new mod_vpl( false, $this->instance->basedon );
            $ret .= $basevpl->get_fulldescription_with_basedon();
        }
        return $ret . $this->get_fulldescription();
    }
    /**
     * Return maximum file size allowed
     *
     * @return int
     *
     */
    public function get_maxfilesize() {
        $plugincfg = get_config('mod_vpl');
        $max = vpl_get_max_post_size();
        if ($plugincfg->maxfilesize > 0 && $plugincfg->maxfilesize < $max) {
            $max = $plugincfg->maxfilesize;
        }
        if ($this->instance->maxfilesize > 0 && $this->instance->maxfilesize < $max) {
            $max = $this->instance->maxfilesize;
        }
        return $max;
    }

    /**
     * Get grading information help
     *
     * @return string grade comments summary in html format
     */
    public function get_grading_help() {
        $list = array ();
        $submissions = $this->all_last_user_submission();
        foreach ($submissions as $submission) {
            $sub = new mod_vpl_submission( $this, $submission );
            $sub->filter_feedback( $list );
        }
        // TODO show evaluation criteria with show hidde button.
        $all = array ();
        foreach ($list as $text => $info) {
            $astext = s( addslashes_js( $text ) );
            $html = '';
            $html .= s( $text );
            foreach ($info->grades as $grade => $nothing) {
                if ($grade >= 0) { // No grade.
                    $jscript = 'VPL.addComment(\'' . $astext . '\')';
                } else {
                    $jscript = 'VPL.addComment(\'' . $astext . ' (' . $grade . ')\')';
                }
                $link = '<a href="javascript:void(0)" onclick="' . $jscript . '">' . $grade . '</a>';
                $html .= ' (' . $link . ')';
            }
            $html .= '<br />' . "\n";
            if (isset( $all [$info->count] )) {
                $all [$info->count] .= '(' . $info->count . ') ' . $html;
            } else {
                $all [$info->count] = '(' . $info->count . ') ' . $html;
            }
        }
        // Sort comments by number of occurrences.
        krsort( $all );
        $html = '';
        foreach ($all as $count => $info) {
            $html .= $info;
        }
        // TODO show info about others review with show hidde button.
        return $html;
    }
    /**
     * Get password
     */
    public function get_password() {
        return trim( $this->instance->password );
    }

    /**
     * Get password md5
     */
    public function get_password_md5() {
        return md5( $this->instance->id . (sesskey()) );
    }

    /**
     * Check if pass password restriction
     */
    public function pass_password_check($passset = '') {
        $password = $this->get_password();
        if ($password > '') {
            global $SESSION;
            $passwordmd5 = $this->get_password_md5();
            $pasvar = 'vpl_password_' . $this->instance->id;
            if (isset( $SESSION->$pasvar ) && $SESSION->$pasvar == $passwordmd5) {
                return true;
            }
            if ($passset == '') {
                $passset = optional_param( 'password', '', PARAM_TEXT );
            }
            if ($passset > '') {
                if ($passset == $password) {
                    $SESSION->$pasvar = $passwordmd5;
                    unset( $SESSION->vpl_attempt_number );
                    return true;
                }
                if (isset( $SESSION->vpl_attempt_number )) {
                    $SESSION->vpl_attempt_number ++;
                } else {
                    $SESSION->vpl_attempt_number = 1;
                }
                // Wait vpl_attempt_number seg to limit force brute crack.
                sleep( $SESSION->vpl_attempt_number );
            }
            return false;
        }
        return true;
    }

    /**
     * Check password restriction
     */
    public function password_check() {
        if (! $this->pass_password_check()) {
            require_once('forms/password_form.php');
            $this->print_header();
            $mform = new mod_vpl_password_form( $_SERVER ['SCRIPT_NAME'] );
            $mform->display();
            $this->print_footer();
            die();
        }
    }

    /**
     * Check network restriction and return true o false
     * @param $netreq Network field may be a combination of ip, netmask and domain comma separated to check.
     * @param $ip the IP to check
     * @return boolean
     */
    public function pass_network_check($netreq = null, $ip = null) {
        if ($netreq === null) {
            $netreq = $this->instance->requirednet;
        }
        $netreq = trim( $netreq );
        if ($netreq == '') { // No net required.
            return true;
        }
        if ($ip === null) {
            $ip = getremoteaddr();
        }
        return address_in_subnet( $ip, $netreq );
    }

    /**
     * Check netword restriction and show error if not passed
     */
    public function network_check() {
        global $OUTPUT;
        if (! $this->pass_network_check()) {
            $this->print_header();
            echo $OUTPUT->box( get_string( 'opnotallowfromclient', VPL ) . ' ' . getremoteaddr() );
            $this->print_footer();
            die();
        }
    }

    /**
     * Check submission restriction
     *
     * @param $data Object
     *            with submitted data
     * @param & $error
     *            string
     * @return bool
     *
     */
    public function pass_submission_restriction(& $alldata, & $error) {
        $max = $this->get_maxfilesize();
        $rfn = $this->get_required_fgm();
        $list = $rfn->getFilelist();
        $error = '';
        if (count( $alldata ) > $this->instance->maxfiles) {
            $error .= get_string( 'maxfilesexceeded', VPL ) . "\n";
        }
        $lr = count( $list );
        $lad = count( $alldata );
        $i = 0;
        foreach ($alldata as $name => $data) {
            if (strlen( $data ) > $max) {
                $error .= '"' . s( $name ) . '" ' . get_string( 'maxfilesizeexceeded', VPL ) . "<br />";
            }
            if (! vpl_is_valid_path_name( $name )) {
                $error .= '"' . s( $name ) . '" ' . get_string( 'incorrect_file_name', VPL ) . "<br />";
            }
            if ($i < $lr && $list [$i] != $name) {
                $a = new stdClass();
                $a->expected = $list [$i];
                $a->found = $name;
                $error .= s( get_string( 'unexpected_file_name', VPL, $a ) ) . "<br />";
            }
            $i++;
        }
        return strlen( $error ) == 0;
    }

    /**
     * Check and submission
     *
     * @param
     *            $userid
     * @param $data Object
     *            with submitted data
     * @param & $error
     *            string
     * @return false or submission id
     */
    public function add_submission($userid, & $files, $comments, & $error) {
        global $USER, $DB;
        if (! $this->pass_submission_restriction( $files, $error )) {
            return false;
        }
        if ($this->is_group_activity()) {
            $liderid = $this->get_group_leaderid( $userid );
            if ($liderid == 0) { // No group or inconsistence.
                if ($this->has_capability( VPL_MANAGE_CAPABILITY ) || ($this->has_capability(
                        VPL_GRADE_CAPABILITY ) && ($userid == $USER->id))) {
                    // Is manager or grader own submission.
                    $liderid = $userid;
                } else {
                    $error = get_string( 'notsaved', VPL ) . "\n" . get_string( 'inconsistentgroup', VPL );
                    return false;
                }
            }
            $userid = $liderid;
        }
        // Grader submmission or group activity.
        if ($USER->id != $userid || $this->is_group_activity()) {
            $user = $DB->get_record( 'user', array (
                    'id' => $USER->id
            ) );
            $submittedby = get_string( 'submittedby', VPL, fullname( $user ) ) . "\n";
        } else {
            $submittedby = '';
        }
        if (($lastsubins = $this->last_user_submission( $userid )) !== false) {
            $lastsub = new mod_vpl_submission( $this, $lastsubins );
            if ($lastsub->is_equal_to( $files, $submittedby . $comments )) {
                return $lastsubins->id;
            }
        }
        ignore_user_abort( true );
        // Create submission record.
        $submissiondata = new stdClass();
        $submissiondata->vpl = $this->get_instance()->id;
        $submissiondata->userid = $userid;
        $submissiondata->datesubmitted = time();
        $submissiondata->comments = $submittedby . $comments;
        $submissionid = $DB->insert_record( 'vpl_submissions', $submissiondata, true );
        if (! $submissionid) {
            $error = get_string( 'notsaved', VPL ) . "\ninserting vpl_submissions record";
            return false;
        }
        // Save files.
        $submission = new mod_vpl_submission( $this, $submissionid );
        $submission->set_submitted_file( $files );
        $submission->remove_grade();
        // If no submitted by grader and not group activity, remove near submmissions.
        if ($submittedby == '') {
            $this->delete_overflow_submissions( $userid );
        }
        return $submissionid;
    }

    /**
     * Get user submissions, order reverse submission id
     *
     * @param $id user
     *            id
     * @return FALSE/array of objects
     */
    public function user_submissions($userid) {
        global $DB;
        if ($this->is_group_activity()) {
            $userid = $this->get_group_leaderid( $userid );
            if ($userid == 0) {
                return array ();
            }
        }
        $select = '(userid = ?) AND (vpl = ?)';
        $parms = array (
                $userid,
                $this->instance->id
        );
        return $DB->get_records_select( 'vpl_submissions', $select, $parms, 'id DESC' );
    }

    /**
     * Get all last usersubmission
     *
     * @param $fields fields
     *            to retrieve from submissions table, default s.*. userid is always retrieved
     * @return object array
     */
    public function all_last_user_submission($fields = 's.*') {
        // Get last submissions records for this vpl module.
        global $DB;
        $id = $this->get_instance()->id;
        $query = "SELECT s.userid, $fields FROM {vpl_submissions} AS s";
        $query .= ' inner join ';
        $query .= ' (SELECT max(id) as maxid FROM {vpl_submissions} ';
        $query .= '  WHERE {vpl_submissions}.vpl=? ';
        $query .= '  GROUP BY {vpl_submissions}.userid) as ls';
        $query .= ' on s.id = ls.maxid';
        $parms = array (
                $id
        );
        return $DB->get_records_sql( $query, $parms );
    }

    /**
     * Get number of user submissions
     *
     * @return oject array
     */
    public function get_submissions_number() {
        global $DB;
        $query = 'SELECT userid, COUNT(*) as submissions FROM {vpl_submissions}';
        $query .= ' WHERE {vpl_submissions}.vpl=?';
        $query .= ' GROUP BY {vpl_submissions}.userid';
        $parms = array (
                $this->get_instance()->id
        );
        return $DB->get_records_sql( $query, $parms );
    }

    /**
     * Get last usersubmission
     *
     * @param $id user
     *            id
     * @return FALSE/object
     *
     */
    public function last_user_submission($userid) {
        global $DB;
        if ($this->is_group_activity()) {
            $userid = $this->get_group_leaderid( $userid );
            if ($userid == 0) {
                return false;
            }
        }
        $select = "(userid = ?) AND (vpl = ?)";
        $params = array (
                $userid,
                $this->instance->id
        );
        $res = $DB->get_records_select( 'vpl_submissions', $select, $params, 'id DESC', '*', 0, 1 );
        foreach ($res as $sub) {
            return $sub;
        }
        return false;
    }
    protected static $context = array ();
    /**
     * Return context object for this module instance
     *
     * @return object
     */
    public function get_context() {
        if (! isset( self::$context [$this->cm->id] )) {
            self::$context [$this->cm->id] = context_module::instance( $this->cm->id );
        }
        return self::$context [$this->cm->id];
    }

    /**
     * Requiere the current user has the capability of performing $capability in this module instance
     *
     * @param string $capability
     *            capability name
     * @param bool $alert
     *            if true show a JavaScript alert message
     * @return void
     */
    public function require_capability($capability, $alert = false) {
        if ($alert && ! ($this->has_capability( $capability ))) {
            global $OUTPUT;
            echo $OUTPUT->header();
            vpl_js_alert( get_string( 'notavailable' ) );
        }
        require_capability( $capability, $this->get_context() );
    }

    /**
     * Check if the user has the capability of performing $capability in this module instance
     *
     * @param string $capability
     *            capability name
     * @param int $userid
     *            default null => current user
     * @return bool
     */
    public function has_capability($capability, $userid = null) {
        return has_capability( $capability, $this->get_context(), $userid );
    }

    /**
     * Delete overflow submissions. If three submissions within the period central is delete
     *
     * @param
     *            $userid
     * @return void
     *
     */
    public function delete_overflow_submissions($userid) {
        global $DB;
        $plugincfg = get_config('mod_vpl');
        if (! isset( $plugincfg->discard_submission_period )) {
            return;
        }
        if ($plugincfg->discard_submission_period == 0) {
            // Keep all submissions.
            return;
        }
        if ($plugincfg->discard_submission_period > 0) {
            $select = "(userid = ?) AND (vpl = ?)";
            $params = array (
                    $userid,
                    $this->instance->id
            );
            $res = $DB->get_records_select( VPL_SUBMISSIONS, $select, $params, 'id DESC', '*', 0, 3 );
            if (count( $res ) == 3) {
                $i = 0;
                foreach ($res as $sub) {
                    switch ($i) {
                        case 0 :
                            $last = $sub;
                            break;
                        case 1 :
                            $second = $sub;
                            break;
                        case 2 :
                            $first = $sub;
                            break;
                    }
                    $i ++;
                }
                // Check time consistence.
                if (! ($last->datesubmitted > $second->datesubmitted && $second->datesubmitted > $first->datesubmitted)) {
                    return;
                }
                if (($last->datesubmitted - $first->datesubmitted) < $plugincfg->discard_submission_period) {
                    // Remove second submission.
                    $submission = new mod_vpl_submission( $this, $second );
                    $submission->delete();
                }
            }
        }
    }

    /**
     * Check if it is submission period
     *
     * @return bool
     *
     */
    public function is_submission_period() {
        $now = time();
        $ret = $this->instance->startdate <= $now;
        return $ret && ($this->instance->duedate == 0 || $this->instance->duedate >= $now);
    }

    /**
     * is visible this vpl instance
     *
     * @return bool
     */
    public function is_visible() {
        $cm = $this->get_course_module();
        $modinfo = get_fast_modinfo( $cm->course );
        $ret = true;
        $ret = $ret && $modinfo->get_cm( $cm->id )->uservisible;
        $ret = $ret && $this->has_capability( VPL_VIEW_CAPABILITY );
        // Grader and manager always view.
        $ret = $ret || $this->has_capability( VPL_GRADE_CAPABILITY );
        $ret = $ret || $this->has_capability( VPL_MANAGE_CAPABILITY );
        return $ret;
    }

    /**
     * this vpl instance admit submission
     *
     * @return bool
     */
    public function is_submit_able() {
        $cm = $this->get_course_module();
        $modinfo = get_fast_modinfo( $cm->course );
        $instance = $this->get_instance();
        $ret = true;
        $ret = $ret && $this->has_capability( VPL_SUBMIT_CAPABILITY );
        $ret = $ret && $this->is_submission_period();
        $ret = $ret && $modinfo->get_cm( $cm->id )->uservisible;
        // Manager can always submit.
        $ret = $ret || $this->has_capability( VPL_MANAGE_CAPABILITY );
        return $ret;
    }

    /**
     * is group activity
     *
     * @return bool
     */
    public function is_group_activity() {
        global $CFG;
        if (! isset( $this->group_activity )) {
            $cm = $this->get_course_module();
            $this->group_activity = $cm->groupingid > 0 && $this->get_instance()->worktype == 1;
            // TODO check groups_get_activity_groupmode($cm)==SEPARATEGROUPS.
        }
        return $this->group_activity;
    }

    /**
     *
     * @param
     *            user object return HTML code to show user picture
     * @return String
     */
    public function user_fullname_picture($user) {
        return $this->user_picture( $user ) . ' ' . $this->fullname( $user );
    }

    /**
     *
     * @param
     *            user object return HTML code to show user picture
     * @return String
     */
    public function user_picture($user) {
        global $OUTPUT;
        if ($this->is_group_activity()) {
            return print_group_picture( $this->get_usergroup( $user->id ), $this->get_course()->id, false, true );
        } else {
            return $OUTPUT->user_picture( $user );
        }
    }

    /**
     * return formated name of user or group
     *
     * @param
     *            user object
     * @param
     *            withlink boolean. if true and is group add link to group
     * @return String
     */
    public function fullname($user, $withlink = true) {
        if ($this->is_group_activity()) {
            $group = $this->get_usergroup( $user->id );
            if ($group !== false) {
                if ($withlink) {
                    $url = vpl_abs_href( '/user/index.php', 'id', $this->get_course()->id, 'group', $group->id );
                    return '<a href="' . $url . '">' . $group->name . '</a>';
                } else {
                    return $group->name;
                }
            }
            return '';
        } else {
            return fullname( $user );
        }
    }

    /**
     * Get array of graders for this activity and group (optional)
     *
     * @param $group optional
     *            parm with group to search for
     * @return array
     */
    public function get_graders($group = '') {
        if (! isset( $this->graders )) {
            $this->graders = get_users_by_capability( $this->get_context(), VPL_GRADE_CAPABILITY, user_picture::fields( 'u' ),
                    'u.lastname ASC', '', '', $group );
        }
        return $this->graders;
    }

    /**
     * Get array of students for this activity and group (optional) if is group activity return only group liders
     *
     * @param $group optional
     *            parm with group to search for
     * @return array
     */
    public function get_students($group = '') {
        if (! isset( $this->students )) {
            // Generate array of graders indexed.
            $nostudens = array ();
            foreach ($this->get_graders() as $user) {
                $nostudens [$user->id] = true;
            }
            $students = array ();
            $all = get_users_by_capability( $this->get_context(), VPL_SUBMIT_CAPABILITY, user_picture::fields( 'u' ),
                    'u.lastname ASC', '', '', $group );
            // TODO the following code is too slow.
            foreach ($all as $user) {
                if (! isset( $nostudens [$user->id] )) {
                    $students [] = $user;
                }
            }
            if (memory_get_usage( true ) > 50000000) { // Don't cache if low memory.
                return $students;
            }
            $this->students = $students;
        }
        return $this->students;
    }

    /**
     * Return if is group activity
     *
     * @return bool
     */
    public function is_inconsistent_user($current, $real) {
        if ($this->is_group_Activity()) {
            return $current != $this->get_group_leaderid( $real );
        } else {
            return $current != $real;
        }
    }

    /**
     * If is a group activity search for a group leader for the group of the userid (0 is not found)
     *
     * @return Integer userid
     */
    public function get_group_leaderid($userid) {
        $leaderid = 0;
        foreach ($this->get_usergroup_members( $userid ) as $user) {
            if ($user->id < $leaderid || $leaderid == 0) {
                $leaderid = $user->id;
            }
        }
        return $leaderid;
    }

    /**
     * If is a group activity return the group of the userid
     *
     * @return group object or false
     */
    public function get_usergroup($userid) {
        if ($this->is_group_activity()) {
            $courseid = $this->get_course()->id;
            $groupingid = $this->get_course_module()->groupingid;
            $groups = groups_get_all_groups( $courseid, $userid, $groupingid );
            if ($groups === false || count( $groups ) > 1) {
                return false;
            }
            return reset( $groups );
        }
        return false;
    }
    protected static $usergroupscache = array ();
    /**
     * If is a group activity return group members for the group of the userid
     *
     * @return Array of user objects
     */
    public function get_usergroup_members($userid) {
        $group = $this->get_usergroup( $userid );
        if ($group !== false) {
            if (! isset( self::$usergroupscache [$group->id] )) {
                self::$usergroupscache [$group->id] = groups_get_members( $group->id, 'u.id' );
            }
            return self::$usergroupscache [$group->id];
        }
        return array ();
    }

    /**
     * Return scale record if grade < 0
     *
     * @return Object or false
     */
    public function get_scale() {
        global $DB;
        if (! isset( $this->scale )) {
            if ($this->get_grade() < 0) {
                $gradeid = - $this->get_grade();
                $this->scale = $DB->get_record( 'scale', array (
                        'id' => $gradeid
                ) );
            } else {
                $this->scale = false;
            }
        }
        return $this->scale;
    }

    /**
     * Return grade info take from gradebook
     *
     * @return Object or false
     */
    public function get_grade_info() {
        global $DB, $CFG, $USER;
        if (! isset( $this->grade_info )) {
            $this->grade_info = false;
            if ($this->get_instance()->grade != 0) { // If 0 then NO GRADE.
                $userid = ($this->has_capability( VPL_GRADE_CAPABILITY ) || $this->has_capability(
                        VPL_MANAGE_CAPABILITY )) ? null : $USER->id;
                require_once($CFG->libdir . '/gradelib.php');
                $gradinginfo = grade_get_grades( $this->get_course()->id, 'mod', 'vpl', $this->get_instance()->id, $userid );
                foreach ($gradinginfo->items as $gi) {
                    $this->grade_info = $gi;
                }
            }
        }
        return $this->grade_info;
    }

    /**
     * Return visiblegrade from gradebook and for every user
     *
     * @return boolean
     */
    public function get_visiblegrade() {
        global $USER;
        if ($gi = $this->get_grade_info()) {
            if (is_array( $gi->grades )) {
                $usergi = reset( $gi->grades );
                return ! ($gi->hidden || (is_object( $usergi ) && $usergi->hidden));
            } else {
                return ! ($gi->hidden);
            }
        } else {
            return false;
        }
    }

    /**
     * Return grade (=0 => no grade, >0 max grade, <0 scaleid)
     *
     * @return int
     */
    public function get_grade() {
        return $this->instance->grade;
    }

    /**
     * print end of page
     */
    public function print_footer() {
        global $OUTPUT;
        $style = "float:right; right:10px; padding:8px; background-color: white;text-align:center;";
        echo '<div style="' . $style . '">';
        echo '<a href="http://vpl.dis.ulpgc.es/">';
        echo 'VPL '. vpl_get_version();
        echo '</a>';
        echo '</div>';
        echo $OUTPUT->footer();
    }

    /**
     * print end of page
     */
    public function print_footer_simple() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }

    /**
     * prepare_page initialy
     */
    public function prepare_page($url = false, $parms = array()) {
        global $PAGE;

        $PAGE->set_cm( $this->get_course_module(), $this->get_course(), $this->get_instance() );
        $PAGE->set_context( $this->get_context() );
        if ($url) {
            $PAGE->set_url( '/mod/vpl/' . $url, $parms );
        }
    }

    protected static $headerisout = false;
    public static function header_is_out() {
        return self::$headerisout;
    }
    /**
     * print header
     *
     * @param $info title
     *            and last nav option
     */
    public function print_header($info = '') {
        global $COURSE, $PAGE, $OUTPUT;
        if (self::$headerisout) {
            return;
        }
        $tittle = $this->get_printable_name();
        if ($info) {
            $tittle .= ' ' . $info;
        }
        $PAGE->set_title( $this->get_course()->fullname . ' ' . $tittle );
        $PAGE->set_pagelayout( 'incourse' );
        $PAGE->set_heading( $this->get_course()->fullname );
        echo $OUTPUT->header();
        self::$headerisout = true;
    }
    public function print_header_simple($info = '') {
        global $OUTPUT, $PAGE;
        if (self::$headerisout) {
            return;
        }
        $tittle = $this->get_printable_name();
        if ($info) {
            $tittle .= ' ' . $info;
        }
        $PAGE->set_title( $this->get_course()->fullname . ' ' . $tittle );
        $PAGE->set_pagelayout( 'popup' );
        echo $OUTPUT->header();
        self::$headerisout = true;
    }
    /**
     * Print heading action with help
     *
     * @param $action string
     *            base text and help
     */
    public function print_heading_with_help($action) {
        global $OUTPUT;
        $title = get_string( $action, VPL ) . ': ' . $this->get_printable_name();
        echo $OUTPUT->heading_with_help( $title, $action, 'vpl', 'icon' );
        self::$headerisout = true;
    }

    /**
     * Create tabs to view_description/submit/view_submission/edit
     *
     * @param $active tab
     *
     */
    public function print_view_tabs($active) {
        // TODO refactor using functions.
        global $CFG, $USER, $DB;
        $active = basename( $active );
        $cmid = $this->cm->id;
        $userid = optional_param( 'userid', null, PARAM_INT );
        $copy = optional_param( 'privatecopy', false, PARAM_INT );
        $viewer = $this->has_capability( VPL_VIEW_CAPABILITY );
        $submiter = $this->has_capability( VPL_SUBMIT_CAPABILITY );
        $similarity = $this->has_capability( VPL_SIMILARITY_CAPABILITY );
        $grader = $this->has_capability( VPL_GRADE_CAPABILITY );
        $manager = $this->has_capability( VPL_MANAGE_CAPABILITY );
        $example = $this->instance->example;
        if (! $userid || ! $grader || $copy) {
            $userid = $USER->id;
        }
        $level2 = $grader || $manager || $similarity;

        $strdescription = get_string( 'description', VPL );
        $strsubmission = get_string( 'submission', VPL );
        $stredit = get_string( 'edit', VPL );
        $strsubmissionview = get_string( 'submissionview', VPL );
        $maintabs = array ();
        $tabs = array ();
        $baseurl = $CFG->wwwroot . '/mod/' . VPL . '/';
        $href = vpl_mod_href( 'view.php', 'id', $cmid, 'userid', $userid );
        $viewtab = new tabobject( 'view.php', $href, $strdescription, $strdescription );
        if ($level2) {
            if ($viewer) {
                $maintabs [] = $viewtab;
            }
            $strsubmissionslist = get_string( 'submissionslist', VPL );
            $href = vpl_mod_href( 'views/submissionslist.php', 'id', $cmid );
            $maintabs [] = new tabobject( 'submissionslist.php', $href, $strsubmissionslist, $strsubmissionslist );
            // Similarity.
            if ($similarity) {
                if ($active == 'listwatermark.php' || $active == 'similarity_form.php' || $active == 'listsimilarity.php') {
                    $tabname = $active;
                } else {
                    $tabname = 'similarity';
                }
                $strsubmissionslist = get_string( 'similarity', VPL );
                $href = vpl_mod_href( 'similarity/similarity_form.php', 'id', $cmid );
                $maintabs [] = new tabobject( $tabname, $href, $strsubmissionslist, $strsubmissionslist );
            }
            // Test.
            if ($grader || $manager) {
                if ($userid == $USER->id) {
                    $text = get_string( 'test', VPL );
                } else {
                    $user = $DB->get_record( 'user', array (
                            'id' => $userid
                    ) );
                    if ($this->is_group_activity()) {
                        $text = get_string( 'group' ) . ' ';
                    } else {
                        $text = get_string( 'user' ) . ' ';
                    }
                    $text .= $this->fullname( $user, false );
                }
                if ($active == 'submission.php' || $active == 'edit.php'
                    || $active == 'submissionview.php' || $active == 'gradesubmission.php'
                    || $active == 'previoussubmissionslist.php') {
                    $tabname = $active;
                } else {
                    $tabname = 'test';
                }
                $href = vpl_mod_href( 'forms/submissionview.php', 'id', $cmid, 'userid', $userid );
                $maintabs [] = new tabobject( $tabname, $href, $text, $text );
            }
        }
        switch ($active) {
            case 'view.php' :
                if ($level2) {
                    print_tabs(
                            array (
                                    $maintabs,
                                    $tabs
                            ), $active );
                    return;
                }
            case 'submission.php' :
            case 'edit.php' :
            case 'submissionview.php' :
            case 'gradesubmission.php' :
            case 'previoussubmissionslist.php' :
                require_once('vpl_submission.class.php');
                $subinstance = $this->last_user_submission( $userid );
                if ($subinstance !== false) {
                    $submission = new mod_vpl_submission( $this, $subinstance );
                }
                if ($viewer && ! $level2) {
                    $tabs [] = $viewtab;
                }
                if ($manager || ($grader && $USER->id == $userid)
                    || (! $grader && $submiter && $this->is_submit_able()
                    && ! $this->instance->restrictededitor && ! $example)) {
                    $href = vpl_mod_href( 'forms/submission.php', 'id', $cmid, 'userid', $userid );
                    $tabs [] = new tabobject( 'submission.php', $href, $strsubmission, $strsubmission );
                }
                if ($manager || ($grader && $USER->id == $userid)
                    || (! $grader && $submiter && $this->is_submit_able())) {
                    $href = vpl_mod_href( 'forms/edit.php', 'id', $cmid, 'userid', $userid );
                    if ($example && $this->instance->run) {
                        $stredit = get_string( 'run', VPL );
                    }
                    $tabs [] = new tabobject( 'edit.php', $href, $stredit, $stredit );
                }
                if (! $example) {
                    $href = vpl_mod_href( 'forms/submissionview.php', 'id', $cmid, 'userid', $userid );
                    $tabs [] = new tabobject( 'submissionview.php', $href, $strsubmissionview, $strsubmissionview );
                    if ($grader && $this->get_grade() != 0 && $subinstance
                        && ($subinstance->dategraded == 0
                            || $subinstance->grader == $USER->id
                            || $subinstance->grader == 0)) {
                        $href = vpl_mod_href( 'forms/gradesubmission.php', 'id', $cmid, 'userid', $userid );
                        $text = get_string( 'grade' );
                        $tabs [] = new tabobject( 'gradesubmission.php', $href, $text, $text );
                    }
                    if ($subinstance && ($grader || $similarity)) {
                        $strlistprevoiussubmissions = get_string( 'previoussubmissionslist', VPL );
                        $href = vpl_mod_href( 'views/previoussubmissionslist.php', 'id', $cmid, 'userid', $userid );
                        $tabs [] = new tabobject( 'previoussubmissionslist.php', $href, $strlistprevoiussubmissions,
                                $strlistprevoiussubmissions );
                    }
                }
                // Show user picture if this activity require password.
                if (! isset( $user ) && $this->instance->password > '') {
                    $user = $DB->get_record( 'user', array (
                            'id' => $userid
                    ) );
                }
                if (isset( $user )) {
                    echo '<div style="position:absolute; right:50px; z-index:50;">';
                    echo $this->user_picture( $user );
                    echo '</div>';
                }
                if ($level2) {
                    print_tabs(
                            array (
                                    $maintabs,
                                    $tabs
                            ), $active );
                    return;
                } else {
                    print_tabs( array (
                            $tabs
                    ), $active );
                    return;
                }

                break;
            case 'submissionslist.php' :
                print_tabs( array (
                        $maintabs
                ), $active );
                return;
            case 'listwatermark.php' :
            case 'similarity_form.php' :
            case 'listsimilarity.php' :
                if ($similarity) {
                    $href = vpl_mod_href( 'similarity/similarity_form.php', 'id', $cmid );
                    $string = get_string( 'similarity', VPL );
                    $tabs [] = new tabobject( 'similarity_form.php', $href, $string, $string );
                    if ($active == 'listsimilarity.php') {
                        $string = get_string( 'listsimilarity', VPL );
                        $tabs [] = new tabobject( 'listsimilarity.php', '', $string, $string );
                    }
                    $plugincfg = get_config('mod_vpl');
                    $watermark = isset( $plugincfg->use_watermarks ) && $plugincfg->use_watermarks;
                    if ($watermark) {
                        $href = vpl_mod_href( 'similarity/listwatermark.php', 'id', $cmid );
                        $string = get_string( 'listwatermarks', VPL );
                        $tabs [] = new tabobject( 'listwatermark.php', $href, $string, $string );
                    }
                    $href = vpl_mod_href( 'views/downloadallsubmissions.php', 'id', $cmid );
                    $string = get_string( 'downloadallsubmissions', VPL );
                    $tabs [] = new tabobject( 'downloadallsubmissions.php', $href, $string, $string );
                }
                print_tabs( array (
                        $maintabs,
                        $tabs
                ), $active );
                break;
        }
    }

    /**
     * Show vpl name
     */
    public function print_name() {
        echo '<h2>';
        p( $this->get_printable_name() );
        echo '</h2>';
    }

    public function print_restriction($str, $value = null, $raw = false, $newline = true) {
        echo '<b>';
        if ($raw) {
            echo s( $str );
        } else {
            echo s( get_string( $str, VPL ) );
        }
        echo '</b>: ';
        if ($value === null) {
            $value = $this->instance->$str;
        }
        echo $value;
        echo $newline ? '<br />' : ' ';
    }

    /**
     * Show vpl submission period
     */
    public function print_submission_period() {
        if ($this->instance->startdate == 0 && $this->instance->duedate == 0) {
            return;
        }
        if ($this->instance->startdate) {
            $this->print_restriction( 'startdate', userdate( $this->instance->startdate ) );
        }
        if ($this->instance->duedate) {
            $this->print_restriction( 'duedate', userdate( $this->instance->duedate ) );
        }
    }

    /**
     * Show vpl submission restriction
     */
    public function print_submission_restriction() {
        global $CFG, $USER;
        // TODO print_submission_restriction.
        $filegroup = $this->get_required_fgm();
        $files = $filegroup->getfilelist();
        if (count( $files )) {
            $text = '';
            $needcomma = false;
            foreach ($files as $file) {
                if ($needcomma) {
                    $text .= ', ';
                }
                $text .= s( $file );
                $needcomma = true;
            }
            $link = ' (<a href="';
            $link .= vpl_mod_href( 'views/downloadrequiredfiles.php', 'id', $this->get_course_module()->id );
            $link .= '">';
            $link .= get_string( 'download', VPL );
            $link .= '</a>)';
            $this->print_restriction( 'requestedfiles', $text . $link );
        }
        if (count( $files ) != $this->instance->maxfiles) {
            $this->print_restriction( 'maxfiles' );
        }
        if ($this->instance->maxfilesize) {
            $mfs = $this->get_maxfilesize();
            $this->print_restriction( 'maxfilesize', vpl_conv_size_to_string( $mfs ) );
        }
        $worktype = $this->instance->worktype;
        $values = array (
                0 => get_string( 'individualwork', VPL ),
                1 => get_string( 'groupwork', VPL )
        );
        if ($worktype) {
            $this->print_restriction( 'worktype', $values [$worktype] . ' ' . $this->fullname( $USER ) );
        } else {
            $this->print_restriction( 'worktype', $values [$worktype] );
        }
        if ($this->has_capability( VPL_GRADE_CAPABILITY )) {
            $stryes = get_string( 'yes' );
            $strno = get_string( 'no' );
            require_once($CFG->libdir . '/gradelib.php');
            if ($gie = $this->get_grade_info()) {
                if ($gie->scaleid == 0) {
                    $info = get_string('grademax', 'core_grades')
                            . ': ' . format_float($gie->grademax, 5, true, true);
                    $info .= $gie->hidden ? (' <b>' . get_string( 'hidden', 'core_grades' ))
                            . '</b>' : '';
                    $info .= $gie->locked ? (' <b>' . get_string( 'locked', 'core_grades' ))
                            . '</b>' : '';
                } else {
                    $info = get_string( 'typescale', 'core_grades' );
                }
                $this->print_restriction( get_string( 'gradessettings', 'core_grades' ), $info, true );
            } else {
                $this->print_restriction( get_string( 'gradessettings', 'core_grades' ), get_string( 'nograde' ), true );
            }
            if (trim( $this->instance->password ) > '') {
                $this->print_restriction( get_string( 'password' ), $stryes, true );
            }
            if (trim( $this->instance->requirednet ) > '') {
                $this->print_restriction( 'requirednet', s( $this->instance->requirednet ) );
            }
            if ($this->instance->restrictededitor) {
                $this->print_restriction( 'restrictededitor', $stryes );
            }
            if ($this->instance->example) {
                $this->print_restriction( 'isexample', $stryes );
            }
            if (! $this->get_course_module()->visible) {
                $this->print_restriction( get_string( 'visible' ), $strno, true );
            }
            if ($this->instance->basedon) {
                $basedon = new mod_vpl( null, $this->instance->basedon );
                $link = '<a href="';
                $link .= vpl_mod_href( 'view.php', 'id', $basedon->cm->id );
                $link .= '">';
                $link .= $basedon->get_printable_name();
                $link .= '</a>';
                $this->print_restriction( 'basedon', $link );
            }
            $noyes = array (
                    $strno,
                    $stryes
            );
            $this->print_restriction( 'run', $noyes [$this->instance->run], false, false );
            if ($this->instance->debug) {
                $this->print_restriction( 'debug', $noyes [1], false, false );
            }
            $this->print_restriction( 'evaluate', $noyes [$this->instance->evaluate], false,
                    ! ($this->instance->evaluate && $this->instance->evaluateonsubmission) );
            if ($this->instance->evaluate && $this->instance->evaluateonsubmission) {
                $this->print_restriction( 'evaluateonsubmission', $noyes [1] );
            }
            if ($this->instance->automaticgrading) {
                $this->print_restriction( 'automaticgrading', $noyes [1], false, false );
            }
            if ($this->instance->maxexetime) {
                $this->print_restriction( 'maxexetime', $this->instance->maxexetime . ' s', false, false );
            }
            if ($this->instance->maxexememory) {
                $this->print_restriction( 'maxexememory', vpl_conv_size_to_string( $this->instance->maxexememory ), false, false );
            }
            if ($this->instance->maxexefilesize) {
                $this->print_restriction( 'maxexefilesize', vpl_conv_size_to_string( $this->instance->maxexefilesize ), false,
                        false );
            }
            if ($this->instance->maxexeprocesses) {
                $this->print_restriction( 'maxexeprocesses', null, false, false );
            }
        }
    }

    /**
     * Show short description
     */
    public function print_shordescription() {
        global $OUTPUT;
        if ($this->instance->shortdescription) {
            echo $OUTPUT->box_start();
            echo format_text( $this->instance->shortdescription, FORMAT_PLAIN );
            echo $OUTPUT->box_end();
        }
    }

    /**
     * Show full description
     */
    public function print_fulldescription() {
        global $OUTPUT;
        $full = $this->get_fulldescription_with_basedon();
        if ($full > '') {
            echo $OUTPUT->box( $full );
        } else {
            $this->print_shordescription();
        }
    }

    /**
     * Print variations in vpl instance
     */
    public function print_variations() {
        global $OUTPUT;
        global $DB;
        require_once(dirname( __FILE__ ) . '/views/show_hide_div.class.php');
        $variations = $DB->get_records( VPL_VARIATIONS, array (
                'vpl' => $this->instance->id
        ) );
        if (count( $variations ) > 0) {
            $div = new vpl_hide_show_div();
            echo '<br /><b>' . get_string( 'variations', VPL ) . $div->generate( true ) . '</b><br />';
            $div->begin_div();
            if (! $this->instance->usevariations) {
                echo '<b>' . get_string( 'variations_unused', VPL ) . '</b><br />';
            }
            if ($this->instance->variationtitle) {
                echo '<b>' . get_string( 'variationtitle', VPL ) . ': ' . s( $this->instance->variationtitle ) . '</b><br />';
            }
            $number = 1;
            foreach ($variations as $variation) {
                echo '<b>' . get_string( 'variation', VPL, $number ) . '</b><br />';
                echo $OUTPUT->box( $variation->description );
                $number ++;
            }
            $div->end_div();
        }
    }

    /**
     * Get user variation. Assign one if needed
     */
    public function get_variation($userid) {
        global $DB;
        if ($this->is_group_activity()) {
            $userid = $this->get_group_leaderid( $userid );
            if ($userid == 0) {
                return false;
            }
        }
        $varassigned = $DB->get_record( VPL_ASSIGNED_VARIATIONS,
                array (
                        'vpl' => $this->instance->id,
                        'userid' => $userid
                ) );
        if ($varassigned === false) { // Variation not assigned.
            $variations = $DB->get_records( VPL_VARIATIONS,
                    array (
                            'vpl' => $this->instance->id
                    ) );
            if (count( $variations ) == 0) { // No variation set.
                return false;
            }
            // Select a random variation.
            shuffle( $variations );
            $variation = $variations [0];
            $assign = new stdClass();
            $assign->vpl = $this->instance->id;
            $assign->variation = $variation->id;
            $assign->userid = $userid;
            if (! $DB->insert_record( VPL_ASSIGNED_VARIATIONS, $assign )) {
                print_error( 'vpl variation not assigned' );
            }
        } else {
            if ($varassigned === false || $varassigned->vpl != $this->instance->id) { // Test consistency.
                // TODO repair inconsistence?
                print_error( 'vpl assigned variation inconsistency' );
            }
            $variation = $DB->get_record( VPL_VARIATIONS,
                    array (
                            'id' => $varassigned->variation
                    ) );
        }
        return $variation;
    }

    /**
     * Show variations if actived and defined
     */
    public function print_variation($userid = 0, $already = array()) {
        global $OUTPUT;
        if (isset( $already [$this->instance->id] )) { // Avoid infinite recursion.
            return;
        }
        $already [$this->instance->id] = true; // Mark as visited.
        if ($this->instance->basedon) { // Show recursive varaitions.
            $basevpl = new mod_vpl( false, $this->instance->basedon );
            $basevpl->print_variation( $userid, $already );
        }
        // If user with grade or manage capability print all variations.
        if ($this->has_capability( VPL_GRADE_CAPABILITY, $userid ) || $this->has_capability( VPL_MANAGE_CAPABILITY,
                $userid )) {
            $this->print_variations();
        }
        // Show user variation if active.
        if ($this->instance->usevariations) { // Variations actived.
            $variation = $this->get_variation( $userid );
            if ($variation !== false) { // Variations defined.
                if ($this->instance->variationtitle > '') {
                    echo '<b>' . format_text( $this->instance->variationtitle, FORMAT_HTML ) . '</b><br />';
                }
                echo $OUTPUT->box( $variation->description );
            }
        }
    }

    /**
     * return an array with variations for this user
     */
    public function get_variation_identification($userid = 0, &$already = array()) {
        if (! ($this->instance->usevariations) || isset( $already [$this->instance->id] )) { // Avoid infinite recursion.
            return array ();
        }
        $already [$this->instance->id] = true;
        if ($this->instance->basedon) {
            $basevpl = new mod_vpl( false, $this->instance->basedon );
            $ret = $basevpl->get_variation_identification( $userid, $already );
        } else {
            $ret = array ();
        }
        $variation = $this->get_variation( $userid );
        if ($variation !== false) {
            $ret [] = $variation->identification;
        }
        return $ret;
    }
}
