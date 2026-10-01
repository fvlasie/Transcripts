<?php

namespace Gibbon\Module\Transcripts\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class TranscriptGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonTermAlias';
    private static $primaryKey = 'gibbonTermAliasID';

    /**
     * Every Grade Scale value ever entered for the student, regardless of enrolment or status.
     * Rows are ordered so that, for a given class and term, the latest cycle (by Reports sequence) comes last.
     */
    public function getStudentGradeRecords(int $gibbonPersonID): array
    {
        $sql = $this->getGradeRecordSql().'
            WHERE gibbonReportingValue.gibbonPersonIDStudent = :gibbonPersonID
            AND (gibbonReportingValue.gibbonScaleGradeID IS NOT NULL OR TRIM(COALESCE(gibbonReportingValue.value, \'\')) <> \'\')
            ORDER BY gibbonSchoolYear.sequenceNumber ASC,
                     COALESCE(gibbonSchoolYearTerm.sequenceNumber, gibbonReportingCycle.sequenceNumber) ASC,
                     gibbonCourse.nameShort ASC,
                     gibbonReportingCycle.sequenceNumber ASC,
                     gibbonReportingCycle.dateEnd ASC,
                     gibbonReportingCriteria.sequenceNumber DESC';

        return $this->db()->select($sql, ['gibbonPersonID' => $gibbonPersonID])->fetchAll() ?: [];
    }

    public function getStudentGradeRecordByKey(int $gibbonPersonID, int $gibbonReportingCriteriaID, int $gibbonCourseClassID): ?array
    {
        $sql = $this->getGradeRecordSql().'
            WHERE gibbonReportingValue.gibbonPersonIDStudent = :gibbonPersonID
            AND gibbonReportingValue.gibbonReportingCriteriaID = :gibbonReportingCriteriaID
            AND gibbonReportingValue.gibbonCourseClassID = :gibbonCourseClassID
            LIMIT 1';

        $row = $this->db()->select($sql, [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonReportingCriteriaID' => $gibbonReportingCriteriaID,
            'gibbonCourseClassID' => $gibbonCourseClassID,
        ])->fetch();

        return !empty($row) ? $row : null;
    }

    private function getGradeRecordSql(): string
    {
        $termMatch = $this->getTermMatchSubquery('gibbonReportingCycle');

        return "SELECT
                gibbonReportingValue.gibbonPersonIDStudent AS gibbonPersonID,
                gibbonCourseClass.gibbonCourseClassID,
                gibbonCourseClass.nameShort AS className,
                gibbonCourse.gibbonCourseID,
                gibbonCourse.gibbonSchoolYearID,
                gibbonReportingValue.gibbonReportingValueID,
                gibbonReportingValue.gibbonReportingCriteriaID,
                gibbonReportingValue.gibbonReportingCycleID,
                gibbonReportingValue.gibbonScaleGradeID,
                gibbonReportingValue.value AS reportingValue,
                gibbonReportingValue.value AS numericGrade,
                gibbonReportingValue.timestampModified,
                gibbonReportingValue.gibbonPersonIDModified,
                modifier.title AS modifiedTitle,
                modifier.preferredName AS modifiedPreferredName,
                modifier.surname AS modifiedSurname,
                gibbonReportingCycle.name AS cycleName,
                gibbonSchoolYear.name AS schoolYearName,
                gibbonSchoolYearTerm.gibbonSchoolYearTermID,
                COALESCE(gibbonSchoolYearTerm.name, gibbonReportingCycle.name) AS termName,
                gibbonTermAlias.secularAlias,
                gibbonCourse.name AS courseName,
                gibbonCourse.nameShort AS courseCode,
                gibbonCoursesAndClasses.externalCourseCode,
                COALESCE(gibbonCoursesAndClasses.credits, NULLIF(gibbonCourse.credits, 0), 3.00) AS credits,
                gibbonCourse.courseLevel,
                gibbonCourse.modeOfInstruction,
                COALESCE(gibbonReportingCriteriaType.gibbonScaleID, gibbonReportingCriteria.gibbonScaleID) AS gibbonScaleID,
                COALESCE(NULLIF(TRIM(gibbonScaleGrade.value), ''), NULLIF(TRIM(gibbonScaleGrade.descriptor), ''), gibbonReportingValue.value) AS letterGrade
            FROM gibbonReportingValue
            INNER JOIN gibbonReportingCriteria ON gibbonReportingCriteria.gibbonReportingCriteriaID = gibbonReportingValue.gibbonReportingCriteriaID
            INNER JOIN gibbonReportingCriteriaType ON gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID = gibbonReportingCriteria.gibbonReportingCriteriaTypeID
                AND gibbonReportingCriteriaType.valueType = 'Grade Scale'
            INNER JOIN gibbonCourseClass ON gibbonCourseClass.gibbonCourseClassID = gibbonReportingValue.gibbonCourseClassID
            INNER JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
            INNER JOIN gibbonSchoolYear ON gibbonSchoolYear.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
            INNER JOIN gibbonReportingCycle ON gibbonReportingCycle.gibbonReportingCycleID = gibbonReportingValue.gibbonReportingCycleID
            LEFT JOIN gibbonSchoolYearTerm ON gibbonSchoolYearTerm.gibbonSchoolYearTermID = {$termMatch}
            LEFT JOIN gibbonTermAlias ON gibbonTermAlias.gibbonSchoolYearTermID = gibbonSchoolYearTerm.gibbonSchoolYearTermID
            LEFT JOIN gibbonScaleGrade ON gibbonScaleGrade.gibbonScaleGradeID = gibbonReportingValue.gibbonScaleGradeID
            LEFT JOIN gibbonCoursesAndClasses ON gibbonCoursesAndClasses.courseCode = gibbonCourse.nameShort
            LEFT JOIN gibbonPerson AS modifier ON modifier.gibbonPersonID = gibbonReportingValue.gibbonPersonIDModified";
    }

    /**
     * Matches a reporting cycle to a school year term: exact dates, then name, then short name,
     * then the term containing the cycle's end date.
     */
    private function getTermMatchSubquery(string $cycleAlias): string
    {
        return "(SELECT t.gibbonSchoolYearTermID
                FROM gibbonSchoolYearTerm AS t
                WHERE t.gibbonSchoolYearID = {$cycleAlias}.gibbonSchoolYearID
                AND (
                    ({$cycleAlias}.dateStart = t.firstDay AND {$cycleAlias}.dateEnd = t.lastDay)
                    OR {$cycleAlias}.name = t.name
                    OR {$cycleAlias}.nameShort = t.nameShort
                    OR {$cycleAlias}.dateEnd BETWEEN t.firstDay AND t.lastDay
                )
                ORDER BY CASE
                    WHEN {$cycleAlias}.dateStart = t.firstDay AND {$cycleAlias}.dateEnd = t.lastDay THEN 0
                    WHEN {$cycleAlias}.name = t.name THEN 1
                    WHEN {$cycleAlias}.nameShort = t.nameShort THEN 2
                    ELSE 3
                END, t.sequenceNumber
                LIMIT 1)";
    }

    /**
     * Current class enrolments that have no Grade Scale value yet.
     */
    public function getStudentUngradedClasses(int $gibbonPersonID): array
    {
        $sql = "SELECT
                gibbonCourseClassPerson.gibbonPersonID,
                gibbonCourseClass.gibbonCourseClassID,
                gibbonCourseClass.nameShort AS className,
                gibbonCourse.gibbonCourseID,
                gibbonCourse.gibbonSchoolYearID,
                gibbonSchoolYear.name AS schoolYearName,
                gibbonCourse.name AS courseName,
                gibbonCourse.nameShort AS courseCode,
                gibbonCoursesAndClasses.externalCourseCode,
                COALESCE(gibbonCoursesAndClasses.credits, NULLIF(gibbonCourse.credits, 0), 3.00) AS credits
            FROM gibbonCourseClassPerson
            INNER JOIN gibbonCourseClass ON gibbonCourseClass.gibbonCourseClassID = gibbonCourseClassPerson.gibbonCourseClassID
            INNER JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
            INNER JOIN gibbonSchoolYear ON gibbonSchoolYear.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
            LEFT JOIN gibbonCoursesAndClasses ON gibbonCoursesAndClasses.courseCode = gibbonCourse.nameShort
            WHERE gibbonCourseClassPerson.gibbonPersonID = :gibbonPersonID
            AND gibbonCourseClassPerson.role = 'Student'
            AND NOT EXISTS (
                SELECT 1
                FROM gibbonReportingValue AS rv
                JOIN gibbonReportingCriteria AS crit ON crit.gibbonReportingCriteriaID = rv.gibbonReportingCriteriaID
                JOIN gibbonReportingCriteriaType AS ct ON ct.gibbonReportingCriteriaTypeID = crit.gibbonReportingCriteriaTypeID AND ct.valueType = 'Grade Scale'
                WHERE rv.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
                AND rv.gibbonPersonIDStudent = gibbonCourseClassPerson.gibbonPersonID
                AND (rv.gibbonScaleGradeID IS NOT NULL OR TRIM(COALESCE(rv.value, '')) <> '')
            )
            ORDER BY gibbonSchoolYear.sequenceNumber, gibbonCourse.nameShort, gibbonCourseClass.nameShort";

        return $this->db()->select($sql, ['gibbonPersonID' => $gibbonPersonID])->fetchAll() ?: [];
    }

    /**
     * Grade Scale criteria a class can be graded against, one per reporting cycle (the first by sequence).
     */
    public function getGradeCriteriaForClass(int $gibbonCourseClassID): array
    {
        $termMatch = $this->getTermMatchSubquery('gibbonReportingCycle');

        $sql = "SELECT gibbonReportingCycle.gibbonReportingCycleID,
                       gibbonReportingCycle.name AS cycleName,
                       gibbonReportingCycle.gibbonSchoolYearID,
                       gibbonReportingCriteria.gibbonReportingCriteriaID,
                       COALESCE(gibbonReportingCriteriaType.gibbonScaleID, gibbonReportingCriteria.gibbonScaleID) AS gibbonScaleID,
                       gibbonSchoolYearTerm.gibbonSchoolYearTermID,
                       gibbonSchoolYearTerm.name AS termName
                FROM gibbonCourseClass
                JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
                JOIN gibbonReportingCycle ON gibbonReportingCycle.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
                JOIN gibbonReportingScope ON gibbonReportingScope.gibbonReportingCycleID = gibbonReportingCycle.gibbonReportingCycleID AND gibbonReportingScope.scopeType = 'Course'
                JOIN gibbonReportingCriteria ON gibbonReportingCriteria.gibbonReportingScopeID = gibbonReportingScope.gibbonReportingScopeID
                    AND gibbonReportingCriteria.target = 'Per Student'
                    AND (gibbonReportingCriteria.gibbonCourseID IS NULL OR gibbonReportingCriteria.gibbonCourseID = gibbonCourse.gibbonCourseID)
                JOIN gibbonReportingCriteriaType ON gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID = gibbonReportingCriteria.gibbonReportingCriteriaTypeID
                    AND gibbonReportingCriteriaType.valueType = 'Grade Scale'
                LEFT JOIN gibbonSchoolYearTerm ON gibbonSchoolYearTerm.gibbonSchoolYearTermID = {$termMatch}
                WHERE gibbonCourseClass.gibbonCourseClassID = :gibbonCourseClassID
                ORDER BY gibbonReportingCycle.sequenceNumber, gibbonReportingCycle.dateStart, gibbonReportingScope.sequenceNumber, gibbonReportingCriteria.sequenceNumber";

        $rows = $this->db()->select($sql, ['gibbonCourseClassID' => $gibbonCourseClassID])->fetchAll() ?: [];

        $criteria = [];
        foreach ($rows as $row) {
            $cycleID = (int)$row['gibbonReportingCycleID'];
            if (!isset($criteria[$cycleID])) {
                $criteria[$cycleID] = $row;
            }
        }

        return array_values($criteria);
    }

    /**
     * Returns the criterion's cycle, school year and scale when it is a Per Student Grade Scale
     * criterion that applies to the class; null otherwise.
     */
    public function getGradeCriterionForClass(int $gibbonReportingCriteriaID, int $gibbonCourseClassID): ?array
    {
        $sql = "SELECT gibbonReportingCriteria.gibbonReportingCriteriaID,
                       gibbonReportingCycle.gibbonReportingCycleID,
                       gibbonReportingCycle.gibbonSchoolYearID,
                       COALESCE(gibbonReportingCriteriaType.gibbonScaleID, gibbonReportingCriteria.gibbonScaleID) AS gibbonScaleID
                FROM gibbonReportingCriteria
                JOIN gibbonReportingCriteriaType ON gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID = gibbonReportingCriteria.gibbonReportingCriteriaTypeID
                JOIN gibbonReportingScope ON gibbonReportingScope.gibbonReportingScopeID = gibbonReportingCriteria.gibbonReportingScopeID
                JOIN gibbonReportingCycle ON gibbonReportingCycle.gibbonReportingCycleID = gibbonReportingCriteria.gibbonReportingCycleID
                JOIN gibbonCourseClass ON gibbonCourseClass.gibbonCourseClassID = :gibbonCourseClassID
                JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
                WHERE gibbonReportingCriteria.gibbonReportingCriteriaID = :gibbonReportingCriteriaID
                AND gibbonReportingCriteriaType.valueType = 'Grade Scale'
                AND gibbonReportingCriteria.target = 'Per Student'
                AND gibbonReportingScope.scopeType = 'Course'
                AND gibbonReportingCycle.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
                AND (gibbonReportingCriteria.gibbonCourseID IS NULL OR gibbonReportingCriteria.gibbonCourseID = gibbonCourse.gibbonCourseID)";

        $row = $this->db()->selectOne($sql, [
            'gibbonReportingCriteriaID' => $gibbonReportingCriteriaID,
            'gibbonCourseClassID' => $gibbonCourseClassID,
        ]);

        return !empty($row) && is_array($row) ? $row : null;
    }

    public function isStudentLinkedToClass(int $gibbonPersonID, int $gibbonCourseClassID): bool
    {
        $sql = "SELECT (
                    EXISTS (SELECT 1 FROM gibbonCourseClassPerson WHERE gibbonPersonID = :personEnrolment AND gibbonCourseClassID = :classEnrolment AND role LIKE 'Student%')
                    OR EXISTS (SELECT 1 FROM gibbonReportingValue WHERE gibbonPersonIDStudent = :personValue AND gibbonCourseClassID = :classValue)
                ) AS linked";

        return (bool)$this->db()->selectOne($sql, [
            'personEnrolment' => $gibbonPersonID,
            'classEnrolment' => $gibbonCourseClassID,
            'personValue' => $gibbonPersonID,
            'classValue' => $gibbonCourseClassID,
        ]);
    }

    public function scaleGradeBelongsToScale(int $gibbonScaleGradeID, int $gibbonScaleID): bool
    {
        $sql = "SELECT COUNT(*) FROM gibbonScaleGrade WHERE gibbonScaleGradeID = :gibbonScaleGradeID AND gibbonScaleID = :gibbonScaleID";

        return (int)$this->db()->selectOne($sql, [
            'gibbonScaleGradeID' => $gibbonScaleGradeID,
            'gibbonScaleID' => $gibbonScaleID,
        ]) > 0;
    }

    public function getGradeScaleOptionsByScaleID(?int $gibbonScaleID): array
    {
        if (empty($gibbonScaleID)) {
            return [];
        }

        $sql = "SELECT gibbonScaleGradeID, value, descriptor
                FROM gibbonScaleGrade
                WHERE gibbonScaleID = :gibbonScaleID
                ORDER BY sequenceNumber, value";

        $rows = $this->db()->select($sql, ['gibbonScaleID' => $gibbonScaleID])->fetchAll();
        $options = [];
        foreach ($rows as $row) {
            $label = trim(($row['value'] ?? '').(!empty($row['descriptor']) && $row['descriptor'] !== $row['value'] ? ' — '.$row['descriptor'] : ''));
            $options[$row['gibbonScaleGradeID']] = $label !== '' ? $label : $row['gibbonScaleGradeID'];
        }

        return $options;
    }

    public function getTermsBySchoolYear(int $gibbonSchoolYearID): array
    {
        $sql = "SELECT gibbonSchoolYearTermID, name
                FROM gibbonSchoolYearTerm
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                ORDER BY sequenceNumber";

        $rows = $this->db()->select($sql, ['gibbonSchoolYearID' => $gibbonSchoolYearID])->fetchAll() ?: [];

        return array_column($rows, 'name', 'gibbonSchoolYearTermID');
    }

    /**
     * The term in which the class has the most Planner lessons, or 0 when it has none.
     */
    public function getPlannerTermIDForClass(int $gibbonCourseClassID): int
    {
        $sql = "SELECT gibbonSchoolYearTerm.gibbonSchoolYearTermID
                FROM gibbonPlannerEntry
                JOIN gibbonCourseClass ON gibbonCourseClass.gibbonCourseClassID = gibbonPlannerEntry.gibbonCourseClassID
                JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
                JOIN gibbonSchoolYearTerm ON gibbonSchoolYearTerm.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
                    AND gibbonPlannerEntry.date BETWEEN gibbonSchoolYearTerm.firstDay AND gibbonSchoolYearTerm.lastDay
                WHERE gibbonPlannerEntry.gibbonCourseClassID = :gibbonCourseClassID
                GROUP BY gibbonSchoolYearTerm.gibbonSchoolYearTermID, gibbonSchoolYearTerm.sequenceNumber
                ORDER BY COUNT(*) DESC, gibbonSchoolYearTerm.sequenceNumber
                LIMIT 1";

        return (int)$this->db()->selectOne($sql, ['gibbonCourseClassID' => $gibbonCourseClassID]);
    }

    public function getReportingCycleIDForTerm(int $gibbonSchoolYearTermID): int
    {
        if ($gibbonSchoolYearTermID <= 0) {
            return 0;
        }

        $sql = "SELECT c.gibbonReportingCycleID
                FROM gibbonSchoolYearTerm AS t
                JOIN gibbonReportingCycle AS c ON c.gibbonSchoolYearID = t.gibbonSchoolYearID
                WHERE t.gibbonSchoolYearTermID = :gibbonSchoolYearTermID
                AND (
                    (c.dateStart = t.firstDay AND c.dateEnd = t.lastDay)
                    OR c.name = t.name
                    OR c.nameShort = t.nameShort
                )
                ORDER BY CASE
                    WHEN c.dateStart = t.firstDay AND c.dateEnd = t.lastDay THEN 0
                    ELSE 1
                END, c.gibbonReportingCycleID
                LIMIT 1";

        return (int)$this->db()->selectOne($sql, ['gibbonSchoolYearTermID' => $gibbonSchoolYearTermID]);
    }

    /**
     * Creates (or completes) the reporting cycle, Course scope and Grade Scale criterion for a term.
     * Only called from the explicit "Set up grading for this term" action.
     */
    public function ensureReportingCycleForTerm(int $gibbonSchoolYearTermID): int
    {
        $existing = $this->getReportingCycleIDForTerm($gibbonSchoolYearTermID);
        if ($existing > 0) {
            $this->ensureReportingCriteriaForCycle($existing);

            return $existing;
        }

        $term = $this->db()->selectOne(
            "SELECT gibbonSchoolYearTermID, gibbonSchoolYearID, name, nameShort, sequenceNumber, firstDay, lastDay
             FROM gibbonSchoolYearTerm
             WHERE gibbonSchoolYearTermID = :gibbonSchoolYearTermID",
            ['gibbonSchoolYearTermID' => $gibbonSchoolYearTermID]
        );
        if (empty($term) || !is_array($term)) {
            return 0;
        }

        $yearGroupIDList = (string)$this->db()->selectOne(
            "SELECT GROUP_CONCAT(gibbonYearGroupID ORDER BY sequenceNumber SEPARATOR ',')
             FROM gibbonYearGroup"
        );
        $cycleTotal = (int)$this->db()->selectOne(
            "SELECT COUNT(*) FROM gibbonSchoolYearTerm WHERE gibbonSchoolYearID = :gibbonSchoolYearID",
            ['gibbonSchoolYearID' => $term['gibbonSchoolYearID']]
        );

        $inserted = $this->db()->insert(
            "INSERT INTO gibbonReportingCycle
                (gibbonSchoolYearID, gibbonYearGroupIDList, name, nameShort, sequenceNumber, cycleNumber, cycleTotal, dateStart, dateEnd, notes)
             VALUES
                (:gibbonSchoolYearID, :gibbonYearGroupIDList, :name, :nameShort, :sequenceNumber, :cycleNumber, :cycleTotal, :dateStart, :dateEnd, :notes)",
            [
                'gibbonSchoolYearID' => $term['gibbonSchoolYearID'],
                'gibbonYearGroupIDList' => $yearGroupIDList !== '' ? $yearGroupIDList : null,
                'name' => $term['name'],
                'nameShort' => $term['nameShort'] ?: $term['name'],
                'sequenceNumber' => (int)$term['sequenceNumber'],
                'cycleNumber' => (int)$term['sequenceNumber'],
                'cycleTotal' => max(1, $cycleTotal),
                'dateStart' => $term['firstDay'],
                'dateEnd' => $term['lastDay'],
                'notes' => 'Created by Transcripts from school year term dates.',
            ]
        );

        $cycleID = (int)$inserted;
        if ($cycleID <= 0) {
            return $this->getReportingCycleIDForTerm($gibbonSchoolYearTermID);
        }

        $this->ensureReportingCriteriaForCycle($cycleID);

        return $cycleID;
    }

    private function ensureReportingCriteriaForCycle(int $gibbonReportingCycleID): void
    {
        if ($gibbonReportingCycleID <= 0) {
            return;
        }

        $existingCriteria = (int)$this->db()->selectOne(
            "SELECT gibbonReportingCriteria.gibbonReportingCriteriaID
             FROM gibbonReportingCriteria
             JOIN gibbonReportingCriteriaType ON gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID = gibbonReportingCriteria.gibbonReportingCriteriaTypeID
             JOIN gibbonReportingScope ON gibbonReportingScope.gibbonReportingScopeID = gibbonReportingCriteria.gibbonReportingScopeID AND gibbonReportingScope.scopeType = 'Course'
             WHERE gibbonReportingCriteria.gibbonReportingCycleID = :gibbonReportingCycleID
             AND gibbonReportingCriteria.gibbonCourseID IS NULL
             AND gibbonReportingCriteria.target = 'Per Student'
             AND gibbonReportingCriteriaType.valueType = 'Grade Scale'
             LIMIT 1",
            ['gibbonReportingCycleID' => $gibbonReportingCycleID]
        );
        if ($existingCriteria > 0) {
            return;
        }

        $scopeID = (int)$this->db()->selectOne(
            "SELECT gibbonReportingScopeID
             FROM gibbonReportingScope
             WHERE gibbonReportingCycleID = :gibbonReportingCycleID
             AND scopeType = 'Course'
             ORDER BY sequenceNumber, gibbonReportingScopeID
             LIMIT 1",
            ['gibbonReportingCycleID' => $gibbonReportingCycleID]
        );
        if ($scopeID <= 0) {
            $scopeID = (int)$this->db()->insert(
                "INSERT INTO gibbonReportingScope (gibbonReportingCycleID, scopeType, name, sequenceNumber)
                 VALUES (:gibbonReportingCycleID, 'Course', 'Course', 1)",
                ['gibbonReportingCycleID' => $gibbonReportingCycleID]
            );
        }

        $criteriaTypeID = (int)$this->db()->selectOne(
            "SELECT gibbonReportingCriteriaTypeID
             FROM gibbonReportingCriteriaType
             WHERE valueType = 'Grade Scale' AND active = 'Y'
             ORDER BY gibbonReportingCriteriaTypeID
             LIMIT 1"
        );
        $scaleID = $this->getDefaultGradeScaleID();
        if ($criteriaTypeID <= 0 && $scaleID > 0) {
            $criteriaTypeID = (int)$this->db()->insert(
                "INSERT INTO gibbonReportingCriteriaType (name, valueType, active, gibbonScaleID)
                 VALUES ('Grade Scale', 'Grade Scale', 'Y', :gibbonScaleID)",
                ['gibbonScaleID' => $scaleID]
            );
        }

        if ($scopeID <= 0 || $criteriaTypeID <= 0) {
            return;
        }

        $this->db()->insert(
            "INSERT INTO gibbonReportingCriteria
                (gibbonReportingCycleID, gibbonReportingScopeID, gibbonReportingCriteriaTypeID, target, name, gibbonScaleID, sequenceNumber)
             VALUES
                (:gibbonReportingCycleID, :gibbonReportingScopeID, :gibbonReportingCriteriaTypeID, 'Per Student', 'Grade', :gibbonScaleID, 1)",
            [
                'gibbonReportingCycleID' => $gibbonReportingCycleID,
                'gibbonReportingScopeID' => $scopeID,
                'gibbonReportingCriteriaTypeID' => $criteriaTypeID,
                'gibbonScaleID' => $scaleID > 0 ? $scaleID : null,
            ]
        );
    }

    private function getDefaultGradeScaleID(): int
    {
        return (int)$this->db()->selectOne(
            "SELECT gibbonScaleID
             FROM gibbonScale
             WHERE active = 'Y'
             ORDER BY (nameShort IN ('FLG', 'SLG')) DESC, name
             LIMIT 1"
        );
    }

    /**
     * Reporting values the transcript cannot place: no criterion, a deleted criterion, or no cycle.
     */
    public function selectOrphanReportingValues(): array
    {
        $sql = "SELECT gibbonReportingValue.gibbonReportingValueID,
                       gibbonReportingValue.gibbonPersonIDStudent,
                       student.surname, student.preferredName,
                       gibbonCourse.nameShort AS courseCode,
                       gibbonCourseClass.nameShort AS className,
                       gibbonSchoolYear.name AS schoolYearName,
                       gibbonReportingValue.value,
                       gibbonReportingValue.timestampModified,
                       CASE
                           WHEN gibbonReportingValue.gibbonReportingCriteriaID IS NULL THEN 'No criterion'
                           WHEN gibbonReportingCriteria.gibbonReportingCriteriaID IS NULL THEN 'Criterion deleted'
                           ELSE 'No reporting cycle'
                       END AS issue
                FROM gibbonReportingValue
                LEFT JOIN gibbonReportingCriteria ON gibbonReportingCriteria.gibbonReportingCriteriaID = gibbonReportingValue.gibbonReportingCriteriaID
                LEFT JOIN gibbonReportingCycle ON gibbonReportingCycle.gibbonReportingCycleID = gibbonReportingValue.gibbonReportingCycleID
                LEFT JOIN gibbonPerson AS student ON student.gibbonPersonID = gibbonReportingValue.gibbonPersonIDStudent
                LEFT JOIN gibbonCourseClass ON gibbonCourseClass.gibbonCourseClassID = gibbonReportingValue.gibbonCourseClassID
                LEFT JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
                LEFT JOIN gibbonSchoolYear ON gibbonSchoolYear.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
                WHERE gibbonReportingValue.gibbonReportingCriteriaID IS NULL
                OR gibbonReportingCriteria.gibbonReportingCriteriaID IS NULL
                OR gibbonReportingCycle.gibbonReportingCycleID IS NULL
                ORDER BY student.surname, student.preferredName, gibbonSchoolYear.sequenceNumber, gibbonCourse.nameShort";

        return $this->db()->select($sql)->fetchAll() ?: [];
    }

    /**
     * Students with more than one Grade Scale value for the same class and term.
     * The transcript shows only the latest; the others are listed here for review.
     */
    public function selectDuplicateGradeValues(): array
    {
        $termMatch = $this->getTermMatchSubquery('gibbonReportingCycle');

        $sql = "SELECT gibbonReportingValue.gibbonPersonIDStudent,
                       student.surname, student.preferredName,
                       gibbonCourse.nameShort AS courseCode,
                       gibbonCourseClass.nameShort AS className,
                       gibbonSchoolYear.name AS schoolYearName,
                       COALESCE(gibbonSchoolYearTerm.name, '') AS termName,
                       COUNT(*) AS valueCount,
                       GROUP_CONCAT(CONCAT(gibbonReportingCycle.name, ' / ', gibbonReportingCriteria.name, ': ', COALESCE(gibbonScaleGrade.value, gibbonReportingValue.value, ''))
                           ORDER BY gibbonReportingCycle.sequenceNumber, gibbonReportingCycle.dateEnd, gibbonReportingCriteria.sequenceNumber SEPARATOR '; ') AS grades
                FROM gibbonReportingValue
                JOIN gibbonReportingCriteria ON gibbonReportingCriteria.gibbonReportingCriteriaID = gibbonReportingValue.gibbonReportingCriteriaID
                JOIN gibbonReportingCriteriaType ON gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID = gibbonReportingCriteria.gibbonReportingCriteriaTypeID AND gibbonReportingCriteriaType.valueType = 'Grade Scale'
                JOIN gibbonReportingCycle ON gibbonReportingCycle.gibbonReportingCycleID = gibbonReportingValue.gibbonReportingCycleID
                JOIN gibbonCourseClass ON gibbonCourseClass.gibbonCourseClassID = gibbonReportingValue.gibbonCourseClassID
                JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
                JOIN gibbonSchoolYear ON gibbonSchoolYear.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
                LEFT JOIN gibbonSchoolYearTerm ON gibbonSchoolYearTerm.gibbonSchoolYearTermID = {$termMatch}
                LEFT JOIN gibbonScaleGrade ON gibbonScaleGrade.gibbonScaleGradeID = gibbonReportingValue.gibbonScaleGradeID
                LEFT JOIN gibbonPerson AS student ON student.gibbonPersonID = gibbonReportingValue.gibbonPersonIDStudent
                WHERE (gibbonReportingValue.gibbonScaleGradeID IS NOT NULL OR TRIM(COALESCE(gibbonReportingValue.value, '')) <> '')
                GROUP BY gibbonReportingValue.gibbonPersonIDStudent, gibbonReportingValue.gibbonCourseClassID, COALESCE(gibbonSchoolYearTerm.gibbonSchoolYearTermID, CONCAT('cycle', gibbonReportingCycle.gibbonReportingCycleID)),
                         student.surname, student.preferredName, gibbonCourse.nameShort, gibbonCourseClass.nameShort, gibbonSchoolYear.name, gibbonSchoolYear.sequenceNumber, gibbonSchoolYearTerm.name
                HAVING COUNT(*) > 1
                ORDER BY student.surname, student.preferredName, gibbonSchoolYear.sequenceNumber, gibbonCourse.nameShort";

        return $this->db()->select($sql)->fetchAll() ?: [];
    }

    public function getTermAliases(): array
    {
        $query = $this->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonSchoolYearTermID', 'ecclesiasticalName', 'secularAlias']);

        return $this->runSelect($query)->fetchAll() ?: [];
    }

    public function saveTermAlias(int $termID, string $ecclesiasticalName, string $secularAlias): bool
    {
        $sql = 'INSERT INTO gibbonTermAlias (gibbonSchoolYearTermID, ecclesiasticalName, secularAlias)
                VALUES (:termID, :ecc, :sec)
                ON DUPLICATE KEY UPDATE ecclesiasticalName = :ecc, secularAlias = :sec';

        return $this->db()->statement($sql, [
            'termID' => $termID,
            'ecc' => $ecclesiasticalName,
            'sec' => $secularAlias,
        ]);
    }
}
