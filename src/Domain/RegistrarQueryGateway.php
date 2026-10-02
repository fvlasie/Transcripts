<?php

namespace Gibbon\Module\Transcripts\Domain;

use Aura\SqlQuery\Common\SelectInterface;
use Gibbon\Domain\DataSet;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class RegistrarQueryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonPerson';
    private static $primaryKey = 'gibbonPersonID';
    private static $searchableColumns = ['gibbonPerson.surname', 'gibbonPerson.preferredName', 'gibbonPerson.firstName', 'gibbonCourse.name', 'gibbonCourse.nameShort'];

    private $schemaCache = [];

    public function selectLearningAreas(): array
    {
        $rows = $this->db()->select(
            "SELECT name FROM gibbonDepartment WHERE type = 'Learning Area' AND name <> '' ORDER BY name"
        )->fetchAll() ?: [];
        $names = array_column($rows, 'name');

        return array_combine($names, $names) ?: [];
    }

    public function queryStudentRecords(QueryCriteria $criteria): DataSet
    {
        $criteria->addFilterRules($this->getFilterRules());

        $query = $this->buildStudentRecordsQuery();

        return $this->runQuery($query, $criteria);
    }

    public function countDistinctStudents(QueryCriteria $criteria): int
    {
        $query = $this->newSelect()
            ->from('gibbonPerson')
            ->cols(['COUNT(DISTINCT gibbonPerson.gibbonPersonID) AS studentCount'])
            ->leftJoin('gibbonStudentProgramHistory', 'gibbonPerson.gibbonPersonID = gibbonStudentProgramHistory.gibbonPersonID AND gibbonStudentProgramHistory.status = "Active"')
            ->leftJoin('gibbonCourseClassPerson', 'gibbonPerson.gibbonPersonID = gibbonCourseClassPerson.gibbonPersonID AND gibbonCourseClassPerson.role = "Student" AND gibbonCourseClassPerson.reportable = "Y"')
            ->leftJoin('gibbonCourseClass', 'gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClass.reportable = "Y"')
            ->leftJoin('gibbonCourse', 'gibbonCourseClass.gibbonCourseID = gibbonCourse.gibbonCourseID')
            ->leftJoin('gibbonDepartment', 'gibbonDepartment.gibbonDepartmentID = gibbonCourse.gibbonDepartmentID AND gibbonDepartment.type = "Learning Area"')
            ->where('gibbonPerson.status = "Full"')
            ->where('gibbonPerson.gibbonRoleIDPrimary = (SELECT gibbonRoleID FROM gibbonRole WHERE category = "Student" LIMIT 1)');

        $this->applyFilters($query, $criteria);

        $result = $this->runSelect($query)->fetch();

        return (int)($result['studentCount'] ?? 0);
    }

    private function buildStudentRecordsQuery(): SelectInterface
    {
        return $this->newQuery()
            ->from('gibbonPerson')
            ->cols([
                'gibbonPerson.gibbonPersonID',
                'gibbonPerson.surname',
                'gibbonPerson.firstName',
                'gibbonPerson.preferredName',
                'gibbonPerson.gender',
                'gibbonStudentProgramHistory.programType',
                'gibbonDepartment.name AS concentration',
                'gibbonStudentProgramHistory.startDate AS programStartDate',
                'gibbonStudentProgramHistory.graduationDate',
                'gibbonCourse.courseLevel',
                $this->instructionModeExpression().' AS modeOfInstruction',
                'gibbonCourse.name AS courseName',
                'gibbonCourse.nameShort AS courseCode',
            ])
            ->leftJoin('gibbonStudentProgramHistory', 'gibbonPerson.gibbonPersonID = gibbonStudentProgramHistory.gibbonPersonID AND gibbonStudentProgramHistory.status = "Active"')
            ->leftJoin('gibbonCourseClassPerson', 'gibbonPerson.gibbonPersonID = gibbonCourseClassPerson.gibbonPersonID AND gibbonCourseClassPerson.role = "Student" AND gibbonCourseClassPerson.reportable = "Y"')
            ->leftJoin('gibbonCourseClass', 'gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClass.reportable = "Y"')
            ->leftJoin('gibbonCourse', 'gibbonCourseClass.gibbonCourseID = gibbonCourse.gibbonCourseID')
            ->leftJoin('gibbonDepartment', 'gibbonDepartment.gibbonDepartmentID = gibbonCourse.gibbonDepartmentID AND gibbonDepartment.type = "Learning Area"')
            ->where('gibbonPerson.status = "Full"')
            ->where('gibbonPerson.gibbonRoleIDPrimary = (SELECT gibbonRoleID FROM gibbonRole WHERE category = "Student" LIMIT 1)')
            ->orderBy(['gibbonPerson.surname ASC', 'gibbonPerson.preferredName ASC', 'gibbonCourse.nameShort ASC']);
    }

    private function getFilterRules(): array
    {
        return [
            'programType' => function ($query, $programType) {
                return $query
                    ->where('gibbonStudentProgramHistory.programType = :programType')
                    ->bindValue('programType', $programType);
            },
            'concentration' => function ($query, $concentration) {
                return $query
                    ->where('gibbonDepartment.name = :concentration')
                    ->bindValue('concentration', $concentration);
            },
            'modeOfInstruction' => function ($query, $modeOfInstruction) {
                return $query
                    ->where($this->instructionModeExpression().' = :modeOfInstruction')
                    ->bindValue('modeOfInstruction', $modeOfInstruction);
            },
            'gender' => function ($query, $gender) {
                return $query
                    ->where('gibbonPerson.gender = :gender')
                    ->bindValue('gender', $gender);
            },
        ];
    }

    private function applyFilters(SelectInterface $query, QueryCriteria $criteria): SelectInterface
    {
        $rules = $this->getFilterRules();

        foreach ($criteria->getFilterBy() as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (isset($rules[$name])) {
                $rules[$name]($query, $value);
            } elseif ($callback = $criteria->getFilterRule($name)) {
                $callback($query, $value);
            }
        }

        return $query;
    }

    /**
     * The student's mode for the class's first billed term. Without a class-term map, Remote only when every term that year is Remote.
     */
    private function instructionModeExpression(): string
    {
        if (!$this->tableExists('gibbonStudentInstructionMode')) {
            return "'In-person'";
        }

        if ($this->tableExists('gibbonTuitionClassTerm')) {
            return "COALESCE(
                (SELECT mode.modeOfInstruction
                FROM gibbonStudentInstructionMode AS mode
                WHERE mode.gibbonPersonID = gibbonPerson.gibbonPersonID
                AND mode.gibbonSchoolYearTermID = (
                    SELECT classTerm.gibbonSchoolYearTermID
                    FROM gibbonTuitionClassTerm AS classTerm
                    JOIN gibbonSchoolYearTerm AS classTermYear ON classTermYear.gibbonSchoolYearTermID = classTerm.gibbonSchoolYearTermID
                    WHERE classTerm.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
                    ORDER BY classTermYear.sequenceNumber, classTermYear.firstDay
                    LIMIT 1
                )),
                'In-person')";
        }

        return "CASE
            WHEN (
                SELECT COUNT(*) FROM gibbonSchoolYearTerm AS termYear
                WHERE termYear.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
            ) > 0
            AND (
                SELECT COUNT(*) FROM gibbonSchoolYearTerm AS termYear
                WHERE termYear.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
            ) = (
                SELECT COUNT(*)
                FROM gibbonStudentInstructionMode AS mode
                JOIN gibbonSchoolYearTerm AS termYear ON termYear.gibbonSchoolYearTermID = mode.gibbonSchoolYearTermID
                WHERE mode.gibbonPersonID = gibbonPerson.gibbonPersonID
                AND termYear.gibbonSchoolYearID = gibbonCourse.gibbonSchoolYearID
                AND mode.modeOfInstruction = 'Remote'
            )
            THEN 'Remote'
            ELSE 'In-person'
        END";
    }

    private function tableExists(string $table): bool
    {
        if (!isset($this->schemaCache[$table])) {
            try {
                $this->schemaCache[$table] = !empty($this->db()->selectOne('SHOW TABLES LIKE :table', ['table' => $table]));
            } catch (\Exception $e) {
                $this->schemaCache[$table] = false;
            }
        }

        return $this->schemaCache[$table];
    }
}
