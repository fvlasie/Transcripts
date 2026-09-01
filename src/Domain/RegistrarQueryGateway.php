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
            ->leftJoin('gibbonCourseClassPerson', 'gibbonPerson.gibbonPersonID = gibbonCourseClassPerson.gibbonPersonID AND gibbonCourseClassPerson.role = "Student"')
            ->leftJoin('gibbonCourseClass', 'gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID')
            ->leftJoin('gibbonCourse', 'gibbonCourseClass.gibbonCourseID = gibbonCourse.gibbonCourseID')
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
                'gibbonStudentProgramHistory.concentration',
                'gibbonStudentProgramHistory.studentLevel',
                'gibbonStudentProgramHistory.startDate AS programStartDate',
                'gibbonStudentProgramHistory.graduationDate',
                'gibbonCourse.courseLevel',
                'gibbonCourse.modeOfInstruction',
                'gibbonCourse.name AS courseName',
                'gibbonCourse.nameShort AS courseCode',
            ])
            ->leftJoin('gibbonStudentProgramHistory', 'gibbonPerson.gibbonPersonID = gibbonStudentProgramHistory.gibbonPersonID AND gibbonStudentProgramHistory.status = "Active"')
            ->leftJoin('gibbonCourseClassPerson', 'gibbonPerson.gibbonPersonID = gibbonCourseClassPerson.gibbonPersonID AND gibbonCourseClassPerson.role = "Student"')
            ->leftJoin('gibbonCourseClass', 'gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID')
            ->leftJoin('gibbonCourse', 'gibbonCourseClass.gibbonCourseID = gibbonCourse.gibbonCourseID')
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
                    ->where('gibbonStudentProgramHistory.concentration = :concentration')
                    ->bindValue('concentration', $concentration);
            },
            'studentLevel' => function ($query, $studentLevel) {
                return $query
                    ->where('gibbonStudentProgramHistory.studentLevel = :studentLevel')
                    ->bindValue('studentLevel', $studentLevel);
            },
            'modeOfInstruction' => function ($query, $modeOfInstruction) {
                return $query
                    ->where('gibbonCourse.modeOfInstruction = :modeOfInstruction')
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
}
