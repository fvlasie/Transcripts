<?php

namespace Gibbon\Module\Transcripts\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class TranscriptGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonTermAlias';
    private static $primaryKey = 'gibbonTermAliasID';

    public function getStudentTranscriptRecords(int $gibbonPersonID): array
    {
        $sql = $this->getTranscriptRecordSql().'
            WHERE gibbonCourseClassPerson.gibbonPersonID = :gibbonPersonID
            AND gibbonCourseClassPerson.role = :role
            ORDER BY gibbonSchoolYear.sequenceNumber ASC,
                     gibbonSchoolYearTerm.sequenceNumber ASC,
                     gibbonCourse.nameShort ASC';

        return $this->db()->select($sql, [
            'gibbonPersonID' => $gibbonPersonID,
            'role' => 'Student',
        ])->fetchAll() ?: [];
    }

    public function getStudentTranscriptRecord(int $gibbonPersonID, int $gibbonCourseClassID, int $gibbonSchoolYearTermID = 0, int $gibbonReportingValueID = 0): ?array
    {
        $sql = $this->getTranscriptRecordSql().'
            WHERE gibbonCourseClassPerson.gibbonPersonID = :gibbonPersonID
            AND gibbonCourseClassPerson.role = :role
            AND gibbonCourseClass.gibbonCourseClassID = :gibbonCourseClassID';

        $params = [
            'gibbonPersonID' => $gibbonPersonID,
            'role' => 'Student',
            'gibbonCourseClassID' => $gibbonCourseClassID,
        ];

        if ($gibbonSchoolYearTermID > 0) {
            $sql .= ' AND gibbonSchoolYearTerm.gibbonSchoolYearTermID = :gibbonSchoolYearTermID';
            $params['gibbonSchoolYearTermID'] = $gibbonSchoolYearTermID;
        } elseif ($gibbonReportingValueID > 0) {
            $sql .= ' AND gibbonReportingValue.gibbonReportingValueID = :gibbonReportingValueID';
            $params['gibbonReportingValueID'] = $gibbonReportingValueID;
        }

        $sql .= ' LIMIT 1';

        $row = $this->db()->select($sql, $params)->fetch();

        return !empty($row) ? $row : null;
    }

    private function getTranscriptRecordSql(): string
    {
        $cycleMatch = $this->getReportingCycleMatchSubquery();

        return "SELECT
                gibbonCourseClassPerson.gibbonPersonID,
                gibbonCourseClass.gibbonCourseClassID,
                gibbonCourse.gibbonCourseID,
                gibbonCourse.gibbonSchoolYearID,
                gibbonReportingValue.gibbonReportingValueID,
                gibbonReportingValue.gibbonReportingCriteriaID,
                gibbonReportingValue.gibbonReportingCycleID,
                gibbonReportingValue.gibbonScaleGradeID,
                gibbonReportingValue.value AS reportingValue,
                gibbonReportingValue.value AS numericGrade,
                gibbonSchoolYear.name AS schoolYearName,
                gibbonSchoolYearTerm.gibbonSchoolYearTermID,
                COALESCE(gibbonSchoolYearTerm.name, gibbonReportingCycle.name) AS termName,
                gibbonTermAlias.secularAlias,
                gibbonCourse.name AS courseName,
                gibbonCourse.nameShort AS courseCode,
                gibbonCoursesAndClasses.externalCourseCode,
                COALESCE(gibbonCoursesAndClasses.credits, gibbonCourse.credits, 0) AS credits,
                gibbonCourse.courseLevel,
                gibbonCourse.modeOfInstruction,
                COALESCE(NULLIF(TRIM(gibbonScaleGrade.value), ''), NULLIF(TRIM(gibbonScaleGrade.descriptor), ''), gibbonReportingValue.value) AS letterGrade
            FROM gibbonCourseClassPerson
            INNER JOIN gibbonCourseClass ON gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
            INNER JOIN gibbonCourse ON gibbonCourseClass.gibbonCourseID = gibbonCourse.gibbonCourseID
            INNER JOIN gibbonSchoolYear ON gibbonCourse.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID
            INNER JOIN gibbonSchoolYearTerm ON gibbonSchoolYearTerm.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID
            LEFT JOIN gibbonReportingCycle ON gibbonReportingCycle.gibbonReportingCycleID = {$cycleMatch}
            LEFT JOIN gibbonReportingValue
                ON gibbonReportingValue.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
                AND gibbonReportingValue.gibbonPersonIDStudent = gibbonCourseClassPerson.gibbonPersonID
                AND gibbonReportingValue.gibbonReportingCycleID = gibbonReportingCycle.gibbonReportingCycleID
            LEFT JOIN gibbonScaleGrade ON gibbonReportingValue.gibbonScaleGradeID = gibbonScaleGrade.gibbonScaleGradeID
            LEFT JOIN gibbonTermAlias ON gibbonTermAlias.gibbonSchoolYearTermID = gibbonSchoolYearTerm.gibbonSchoolYearTermID
            LEFT JOIN gibbonCoursesAndClasses ON gibbonCoursesAndClasses.courseCode = gibbonCourse.nameShort";
    }

    private function getReportingCycleMatchSubquery(): string
    {
        return '(SELECT c.gibbonReportingCycleID
                FROM gibbonReportingCycle AS c
                WHERE c.gibbonSchoolYearID = gibbonSchoolYearTerm.gibbonSchoolYearID
                AND (
                    (c.dateStart = gibbonSchoolYearTerm.firstDay AND c.dateEnd = gibbonSchoolYearTerm.lastDay)
                    OR c.name = gibbonSchoolYearTerm.name
                    OR c.nameShort = gibbonSchoolYearTerm.nameShort
                )
                ORDER BY CASE
                    WHEN c.dateStart = gibbonSchoolYearTerm.firstDay AND c.dateEnd = gibbonSchoolYearTerm.lastDay THEN 0
                    ELSE 1
                END, c.gibbonReportingCycleID
                LIMIT 1)';
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

    public function getGradeScaleIDForGrade(?int $gibbonScaleGradeID): int
    {
        if (empty($gibbonScaleGradeID)) {
            return 0;
        }

        return (int)$this->db()->selectOne(
            "SELECT gibbonScaleID FROM gibbonScaleGrade WHERE gibbonScaleGradeID = :gibbonScaleGradeID",
            ['gibbonScaleGradeID' => $gibbonScaleGradeID]
        );
    }

    public function getGradeScaleChoices(): array
    {
        $rows = $this->db()->select(
            "SELECT gibbonScaleID, name, nameShort, active
             FROM gibbonScale
             ORDER BY (active = 'Y') DESC, (nameShort IN ('FLG', 'SLG')) DESC, name"
        )->fetchAll();

        $options = [];
        foreach ($rows as $row) {
            $label = $row['name'];
            if (!empty($row['nameShort']) && $row['nameShort'] !== $row['name']) {
                $label .= ' ('.$row['nameShort'].')';
            }
            if (($row['active'] ?? 'Y') !== 'Y') {
                $label .= ' — '.__('Inactive');
            }
            $options[$row['gibbonScaleID']] = $label;
        }

        return $options;
    }

    public function getChainedGradeScaleOptions(): array
    {
        $rows = $this->db()->select(
            "SELECT gibbonScaleGrade.gibbonScaleGradeID, gibbonScaleGrade.gibbonScaleID, gibbonScaleGrade.value, gibbonScaleGrade.descriptor
             FROM gibbonScaleGrade
             JOIN gibbonScale ON gibbonScale.gibbonScaleID = gibbonScaleGrade.gibbonScaleID
             ORDER BY gibbonScale.name, gibbonScaleGrade.sequenceNumber, gibbonScaleGrade.value"
        )->fetchAll();

        $options = [];
        $chained = [];
        foreach ($rows as $row) {
            $label = trim(($row['value'] ?? '').(!empty($row['descriptor']) && $row['descriptor'] !== $row['value'] ? ' — '.$row['descriptor'] : ''));
            $options[$row['gibbonScaleGradeID']] = $label !== '' ? $label : $row['gibbonScaleGradeID'];
            $chained[$row['gibbonScaleGradeID']] = $row['gibbonScaleID'];
        }

        return [$options, $chained];
    }

    public function getGradeScaleOptions(?int $gibbonScaleGradeID): array
    {
        if (empty($gibbonScaleGradeID)) {
            return [];
        }

        $sql = "SELECT selected.gibbonScaleID
                FROM gibbonScaleGrade AS selected
                WHERE selected.gibbonScaleGradeID = :gibbonScaleGradeID";

        $gibbonScaleID = $this->db()->selectOne($sql, ['gibbonScaleGradeID' => $gibbonScaleGradeID]);

        return $this->getGradeScaleOptionsByScaleID((int)$gibbonScaleID);
    }

    public function getReportingCyclesForClass(int $gibbonCourseClassID): array
    {
        $sql = "SELECT gibbonReportingCycle.gibbonReportingCycleID, gibbonReportingCycle.name
                FROM gibbonCourseClass
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID)
                JOIN gibbonReportingCycle ON (gibbonReportingCycle.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID)
                WHERE gibbonCourseClass.gibbonCourseClassID = :gibbonCourseClassID
                ORDER BY gibbonReportingCycle.sequenceNumber, gibbonReportingCycle.dateStart, gibbonReportingCycle.name";

        $rows = $this->db()->select($sql, ['gibbonCourseClassID' => $gibbonCourseClassID])->fetchAll();
        $options = [];
        foreach ($rows as $row) {
            $options[$row['gibbonReportingCycleID']] = $row['name'];
        }

        return $options;
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
             WHERE gibbonReportingCriteria.gibbonReportingCycleID = :gibbonReportingCycleID
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

    public function getTermNameByID(int $gibbonSchoolYearTermID): string
    {
        if ($gibbonSchoolYearTermID <= 0) {
            return '';
        }

        $name = $this->db()->selectOne(
            'SELECT name FROM gibbonSchoolYearTerm WHERE gibbonSchoolYearTermID = :gibbonSchoolYearTermID',
            ['gibbonSchoolYearTermID' => $gibbonSchoolYearTermID]
        );

        return is_string($name) ? $name : '';
    }

    public function getDefaultGradeScaleIDForClass(int $gibbonCourseClassID, int $gibbonReportingCycleID = 0): ?int
    {
        $context = $this->getReportingContextForClass($gibbonCourseClassID, $gibbonReportingCycleID);

        return !empty($context['gibbonScaleID']) ? (int)$context['gibbonScaleID'] : null;
    }

    public function getReportingContextForClass(int $gibbonCourseClassID, int $gibbonReportingCycleID = 0): ?array
    {
        $sql = "SELECT gibbonReportingCycle.gibbonReportingCycleID,
                       gibbonReportingCycle.gibbonSchoolYearID,
                       gibbonReportingCriteria.gibbonReportingCriteriaID,
                       COALESCE(gibbonReportingCriteriaType.gibbonScaleID, gibbonReportingCriteria.gibbonScaleID) AS gibbonScaleID
                FROM gibbonCourseClass
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID)
                JOIN gibbonReportingCycle ON (gibbonReportingCycle.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID)
                JOIN gibbonReportingScope ON (gibbonReportingScope.gibbonReportingCycleID = gibbonReportingCycle.gibbonReportingCycleID AND gibbonReportingScope.scopeType = 'Course')
                JOIN gibbonReportingCriteria ON (
                    gibbonReportingCriteria.gibbonReportingScopeID = gibbonReportingScope.gibbonReportingScopeID
                    AND (gibbonReportingCriteria.gibbonCourseID IS NULL OR gibbonReportingCriteria.gibbonCourseID = gibbonCourse.gibbonCourseID)
                )
                JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID = gibbonReportingCriteria.gibbonReportingCriteriaTypeID)
                WHERE gibbonCourseClass.gibbonCourseClassID = :gibbonCourseClassID
                AND gibbonReportingCriteriaType.valueType = 'Grade Scale'";

        $params = ['gibbonCourseClassID' => $gibbonCourseClassID];
        if ($gibbonReportingCycleID > 0) {
            $sql .= " AND gibbonReportingCycle.gibbonReportingCycleID = :gibbonReportingCycleID";
            $params['gibbonReportingCycleID'] = $gibbonReportingCycleID;
        }

        $sql .= " ORDER BY gibbonReportingCycle.sequenceNumber ASC, gibbonReportingCycle.gibbonReportingCycleID ASC, gibbonReportingCriteria.sequenceNumber ASC
                LIMIT 1";

        $row = $this->db()->selectOne($sql, $params);

        return !empty($row) ? $row : null;
    }

    public function getLetterGradeOptions(): array
    {
        return [
            'A+' => 'A+',
            'A' => 'A',
            'A-' => 'A-',
            'B+' => 'B+',
            'B' => 'B',
            'B-' => 'B-',
            'C+' => 'C+',
            'C' => 'C',
            'C-' => 'C-',
            'D+' => 'D+',
            'D' => 'D',
            'F' => 'F',
        ];
    }

    public function getGradeScaleValueByID(int $gibbonScaleGradeID): ?string
    {
        if ($gibbonScaleGradeID <= 0) {
            return null;
        }

        $sql = "SELECT value FROM gibbonScaleGrade WHERE gibbonScaleGradeID=:gibbonScaleGradeID";
        $value = $this->db()->selectOne($sql, ['gibbonScaleGradeID' => $gibbonScaleGradeID]);

        return $value !== false && $value !== null ? (string) $value : null;
    }

    public function saveReportingGrade(array $record, ?int $gibbonScaleGradeID, ?string $value, int $gibbonPersonID): ?int
    {
        $existingID = (int)($record['gibbonReportingValueID'] ?? 0);
        $cycleID = (int)($record['gibbonReportingCycleID'] ?? 0);

        if ($existingID <= 0 && $cycleID > 0) {
            $existingID = (int)$this->db()->selectOne(
                "SELECT gibbonReportingValueID
                 FROM gibbonReportingValue
                 WHERE gibbonCourseClassID = :gibbonCourseClassID
                 AND gibbonPersonIDStudent = :gibbonPersonIDStudent
                 AND gibbonReportingCycleID = :gibbonReportingCycleID
                 ORDER BY gibbonReportingValueID DESC
                 LIMIT 1",
                [
                    'gibbonCourseClassID' => $record['gibbonCourseClassID'],
                    'gibbonPersonIDStudent' => $record['gibbonPersonID'],
                    'gibbonReportingCycleID' => $cycleID,
                ]
            );
        }

        if ($existingID > 0) {
            $updated = $this->updateReportingGrade($existingID, $gibbonScaleGradeID, $value, $gibbonPersonID, $cycleID);

            return $updated ? $existingID : null;
        }

        $context = $this->getReportingContextForClass(
            (int)$record['gibbonCourseClassID'],
            $cycleID
        );
        if (empty($context)) {
            $context = [
                'gibbonReportingCycleID' => $cycleID > 0 ? $cycleID : null,
                'gibbonReportingCriteriaID' => null,
                'gibbonSchoolYearID' => $record['gibbonSchoolYearID'] ?? null,
            ];
        }

        $sql = "INSERT INTO gibbonReportingValue
                    (gibbonReportingCycleID, gibbonReportingCriteriaID, gibbonSchoolYearID, gibbonCourseClassID, gibbonPersonIDStudent, gibbonScaleGradeID, value, gibbonPersonIDCreated, timestampCreated, gibbonPersonIDModified, timestampModified)
                VALUES
                    (:gibbonReportingCycleID, :gibbonReportingCriteriaID, :gibbonSchoolYearID, :gibbonCourseClassID, :gibbonPersonIDStudent, :gibbonScaleGradeID, :value, :gibbonPersonIDCreated, :timestampCreated, :gibbonPersonIDModified, :timestampModified)";

        $now = date('Y-m-d H:i:s');
        $inserted = $this->db()->insert($sql, [
            'gibbonReportingCycleID' => $cycleID > 0 ? $cycleID : ($context['gibbonReportingCycleID'] ?? null),
            'gibbonReportingCriteriaID' => $context['gibbonReportingCriteriaID'],
            'gibbonSchoolYearID' => $context['gibbonSchoolYearID'] ?? $record['gibbonSchoolYearID'],
            'gibbonCourseClassID' => $record['gibbonCourseClassID'],
            'gibbonPersonIDStudent' => $record['gibbonPersonID'],
            'gibbonScaleGradeID' => $gibbonScaleGradeID,
            'value' => $value,
            'gibbonPersonIDCreated' => $gibbonPersonID,
            'timestampCreated' => $now,
            'gibbonPersonIDModified' => $gibbonPersonID,
            'timestampModified' => $now,
        ]);

        return !empty($inserted) ? (int) $inserted : null;
    }

    public function updateReportingGrade(int $gibbonReportingValueID, ?int $gibbonScaleGradeID, ?string $value, int $gibbonPersonIDModified, int $gibbonReportingCycleID = 0): bool
    {
        $sql = "UPDATE gibbonReportingValue
                SET gibbonScaleGradeID = :gibbonScaleGradeID,
                    value = :value,
                    gibbonPersonIDModified = :gibbonPersonIDModified,
                    timestampModified = :timestampModified";
        $params = [
            'gibbonScaleGradeID' => $gibbonScaleGradeID,
            'value' => $value,
            'gibbonPersonIDModified' => $gibbonPersonIDModified,
            'timestampModified' => date('Y-m-d H:i:s'),
            'gibbonReportingValueID' => $gibbonReportingValueID,
        ];

        if ($gibbonReportingCycleID > 0) {
            $sql .= ", gibbonReportingCycleID = :gibbonReportingCycleID";
            $params['gibbonReportingCycleID'] = $gibbonReportingCycleID;
        }

        $sql .= " WHERE gibbonReportingValueID = :gibbonReportingValueID";

        return $this->db()->update($sql, $params);
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
