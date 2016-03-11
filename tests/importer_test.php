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
 * Importer tests.
 *
 * @package    tool_lpimportoutcomes
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');

/**
 * Importer testcase.
 *
 * @package    tool_lpimportoutcomes
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer_testcase extends advanced_testcase {

    /**
     * Create an outcome.
     *
     * @todo MDL-52243 Move this to core data generators.
     * @return grade_outcome
     */
    public function create_outcome($record, array $options = null) {
        static $i = 0;

        $record = (object) $record;

        if (!isset($record->scaleid)) {
            throw new coding_exception('The scaleid must be set.');
        }

        $i++;

        if (!isset($record->courseid)) {
            $record->courseid = null;
        }
        if (!isset($record->shortname)) {
            $record->shortname = 'outcome' . $i;
        }
        if (!isset($record->fullname)) {
            $record->fullname = 'Outcome ' . $i;
        }
        if (!isset($record->description)) {
            $record->description = 'Description of outcome ' . $i;
        }
        if (!isset($record->descriptionformat)) {
            $record->descriptionformat = FORMAT_PLAIN;
        }

        $outcome = new grade_outcome($record, false);
        $outcome->insert();

        return $outcome;
    }

    public function test_validate_outcomes() {
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $c1 = $dg->create_course();
        $c2 = $dg->create_course();
        $scale = $dg->create_scale();

        $oc1 = $this->create_outcome(array('scaleid' => $scale->id));
        $oc2 = $this->create_outcome(array('scaleid' => $scale->id));
        $oc3 = $this->create_outcome(array('scaleid' => $scale->id));
        $oc4 = $this->create_outcome(array('scaleid' => $scale->id, 'courseid' => $c1->id));
        $oc5 = $this->create_outcome(array('scaleid' => $scale->id, 'courseid' => $c2->id));

        $importer = new \tool_lpimportoutcomes\importer();

        $this->assertTrue($importer->validate_outcomes(array($oc1->id)));
        $this->assertTrue($importer->validate_outcomes(array($oc1->id, $oc2->id, $oc3->id)));
        $this->assertTrue($importer->validate_outcomes(array($oc2->id, $oc3->id)));
        $this->assertFalse($importer->validate_outcomes(array($oc4->id)));
        $this->assertFalse($importer->validate_outcomes(array($oc5->id)));
        $this->assertFalse($importer->validate_outcomes(array($oc1->id, $oc5->id)));
        $this->assertFalse($importer->validate_outcomes(array($oc1->id, $oc3->id, $oc5->id)));
        $this->assertFalse($importer->validate_outcomes(array($oc4->id, $oc5->id)));
    }

    public function test_get_scales() {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        $scale1 = $dg->create_scale();
        $scale2 = $dg->create_scale();
        $scale3 = $dg->create_scale();

        $outcomes = [];
        $oc = $this->create_outcome(array('scaleid' => $scale1->id));
        $outcomes[] = $oc->id;
        $oc = $this->create_outcome(array('scaleid' => $scale1->id));
        $outcomes[] = $oc->id;
        $oc = $this->create_outcome(array('scaleid' => $scale2->id));
        $outcomes[] = $oc->id;
        $oc = $this->create_outcome(array('scaleid' => $scale2->id));
        $outcomes[] = $oc->id;
        $oc = $this->create_outcome(array('scaleid' => $scale2->id));
        $outcomes[] = $oc->id;
        $oc = $this->create_outcome(array('scaleid' => $scale3->id));
        $outcomes[] = $oc->id;
        $oc = $this->create_outcome(array('scaleid' => $scale3->id));
        $outcomes[] = $oc->id;
        $oc = $this->create_outcome(array('scaleid' => $scale3->id));
        $outcomes[] = $oc->id;
        $oc = $this->create_outcome(array('scaleid' => $scale3->id));
        $outcomes[] = $oc->id;

        $importer = new \tool_lpimportoutcomes\importer();
        $importer->set_outcomes($outcomes);

        $expected = array(
            $scale3->id => (object) array(
                'id' => $scale3->id,
                'name' => $scale3->name,
                'nb' => 4
            ),
            $scale2->id => (object) array(
                'id' => $scale2->id,
                'name' => $scale2->name,
                'nb' => 3
            ),
            $scale1->id => (object) array(
                'id' => $scale1->id,
                'name' => $scale1->name,
                'nb' => 2
            ),
        );

        $this->assertEquals($expected, $importer->get_scales());
    }

    public function test_basic_import() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $dg = $this->getDataGenerator();
        $scale = $dg->create_scale();
        $outcome = $this->create_outcome(array('scaleid' => $scale->id));

        $scaleconfigs = array(
            $scale->id => json_encode(array(
                array('scaleid' => $scale->id),
                array('id' => 1, 'proficient' => 1, 'scaledefault' => 1)
            ))
        );
        $frameworkdata = (object) array(
            'contextid' => context_system::instance()->id,
            'shortname' => 'Framework 1',
            'idnumber' => 'F1',
            'scaleid' => $scale->id,
            'scaleconfiguration' => $scaleconfigs[$scale->id]
        );

        $importer = new \tool_lpimportoutcomes\importer();
        $importer->set_outcomes(array($outcome->id));
        list($framework, $competencies) = $importer->create($frameworkdata, $scaleconfigs);

        $this->assertEquals($frameworkdata->contextid, $framework->get_contextid());
        $this->assertEquals($frameworkdata->shortname, $framework->get_shortname());
        $this->assertEquals($frameworkdata->idnumber, $framework->get_idnumber());
        $this->assertEquals($frameworkdata->scaleid, $framework->get_scaleid());
        $this->assertEquals($frameworkdata->scaleconfiguration, $framework->get_scaleconfiguration());

        $this->assertCount(1, $competencies);
        $this->assertEquals($framework->get_id(), $competencies[0]->get_competencyframeworkid());
        $this->assertEquals($outcome->shortname, $competencies[0]->get_idnumber());
        $this->assertEquals($outcome->fullname, $competencies[0]->get_shortname());
        $this->assertEquals(null, $competencies[0]->get_scaleid());
        $this->assertEquals(null, $competencies[0]->get_scaleconfiguration());
        $this->assertEquals($outcome->description, $competencies[0]->get_description());
        $this->assertEquals($outcome->descriptionformat, $competencies[0]->get_descriptionformat());
    }

    public function test_import_with_multiple_scales() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $dg = $this->getDataGenerator();
        $scale1 = $dg->create_scale();
        $scale2 = $dg->create_scale();

        $oc1 = $this->create_outcome(array('scaleid' => $scale2->id));
        $oc2 = $this->create_outcome(array('scaleid' => $scale1->id));
        $oc3 = $this->create_outcome(array('scaleid' => $scale2->id));

        $importer = new \tool_lpimportoutcomes\importer();
        $importer->set_outcomes(array($oc1->id, $oc2->id, $oc3->id));

        $scaleconfigs = array(
            $scale1->id => json_encode(array(
                array('scaleid' => $scale1->id),
                array('id' => 1, 'proficient' => 1, 'scaledefault' => 1)
            )),
            $scale2->id => json_encode(array(
                array('scaleid' => $scale2->id),
                array('id' => 1, 'proficient' => 1, 'scaledefault' => 1)
            ))
        );
        $frameworkdata = (object) array(
            'contextid' => context_system::instance()->id,
            'shortname' => 'Framework 1',
            'idnumber' => 'F1',
            'scaleid' => $scale2->id,
            'scaleconfiguration' => $scaleconfigs[$scale2->id]
        );

        list($framework, $competencies) = $importer->create($frameworkdata, $scaleconfigs);

        $this->assertEquals(null, $competencies[0]->get_scaleid());
        $this->assertEquals(null, $competencies[0]->get_scaleconfiguration());
        $this->assertEquals($scale1->id, $competencies[1]->get_scaleid());
        $this->assertEquals($scaleconfigs[$scale1->id], $competencies[1]->get_scaleconfiguration());
        $this->assertEquals(null, $competencies[2]->get_scaleid());
        $this->assertEquals(null, $competencies[2]->get_scaleconfiguration());
    }

    public function test_import_with_shared_outcome_shortnames() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $dg = $this->getDataGenerator();
        $scale1 = $dg->create_scale();

        $oc1 = $this->create_outcome(array('scaleid' => $scale1->id, 'shortname' => 'OC1'));
        $oc2 = $this->create_outcome(array('scaleid' => $scale1->id, 'shortname' => 'OC2'));
        $oc3 = $this->create_outcome(array('scaleid' => $scale1->id, 'shortname' => 'OC1'));
        $oc4 = $this->create_outcome(array('scaleid' => $scale1->id, 'shortname' => 'OC1'));
        $oc5 = $this->create_outcome(array('scaleid' => $scale1->id, 'shortname' => 'OC1_' . $oc4->id));

        $importer = new \tool_lpimportoutcomes\importer();
        $importer->set_outcomes(array($oc1->id, $oc2->id, $oc3->id, $oc4->id, $oc5->id));

        $scaleconfigs = array(
            $scale1->id => json_encode(array(
                array('scaleid' => $scale1->id),
                array('id' => 1, 'proficient' => 1, 'scaledefault' => 1)
            )),
        );
        $frameworkdata = (object) array(
            'contextid' => context_system::instance()->id,
            'shortname' => 'Framework 1',
            'idnumber' => 'F1',
            'scaleid' => $scale1->id,
            'scaleconfiguration' => $scaleconfigs[$scale1->id]
        );

        list($framework, $competencies) = $importer->create($frameworkdata, $scaleconfigs);

        $this->assertEquals($oc1->shortname, $competencies[0]->get_idnumber());
        $this->assertEquals($oc2->shortname, $competencies[1]->get_idnumber());
        $this->assertEquals($oc3->shortname . '_' . $oc3->id, $competencies[2]->get_idnumber());
        $this->assertEquals($oc4->shortname . '_' . $oc4->id, $competencies[3]->get_idnumber());
        $this->assertEquals($oc5->shortname . '_' . $oc5->id, $competencies[4]->get_idnumber());
    }
}
