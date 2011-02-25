<?php

/**
 * Collaborative real-time editor assignment
 * @author Juan Leyva <juanleyvadelgado@gmail.com>
 *
 * Protocol:  http://code.google.com/p/google-mobwrite/wiki/Protocol
 * More info: http://www.youtube.com/watch?v=S2Hp_1jqpY8
*/

require_once($CFG->libdir.'/formslib.php');

/**
 * Extend the base assignment class for assignments with a real time collaboration editor
 *
 */
class assignment_rtcollaboration extends assignment_base {

    function assignment_rtcollaboration($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'rtcollaboration';
    }

    function view() {

        global $USER, $CFG;
        
        require_js($CFG->wwwroot.'/mod/assignment/type/rtcollaboration/diff_match_patch.js');
        require_js($CFG->wwwroot.'/mod/assignment/type/rtcollaboration/mobwrite_core.js');
        require_js($CFG->wwwroot.'/mod/assignment/type/rtcollaboration/mobwrite_form.js');
        

        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        require_capability('mod/assignment:view', $context);  
        $canedit = has_capability('mod/assignment:submit', $context);

        $submission = $this->get_submission();        
        $editable = $this->isopen() && (!$submission || $this->assignment->resubmit || !$submission->timemarked);

        add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        $this->view_header();        
        $this->view_intro();
        $this->view_dates();
        
        if($editable){
            echo '<script type="text/javascript"><!--
                window.onload = function(){
                    // We can use the full URL. Mobwrite thinks that is a remote server (no the same) so disabled ajax
                    mobwrite.syncUsername = "'.$USER->id.'";     
                    mobwrite.debug = false;
                    mobwrite.syncGateway = "type/rtcollaboration/mobwrite.php?id='.$this->cm->id.'&sesskey='.$USER->sesskey.'";
                    mobwrite.share("rteditor'.$this->assignment->id.'");
                }    
                --></script>';
                    
            $textdisabled = ($canedit)? '': 'disabled="disabled"';
            echo '<TEXTAREA ID="rteditor'.$this->assignment->id.'" STYLE="width: 100%; height: 100%" rows="30" '.$textdisabled.'></TEXTAREA>';
        }

        $this->view_feedback();
        $this->view_footer();
    }


    function print_student_answer($userid, $return=false){
        global $CFG;
        if (!$submission = $this->get_submission($userid)) {
            return '';
        }
        $output = '<div class="files">'.
                  '<img src="'.$CFG->pixpath.'/f/html.gif" class="icon" alt="html" />'.
                  link_to_popup_window ('/mod/assignment/type/rtcollaboration/file.php?id='.$this->cm->id.'&amp;userid='.
                  $submission->userid, 'file'.$userid, shorten_text(trim(strip_tags(format_text($submission->data1,$submission->data2))), 15), 450, 580,
                  get_string('submission', 'assignment'), 'none', true).
                  '</div>';
                  return $output;
    }

    function print_user_files($userid, $return=false) {
        global $CFG;

        if (!$submission = $this->get_submission($userid)) {
            return '';
        }

        $output = '<div class="files">'.
                  '<img align="middle" src="'.$CFG->pixpath.'/f/html.gif" height="16" width="16" alt="html" />'.
                  link_to_popup_window ('/mod/assignment/type/rtcollaboration/file.php?id='.$this->cm->id.'&amp;userid='.
                  $submission->userid, 'file'.$userid, shorten_text(trim(strip_tags(format_text($submission->data1,$submission->data2))), 15), 450, 580,
                  get_string('submission', 'assignment'), 'none', true).
                  '</div>';

        ///Stolen code from file.php

        print_simple_box_start('center', '', '', 0, 'generalbox', 'wordcount');
    /// Decide what to count
        if ($CFG->assignment_itemstocount == ASSIGNMENT_COUNT_WORDS) {
            echo ' ('.get_string('numwords', '', count_words(format_text($submission->data1, $submission->data2))).')';
        } else if ($CFG->assignment_itemstocount == ASSIGNMENT_COUNT_LETTERS) {
            echo ' ('.get_string('numletters', '', count_letters(format_text($submission->data1, $submission->data2))).')';
        }
        print_simple_box_end();
        print_simple_box(format_text($submission->data1, $submission->data2), 'center', '100%');

        ///End of stolen code from file.php

        if ($return) {
            //return $output;
        }
        //echo $output;
    }


    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $mform->addElement('select', 'resubmit', get_string("allowresubmit", "assignment"), $ynoptions);
        $mform->setHelpButton('resubmit', array('resubmit', get_string('allowresubmit', 'assignment'), 'assignment'));
        $mform->setDefault('resubmit', 0);

        $mform->addElement('select', 'emailteachers', get_string("emailteachers", "assignment"), $ynoptions);
        $mform->setHelpButton('emailteachers', array('emailteachers', get_string('emailteachers', 'assignment'), 'assignment'));
        $mform->setDefault('emailteachers', 0);

        $mform->addElement('select', 'var1', get_string("commentinline", "assignment"), $ynoptions);
        $mform->setHelpButton('var1', array('commentinline', get_string('commentinline', 'assignment'), 'assignment'));
        $mform->setDefault('var1', 0);

    }
    
    // We only submit assignments of users with some work done
    function submit_pending_assignments($assignments){
        if($assignments){
            foreach($assignments as $a){
                //TODO Add indexes to assignment_rtcollab_view
                $users = get_records('assignment_rtcollab_view','assignment',$a->id);
                if($users){
                    foreach($users as $u){
                        if(! $submission = get_record('assignment_submissions','assignment',$a->id,'userid',$USER->id)){
                            //
                        }
                    }
                }
            }
        }
    }
    
    // Check for pending submissions
    // Users does not submit theirself theirs assignments
    function submit_pending_submissions(){
        $timenow = time();
        // In date assignments        
        $assignments = get_records_sql("SELECT a.*,t.text,t.groupid FROM {$CFG->prefix}assignment a LEFT JOIN {$CFG->prefix}assignment_rtcollab_text t ON a.id = t.assignment WHERE $timenow > a.timeavailable AND ($timenow < a.timedue OR ($timenow > a.timedue AND a.preventlate = 0))");
        $this->submit_pending_assignments($assignments);        
    }
    
    
    function cron(){
        $this->submit_pending_submissions();
    }

}


?>