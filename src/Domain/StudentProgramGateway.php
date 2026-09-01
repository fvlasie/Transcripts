<?php

namespace Gibbon\Module\Transcripts\Domain;

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\DataSet;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class StudentProgramGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStudentProgramHistory';
    private static $primaryKey = 'gibbonStudentProgramHistoryID';
    private static $searchableColumns = ['gibbonPerson.surname', 'gibbonPerson.preferredName', 'gibbonStudentProgramHistory.programType'];

    public function getActiveProgramByPerson(int $gibbonPersonID): ?array
    {
        $query = $this->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID = :gibbonPersonID')
            ->where('status = :status')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->bindValue('status', 'Active')
            ->orderBy(['startDate DESC']);

        return $this->runSelect($query)->fetch() ?: null;
    }

    public function getAllProgramsByPerson(int $gibbonPersonID): array
    {
        $query = $this->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['startDate ASC']);

        return $this->runSelect($query)->fetchAll() ?: [];
    }

    public function queryAllPrograms(QueryCriteria $criteria): DataSet
    {
        $query = $this->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStudentProgramHistory.gibbonStudentProgramHistoryID',
                'gibbonStudentProgramHistory.gibbonPersonID',
                'gibbonStudentProgramHistory.programType',
                'gibbonStudentProgramHistory.concentration',
                'gibbonStudentProgramHistory.studentLevel',
                'gibbonStudentProgramHistory.startDate',
                'gibbonStudentProgramHistory.switchDate',
                'gibbonStudentProgramHistory.graduationDate',
                'gibbonStudentProgramHistory.status',
                'gibbonStudentProgramHistory.notes',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStudentProgramHistory.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->orderBy(['gibbonStudentProgramHistory.startDate DESC', 'gibbonPerson.surname', 'gibbonPerson.preferredName']);

        $criteria->addFilterRules([
            'gibbonPersonID' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonStudentProgramHistory.gibbonPersonID = :gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function addProgramHistory(array $data): int
    {
        return $this->insert($data);
    }

    public function updateProgramHistory(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }
}
