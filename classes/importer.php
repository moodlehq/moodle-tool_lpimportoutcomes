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
 * Outcomes importer.
 *
 * @package    tool_lpimportoutcomes
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lpimportoutcomes;

use stdClass;
use \core_competency\api;

/**
 * Outcomes importer.
 *
 * @package    tool_lpimportoutcomes
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer {

    /** @var array Outcome IDs to import. */
    protected $outcomes = array();

    /**
     * Create a framework, and its competencies.
     *
     * This does not perform any validation.
     *
     * @param stdClass $frameworkdata Framework configuration.
     * @param array $scaleconfigurations Indexes are scale IDs, values are scale configuration.
     * @return array (\core_competency\competency_framework, \core_competency\competency[])
     */
    public function create(stdClass $frameworkdata, array $scaleconfigurations) {
        global $DB;
        $competencies = array();

        list($insql, $inparams) = $DB->get_in_or_equal($this->outcomes);
        $outcomes = $DB->get_records_select('grade_outcomes', "id $insql", $inparams);
        $knownidnumbers = array();
        $finalidnumbers = array();

        // Create the framework.
        $framework = api::create_framework($frameworkdata);

        // Create ID numbers for each outcome.
        foreach ($outcomes as $outcome) {
            $candidate = $outcome->shortname;
            while (isset($knownidnumbers[$candidate])) {
                $candidate = $candidate . '_' . $outcome->id;
            }

            $finalidnumbers[$outcome->id] = $candidate;
            $knownidnumbers[$candidate] = true;
        }

        // Loop over outcomes in the order provided.
        foreach ($this->outcomes as $outcomeid) {
            $outcome = $outcomes[$outcomeid];
            $outcome->idnumber = $finalidnumbers[$outcomeid];
            $outcome->shortname = $outcome->fullname;
            if ($outcome->scaleid != $framework->get_scaleid()) {
                $outcome->scaleconfiguration = isset($scaleconfigurations[$outcome->scaleid])
                    ? $scaleconfigurations[$outcome->scaleid] : null;
            } else {
                $outcome->scaleid = null;
            }
            unset($outcome->id, $outcome->courseid, $outcome->fullname, $outcome->timecreated,
                $outcome->timemodified, $outcome->usermodified);

            $outcome->competencyframeworkid = $framework->get_id();
            $competency = api::create_competency($outcome);
            $competencies[] = $competency;
        }

        return array($framework, $competencies);
    }

    /**
     * Get the relevant scales order by priority.
     *
     * @return stdClass[] Containing id, name, nb
     */
    public function get_scales() {
        global $DB;
        list($insql, $inparams) = $DB->get_in_or_equal($this->outcomes, SQL_PARAMS_NAMED);
        $sql = "SELECT s.id, s.name, COUNT(s.id) AS nb
                  FROM {scale} s
                  JOIN {grade_outcomes} go
                    ON go.scaleid = s.id
                 WHERE go.id $insql
              GROUP BY s.id, s.name
              ORDER BY COUNT(s.id) DESC, s.name";
        return $DB->get_records_sql($sql, $inparams);
    }

    /**
     * Return all importable outcomes.
     *
     * @return stdClass[]
     */
    public function get_all_outcomes() {
        global $DB;
        return $DB->get_records_select('grade_outcomes', 'courseid IS NULL', array(), 'shortname');
    }

    /**
     * Set the selected outcomes.
     *
     * @param array $ids
     */
    public function set_outcomes(array $ids) {
        $this->outcomes = $ids;
    }

    /**
     * Validate a list of outcome IDs.
     *
     * @param array $ids Outcome IDs.
     * @return bool
     */
    public function validate_outcomes(array $ids) {
        global $DB;
        $ids = array_unique($ids);
        list($insql, $inparams) = $DB->get_in_or_equal($ids);
        $sql = "courseid IS NULL AND id $insql";
        return count($ids) == $DB->count_records_select('grade_outcomes', $sql, $inparams, "COUNT('x')");
    }

}
