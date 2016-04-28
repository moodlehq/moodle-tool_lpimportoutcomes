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

use context_system;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form class.
 *
 * @package    tool_lpimportoutcomes
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_setup extends \tool_lp\form\competency_framework {

    /** @var array Fields to remove when getting the final data. */
    protected static $fieldstoremove = array('submitbutton');

    /** @var array Fields to remove from the persistent validation. */
    protected static $foreignfields = array('ids');

    /** @var array Additional foreign fields. */
    protected $extraforeignfields = array();

    public function definition() {
        global $DB, $PAGE;

        $mform = $this->_form;
        $mform->setDisableShortforms(true);

        $mform->addElement('header', 'hdroutcomes', get_string('outcomes', 'grades'));

        $ids = $this->_customdata['ids'];
        $scales = $this->_customdata['importer']->get_scales();

        // Keep a reference to the outcomes.
        $mform->addElement('hidden', 'ids', implode(',', $ids));
        $mform->setType('ids', PARAM_SEQUENCE);
        $mform->addElement('static', 'outcomescount', '', get_string('noutcomeschosen', 'tool_lpimportoutcomes', count($ids)));

        $mform->addElement('header', 'hdrframework', get_string('competencyframework', 'tool_lp'));

        // Force the context.
        $mform->addElement('hidden', 'contextid', context_system::instance()->id);
        $mform->setType('contextid', PARAM_INT);
        $mform->setConstant('contextid', context_system::instance()->id);

        $mform->addElement('text', 'shortname', get_string('shortname', 'tool_lp'));
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addElement('editor', 'description', get_string('description', 'tool_lp'), array('rows' => 4));
        $mform->setType('description', PARAM_RAW);
        $mform->addElement('text', 'idnumber', get_string('idnumber', 'tool_lp'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'required', null, 'client');

        $scale = array_shift($scales);
        $option = array($scale->id => $scale->name);
        $mform->addElement('select', 'scaleid', get_string('scale', 'tool_lp'), $option, array('disabled' => 'disabled'));
        $mform->setType('scaleid', PARAM_INT);
        $mform->addHelpButton('scaleid', 'scale', 'tool_lp');
        $mform->setConstant('scaleid', $scale->id);

        $mform->addElement('button', 'scaleconfigbutton', get_string('configurescale', 'tool_lp'));
        $mform->addElement('hidden', 'scaleconfiguration', '', array('id' => 'tool_lp_scaleconfiguration'));
        $mform->setType('scaleconfiguration', PARAM_RAW);
        $PAGE->requires->js_call_amd('tool_lp/scaleconfig', 'init', array('#id_scaleid',
            '#tool_lp_scaleconfiguration', '#id_scaleconfigbutton'));

        $mform->addElement('selectyesno', 'visible', get_string('visible', 'tool_lp'));
        $mform->setDefault('visible', true);
        $mform->addHelpButton('visible', 'visible', 'tool_lp');

        // Set taxonomies but do not ask the users to set them up as there will only be one level.
        for ($i = 1; $i <= 4; $i++) {
            $mform->addElement('hidden', "taxonomies[$i]", \core_competency\competency_framework::TAXONOMY_COMPETENCY);
            $mform->setConstant("taxonomies[$i]", \core_competency\competency_framework::TAXONOMY_COMPETENCY);
            $mform->setType("taxonomies[$i]", PARAM_ALPHANUMEXT);
            $this->extraforeignfields[] = "taxonomies[$i]";
        }

        // If there are extra scales display them.
        if (!empty($scales)) {
            $mform->addElement('header', 'hdrscales', get_string('scales', 'core'));
            $mform->addElement('static', 'scalesinfo', '', get_string('additionalscaleshelp', 'tool_lpimportoutcomes'));
        }

        foreach ($scales as $scale) {
            $fieldname = 'extrascales_' . $scale->id;
            $configfieldname = 'extrascalesconfig_' . $scale->id;
            $btnname = 'extrascalesbtn_' . $scale->id;

            $this->extraforeignfields[] = $fieldname;
            $this->extraforeignfields[] = $configfieldname;

            $option = array($scale->id => $scale->name);
            $mform->addElement('select', $fieldname, get_string('scale', 'tool_lp'), $option,
                array('id' => $fieldname, 'disabled' => 'disabled'));
            $mform->setType($fieldname, PARAM_INT);
            $mform->addHelpButton($fieldname, 'scale', 'tool_lp');
            $mform->setConstant($fieldname, $scale->id);

            $mform->addElement('button', $btnname, get_string('configurescale', 'tool_lp'), array('id' => $btnname));
            $mform->addElement('hidden', $configfieldname, '', array('id' => $configfieldname));
            $mform->setType($configfieldname, PARAM_RAW);
            $PAGE->requires->js_call_amd('tool_lp/scaleconfig', 'init', array('#' . $fieldname,
                '#' . $configfieldname, '#' . $btnname));
        }

        $this->add_action_buttons(true, get_string('createframework', 'tool_lpimportoutcomes'));
    }

    /**
     * Get form data.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (is_object($data)) {
            $persistentdata = $this->filter_data_for_persistent($data);
            $scales = array();

            // Reorganise additional scales.
            foreach ($data as $key => $value) {
                if (strpos($key, 'extrascales_') === 0) {
                    $scales[$value] = $data->{'extrascalesconfig_' . $value};
                }
            }

            $data = (object) array(
                'framework' => $persistentdata,
                'scales' => $scales,
                'outcomes' => explode(',', $data->ids)
            );
        }
        return $data;
    }

    /**
     * Extra validation.
     *
     * @param  stdClass $data Data to validate.
     * @param  array $files Array of files.
     * @param  array $errors Currently reported errors.
     * @return array of additional errors, or overridden errors.
     */
    protected function extra_validation($data, $files, array &$existingerrors) {
        $newerrors = parent::extra_validation($data, $files, $existingerrors);

        if (!$this->_customdata['importer']->validate_outcomes(explode(',', $data->ids))) {
            // If this error occurs it will be transparent, but that can only be the result of messing up with the form.
            $newerrors['ids'] = get_string('invalidoutcomefound', 'tool_lpimportoutcomes');
        }

        // Validate each of the scale configuration, they should be allowed on the framework.
        $extrascaleids = array();
        foreach ($data as $key => $value) {
            if (strpos($key, 'extrascales_') === 0) {
                $extrascaleids[] = $value;
            }
        }
        foreach ($extrascaleids as $scaleid) {
            // We use the persistent to validate the data.
            $persistentdata = $this->filter_data_for_persistent($data);
            $persistentdata->scaleid = $data->{'extrascales_' . $scaleid};
            $persistentdata->scaleconfiguration = $data->{'extrascalesconfig_' . $scaleid};
            $persistent = $this->get_persistent();
            $persistent->from_record((object) $persistentdata);
            $errors = $persistent->get_errors();
            if (!empty($errors['scaleid'])) {
                $newerrors['extrascales_' . $scaleid] = $errors['scaleid'];
            } else if (!empty($errors['scaleconfiguration'])) {
                $newerrors['extrascales_' . $scaleid] = $errors['scaleconfiguration'];
            }
        }

        return $newerrors;
    }

    /**
     * Filter out the foreign fields of the persistent.
     *
     * @param stdClass $data The data to filter the fields out of.
     * @return stdClass.
     */
    protected function filter_data_for_persistent($data) {
        $data = (array) parent::filter_data_for_persistent($data);
        $data = array_diff_key($data, array_flip((array) $this->extraforeignfields));
        return (object) $data;
    }

}
