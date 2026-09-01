<?php

namespace Gibbon\Module\Transcripts\Domain;

use Gibbon\Domain\DataSet;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class CourseProgramGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonTranscriptsCourseProgram';
    private static $primaryKey = 'gibbonTranscriptsCourseProgramID';
    private static $searchableColumns = ['gibbonCourse.nameShort', 'gibbonCourse.name'];

    public function queryCourses(QueryCriteria $criteria, int $gibbonSchoolYearID): DataSet
    {
        $query = $this->newQuery()
            ->from('gibbonCourse')
            ->cols([
                'gibbonCourse.gibbonCourseID',
                'gibbonCourse.nameShort AS courseCode',
                'gibbonCourse.name AS courseName',
                'gibbonCourse.courseLevel',
                "GROUP_CONCAT(DISTINCT gibbonTranscriptsCourseProgram.programType ORDER BY gibbonTranscriptsCourseProgram.programType SEPARATOR ', ') AS programs",
            ])
            ->leftJoin('gibbonTranscriptsCourseProgram', 'gibbonTranscriptsCourseProgram.courseCode = gibbonCourse.nameShort')
            ->where('gibbonCourse.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['gibbonCourse.gibbonCourseID'])
            ->orderBy(['gibbonCourse.nameShort', 'gibbonCourse.name']);

        $criteria->addFilterRules([
            'programType' => function ($query, $programType) {
                return $query
                    ->where('gibbonTranscriptsCourseProgram.programType = :filterProgramType')
                    ->bindValue('filterProgramType', $programType);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function getCourseByID(int $gibbonCourseID): ?array
    {
        $sql = "SELECT gibbonCourse.gibbonCourseID, gibbonCourse.nameShort AS courseCode, gibbonCourse.name AS courseName, gibbonCourse.courseLevel, gibbonCourse.gibbonSchoolYearID
                FROM gibbonCourse
                WHERE gibbonCourse.gibbonCourseID = :gibbonCourseID";

        $row = $this->db()->selectOne($sql, ['gibbonCourseID' => $gibbonCourseID]);

        return !empty($row) ? $row : null;
    }

    public function getCourseSelectBySchoolYear(int $gibbonSchoolYearID): array
    {
        $sql = "SELECT gibbonCourseID, CONCAT(nameShort, ' - ', name) AS label
                FROM gibbonCourse
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                ORDER BY nameShort, name";

        $rows = $this->db()->select($sql, ['gibbonSchoolYearID' => $gibbonSchoolYearID])->fetchAll();

        return array_column($rows, 'label', 'gibbonCourseID');
    }

    public function getProgramTypesByCourseCode(string $courseCode): array
    {
        $sql = "SELECT programType
                FROM gibbonTranscriptsCourseProgram
                WHERE courseCode = :courseCode
                ORDER BY programType";

        $rows = $this->db()->select($sql, ['courseCode' => $courseCode])->fetchAll();

        return array_column($rows, 'programType');
    }

    public function getCourseCodesByProgramType(string $programType): array
    {
        $sql = "SELECT DISTINCT courseCode
                FROM gibbonTranscriptsCourseProgram
                WHERE programType = :programType
                ORDER BY courseCode";

        $rows = $this->db()->select($sql, ['programType' => $programType])->fetchAll();

        return array_column($rows, 'courseCode');
    }

    public function replaceProgramsForCourseCode(string $courseCode, array $programTypes): bool
    {
        $this->db()->delete(
            'DELETE FROM gibbonTranscriptsCourseProgram WHERE courseCode = :courseCode',
            ['courseCode' => $courseCode]
        );

        $programTypes = array_values(array_unique(array_filter($programTypes)));
        foreach ($programTypes as $programType) {
            $this->insert([
                'courseCode' => $courseCode,
                'programType' => $programType,
            ]);
        }

        return true;
    }
}
