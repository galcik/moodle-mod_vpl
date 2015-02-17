<?php
/**
 * @package mod_vpl. Class for logging submission upload events
 * @copyright	2014 onwards Juan Carlos Rodríguez-del-Pino
 * @license		http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author		Juan Carlos Rodríguez-del-Pino <jcrodriguez@dis.ulpgc.es>
 */
namespace mod_vpl\event;
require_once dirname(__FILE__).'/../../locallib.php';
defined('MOODLE_INTERNAL') || die();

class submission_uploaded extends submission_base {
	protected function init() {
		parent::init();
		$this->data['crud'] = 'c';
		$this->legacy_action='submit files';
	}
}
