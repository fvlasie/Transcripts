<?php

namespace Gibbon\Module\Transcripts\Gateway;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

/**
 * Gateway for complex cross-sectional registrar analytical queries and filters.
 */
class RegistrarQueryGateway extends QueryableGateway
{
    use TableAware;

    protected static string $tableName = 'gibbonPerson';
    protected static string $primaryKey = 'gibbonPersonID';
    public function queryStudentRecords(array $filters = [], array $sorting = []): array
    {
        $query = $this->newSelect()
            ->from('gibbonPerson')
            ->leftJoin('gibbonStudentProgramHistory', 'gibbonPerson.gibbonPersonID = gibbonStudentProgramHistory.gibbonPersonID AND gibbonStudentProgramHistory.status = "Active"')
            ->leftJoin('gibbonCourseClassPerson', 'gibbonPerson.gibbonPersonID = gibbonCourseClassPerson.gibbonPersonID AND gibbonCourseClassPerson.role = "Student"')
            ->leftJoin('gibbonCourseClass', 'gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID')
            ->leftJoin('gibbonCourse', 'gibbonCourseClass.gibbonCourseID = gibbonCourse.gibbonCourseID')
            ->cols([
                'gibbonPerson.gibbonPersonID',
                'gibbonPerson.surname',
                'gibbonPerson.firstName',
                'gibbonPerson.gender',
                'gibbonStudentProgramHistory.programType',
                'gibbonStudentProgramHistory.concentration',
                'gibbonStudentProgramHistory.studentLevel',
                'gibbonStudentProgramHistory.startDate AS programStartDate',
                'gibbonStudentProgramHistory.graduationDate',
                'gibbonCourse.courseLevel',
                'gibbonCourse.modeOfInstruction',
                'gibbonCourse.name AS courseName',
                'gibbonCourse.nameShort AS courseCode'
            ])
            ->where('gibbonPerson.status = "Full"')
            ->where('gibbonPerson.gibbonRoleIDPrimary = (SELECT gibbonRoleID FROM gibbonRole WHERE category = "Student" LIMIT 1)');

        // Apply filters
        if (!empty($filters['programType'])) {
            $query->where('gibbonStudentProgramHistory.programType = :programType')
                  ->bindValue('programType', $filters['programType']);
        }

        if (!empty($filters['concentration'])) {
            $query->where('gibbonStudentProgramHistory.concentration = :concentration')
                  ->bindValue('concentration', $filters['concentration']);
        }

        if (!empty($filters['studentLevel'])) {
            $query->where('gibbonStudentProgramHistory.studentLevel = :studentLevel')
                  ->bindValue('studentLevel', $filters['studentLevel']);
        }

        if (!empty($filters['modeOfInstruction'])) {
            $query->where('gibbonCourse.modeOfInstruction = :modeOfInstruction')
                  ->bindValue('modeOfInstruction', $filters['modeOfInstruction']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gibbonPerson.gender = :gender')
                  ->bindValue('gender', $filters['gender']);
        }

        // Apply sorting
        if (!empty($sorting)) {
            $query->orderBy($sorting);
        } else {
            $query->orderBy(['gibbonPerson.surname ASC', 'gibbonPerson.firstName ASC']);
        }

        return $this->runSelect($query)->fetchAll() ?: [];
    }
}
