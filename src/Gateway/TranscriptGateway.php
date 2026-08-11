<?php

namespace Gibbon\Module\Transcripts\Gateway;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

/**
 * Gateway for retrieving student transcript records and term aliases.
 */
class TranscriptGateway extends QueryableGateway
{
    use TableAware;

    protected static string $tableName = 'gibbonTermAlias';
    protected static string $primaryKey = 'gibbonTermAliasID';
    public function getStudentTranscriptRecords(int $gibbonPersonID): array
    {
        $this->ensureTablesExist();

        $query = $this->newSelect()
            ->from('gibbonCourseClassPerson')
            ->innerJoin('gibbonCourseClass', 'gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID')
            ->innerJoin('gibbonCourse', 'gibbonCourseClass.gibbonCourseID = gibbonCourse.gibbonCourseID')
            ->innerJoin('gibbonSchoolYear', 'gibbonCourse.gibbonSchoolYearID = gibbonSchoolYear.gibbonSchoolYearID')
            ->leftJoin('gibbonSchoolYearTerm', 'gibbonSchoolYear.gibbonSchoolYearID = gibbonSchoolYearTerm.gibbonSchoolYearID')
            ->leftJoin('gibbonTermAlias', 'gibbonSchoolYearTerm.gibbonSchoolYearTermID = gibbonTermAlias.gibbonSchoolYearTermID')
            ->leftJoin('gibbonReportingValue', 'gibbonCourseClassPerson.gibbonCourseClassID = gibbonReportingValue.gibbonCourseClassID AND gibbonCourseClassPerson.gibbonPersonID = gibbonReportingValue.gibbonPersonIDStudent')
            ->leftJoin('gibbonScaleGrade', 'gibbonReportingValue.gibbonScaleGradeID = gibbonScaleGrade.gibbonScaleGradeID')
            ->cols([
                'gibbonCourseClassPerson.gibbonPersonID',
                'gibbonSchoolYear.name AS schoolYearName',
                'gibbonSchoolYearTerm.name AS termName',
                'gibbonTermAlias.secularAlias',
                'gibbonCourse.name AS courseName',
                'gibbonCourse.nameShort AS courseCode',
                'gibbonCourse.credits',
                'gibbonCourse.courseLevel',
                'gibbonCourse.modeOfInstruction',
                'COALESCE(gibbonScaleGrade.value, gibbonReportingValue.value) AS letterGrade',
                'gibbonReportingValue.value AS numericGrade'
            ])
            ->where('gibbonCourseClassPerson.gibbonPersonID = :gibbonPersonID')
            ->where('gibbonCourseClassPerson.role = :role')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->bindValue('role', 'Student')
            ->orderBy(['gibbonSchoolYear.sequenceNumber ASC', 'gibbonCourse.nameShort ASC']);

        return $this->runSelect($query)->fetchAll() ?: [];
    }

    public function getTermAliases(): array
    {
        $this->ensureTablesExist();

        $query = $this->newSelect()
            ->from('gibbonTermAlias')
            ->cols(['gibbonSchoolYearTermID', 'ecclesiasticalName', 'secularAlias']);

        return $this->runSelect($query)->fetchAll() ?: [];
    }

    public function saveTermAlias(int $termID, string $ecclesiasticalName, string $secularAlias): bool
    {
        $this->ensureTablesExist();

        $sql = "INSERT INTO gibbonTermAlias (gibbonSchoolYearTermID, ecclesiasticalName, secularAlias)
                VALUES (:termID, :ecc, :sec)
                ON DUPLICATE KEY UPDATE ecclesiasticalName = :ecc, secularAlias = :sec";

        return $this->db()->statement($sql, [
            'termID' => $termID,
            'ecc' => $ecclesiasticalName,
            'sec' => $secularAlias
        ]);
    }

    private function ensureTablesExist(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `gibbonTermAlias` (
            `gibbonTermAliasID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `gibbonSchoolYearTermID` INT(10) UNSIGNED NOT NULL,
            `ecclesiasticalName` VARCHAR(50) NOT NULL COMMENT 'e.g. Pascha, Nativity, Pentecost',
            `secularAlias` VARCHAR(50) NOT NULL COMMENT 'e.g. Spring Term, Fall Term, Summer Term',
            `notes` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`gibbonTermAliasID`),
            UNIQUE KEY `gibbonSchoolYearTermID` (`gibbonSchoolYearTermID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->db()->statement($sql);

        // Ensure custom columns on core gibbonCourse exist
        $cols = ['modeOfInstruction', 'courseLevel', 'credits'];
        foreach ($cols as $col) {
            try {
                $check = $this->db()->selectOne("SHOW COLUMNS FROM `gibbonCourse` LIKE '$col'");
                if (empty($check)) {
                    if ($col === 'modeOfInstruction') {
                        $this->db()->statement("ALTER TABLE `gibbonCourse` ADD COLUMN `modeOfInstruction` ENUM('In-person', 'Remote') NOT NULL DEFAULT 'In-person'");
                    } elseif ($col === 'courseLevel') {
                        $this->db()->statement("ALTER TABLE `gibbonCourse` ADD COLUMN `courseLevel` ENUM('BTh', 'MTS', 'Certificate', 'Non-Degree') NOT NULL DEFAULT 'BTh'");
                    } elseif ($col === 'credits') {
                        $this->db()->statement("ALTER TABLE `gibbonCourse` ADD COLUMN `credits` DECIMAL(4,2) NOT NULL DEFAULT 0.00");
                    }
                }
            } catch (\Exception $e) {
                // Ignore if column already exists
            }
        }
    }
}
