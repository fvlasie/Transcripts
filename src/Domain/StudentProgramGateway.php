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

    /**
     * Dates to offer on a new program record. Start is the day after a previous program ended, else the
     * first day of the student's earliest school year, else the start date on their person record, else
     * the current school year's first day. Graduation is the last day of their final school year, and only
     * when they are not enrolled in the current year. Both are suggestions; the form leaves them editable.
     *
     * @return array{startDate: ?string, graduationDate: ?string, startSource: string, graduationSource: string}
     */
    public function suggestProgramDates(int $gibbonPersonID): array
    {
        $suggested = ['startDate' => null, 'graduationDate' => null, 'startSource' => '', 'graduationSource' => ''];
        if ($gibbonPersonID <= 0) {
            return $suggested;
        }

        $previousEnd = null;
        foreach ($this->getAllProgramsByPerson($gibbonPersonID) as $program) {
            foreach (['switchDate', 'graduationDate'] as $field) {
                $value = $program[$field] ?? '';
                if ($value !== '' && $value !== '0000-00-00' && ($previousEnd === null || $value > $previousEnd)) {
                    $previousEnd = $value;
                }
            }
        }

        $enrolment = $this->db()->selectOne(
            "SELECT MIN(gibbonSchoolYear.firstDay) AS firstDay, MAX(gibbonSchoolYear.lastDay) AS lastDay,
                    SUM(gibbonSchoolYear.status = 'Current') AS currentYears
             FROM gibbonStudentEnrolment
             JOIN gibbonSchoolYear ON gibbonSchoolYear.gibbonSchoolYearID = gibbonStudentEnrolment.gibbonSchoolYearID
             WHERE gibbonStudentEnrolment.gibbonPersonID = :gibbonPersonID",
            ['gibbonPersonID' => $gibbonPersonID]
        ) ?: [];

        if ($previousEnd !== null) {
            $suggested['startDate'] = date('Y-m-d', strtotime($previousEnd.' +1 day'));
            $suggested['startSource'] = __('The day after their previous program ended.');
        } elseif (!empty($enrolment['firstDay']) && $enrolment['firstDay'] !== '0000-00-00') {
            $suggested['startDate'] = $enrolment['firstDay'];
            $suggested['startSource'] = __('The first day of their earliest school year.');
        } else {
            $personStart = $this->db()->selectOne(
                'SELECT dateStart FROM gibbonPerson WHERE gibbonPersonID = :gibbonPersonID',
                ['gibbonPersonID' => $gibbonPersonID]
            );
            if (!empty($personStart) && $personStart !== '0000-00-00') {
                $suggested['startDate'] = $personStart;
                $suggested['startSource'] = __('The start date on their person record.');
            }
        }

        if (empty($suggested['startDate'])) {
            $currentStart = $this->db()->selectOne(
                "SELECT firstDay FROM gibbonSchoolYear WHERE status = 'Current' ORDER BY sequenceNumber DESC LIMIT 1"
            );
            if (!empty($currentStart) && $currentStart !== '0000-00-00') {
                $suggested['startDate'] = $currentStart;
                $suggested['startSource'] = __('The first day of the current school year.');
            }
        }

        if (!empty($enrolment['lastDay']) && $enrolment['lastDay'] !== '0000-00-00' && (int)($enrolment['currentYears'] ?? 0) === 0) {
            $suggested['graduationDate'] = $enrolment['lastDay'];
            $suggested['graduationSource'] = __('The last day of their final school year. They are not enrolled in the current year.');
        }

        return $suggested;
    }

    /**
     * Program names with how many student records use each one.
     */
    public function getProgramTypeUsage(): array
    {
        return $this->db()->select(
            'SELECT gibbonTranscriptProgram.name, gibbonTranscriptProgram.sequenceNumber,
                    COUNT(gibbonStudentProgramHistory.gibbonStudentProgramHistoryID) AS records
             FROM gibbonTranscriptProgram
             LEFT JOIN gibbonStudentProgramHistory ON gibbonStudentProgramHistory.programType = gibbonTranscriptProgram.name
             GROUP BY gibbonTranscriptProgram.gibbonTranscriptProgramID, gibbonTranscriptProgram.name, gibbonTranscriptProgram.sequenceNumber
             ORDER BY gibbonTranscriptProgram.sequenceNumber, gibbonTranscriptProgram.name'
        )->fetchAll() ?: [];
    }

    /**
     * @throws \InvalidArgumentException invalid or duplicate
     */
    public function programTypeExists(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $existing = $this->db()->selectOne(
            'SELECT name FROM gibbonTranscriptProgram WHERE name = :name',
            ['name' => $name]
        );

        return !empty($existing);
    }

    public function addProgramType(string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 30 || strpos($name, ',') !== false) {
            throw new \InvalidArgumentException('invalid');
        }

        $existing = $this->db()->selectOne(
            'SELECT name FROM gibbonTranscriptProgram WHERE name = :name',
            ['name' => $name]
        );
        if (!empty($existing)) {
            throw new \InvalidArgumentException('duplicate');
        }

        $sequence = (int) $this->db()->selectOne('SELECT COALESCE(MAX(sequenceNumber), 0) + 1 FROM gibbonTranscriptProgram');
        $this->db()->insert(
            'INSERT INTO gibbonTranscriptProgram (name, sequenceNumber) VALUES (:name, :sequenceNumber)',
            ['name' => $name, 'sequenceNumber' => $sequence]
        );
    }

    /**
     * Removes a program name that no student record uses. Returns false when it is in use or missing.
     */
    public function deleteProgramType(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $used = (int) $this->db()->selectOne(
            'SELECT COUNT(*) FROM gibbonStudentProgramHistory WHERE programType = :name',
            ['name' => $name]
        );
        if ($used > 0) {
            return false;
        }

        return $this->db()->delete(
            'DELETE FROM gibbonTranscriptProgram WHERE name = :name',
            ['name' => $name]
        ) > 0;
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
