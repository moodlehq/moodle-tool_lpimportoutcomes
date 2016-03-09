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
 * Form.
 *
 * @package    tool_lpimportoutcomes
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lpimportoutcomes\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form class.
 *
 * @package    tool_lpimportoutcomes
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choose_outcomes extends \moodleform {

    protected function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('header', 'hdr', get_string('outcomes', 'grades'));

        $outcomes = $this->_customdata['importer']->get_all_outcomes();
        $options = array();
        foreach ($outcomes as $outcome) {
            $options[$outcome->id] = $outcome->fullname . ' (' . $outcome->shortname . ')';
        }
        $mform->addElement('autocomplete', 'outcomes', get_string('selectoutcomes', 'tool_lpimportoutcomes'), $options, array(
            'multiple' => true
        ));
        $mform->addRule('outcomes', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(false, get_string('nextstep', 'tool_lpimportoutcomes'));
    }

    public function validation($data, $files) {
        $errors = array();

        if (empty($data['outcomes'])) {
            $errors['outcomes'] = get_string('required');
        } else if (!$this->_customdata['importer']->validate_outcomes($data['outcomes'])) {
            $errors['outcomes'] = get_string('invalidoutcomefound', 'tool_lpimportoutcomes');
        }

        return $errors;
    }

}
