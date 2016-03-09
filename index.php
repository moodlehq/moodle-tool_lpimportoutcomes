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
 * Page to migrate frameworks.
 *
 * @package    tool_lpimportoutcomes
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$context = context_system::instance();
require_login(null, false);
require_capability('tool/lpimportoutcomes:outcomesimport', $context);

// Outcomes IDs being passed.
$ids = optional_param('ids', array(), PARAM_SEQUENCE);
if (!empty($ids)) {
    $ids = explode(',', $ids);
}

// Set-up the importer.
$importer = new \tool_lpimportoutcomes\importer();

// Set-up the page.
$url = new moodle_url('/admin/tool/lpimportoutcomes/index.php');
$title = get_string('importoutcomes', 'tool_lpimportoutcomes');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'tool_lpimportoutcomes'));

$output = $OUTPUT;

// We're still in step one.
if (empty($ids)) {
    $formstep1 = new \tool_lpimportoutcomes\form\choose_outcomes(null, array('importer' => $importer));

    if ($step1data = $formstep1->get_data()) {
        $ids = $step1data->outcomes;

    } else {
        echo $output->header();
        echo $output->heading($title);
        $formstep1->display();
        echo $output->footer();
        die();
    }
}

$importer->set_outcomes($ids);
$customdata = array(
    'importer' => $importer,
    'ids' => $ids,
    'persistent' => null
);
$formstep2 = new \tool_lpimportoutcomes\form\framework_setup(null, $customdata);
if ($formstep2->is_cancelled()) {
    redirect($url);
}

echo $output->header();
echo $output->heading($title);
if ($data = $formstep2->get_data()) {

    // We don't really need to set the outcomes again, but we do because the form was validated.
    $importer->set_outcomes($data->outcomes);

    // Do the things.
    list($framework, $competencies) = $importer->create($data->framework, $data->scales);

    $continueurl = new moodle_url('/admin/tool/lp/competencies.php', array(
        'competencyframeworkid' => $framework->get_id(),
        'pagecontextid' => $framework->get_contextid()
    ));
    $continuebtn = new single_button($continueurl, get_string('continuetoframework', 'tool_lpimportoutcomes'), 'get');
    $importbtn = new single_button($url, get_string('importmore', 'tool_lpimportoutcomes'), 'get');
    echo $output->confirm(get_string('outcomessuccessfullyimported', 'tool_lpimportoutcomes'), $continuebtn, $importbtn);

} else {
    $formstep2->display();
}

echo $output->footer();
