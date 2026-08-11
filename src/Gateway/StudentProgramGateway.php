<?php

namespace Gibbon\Module\Transcripts\Gateway;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

/**
 * Gateway for accessing and updating student program history, program dates, and metadata.
 */
class StudentProgramGateway extends QueryableGateway
{
    use TableAware;

    protected static string $tableName = 'gibbonStudentProgramHistory';
    protected static string $primaryKey = 'gibbonStudentProgramHistoryID';

    public function getActiveProgramByPerson(int $gibbonPersonID): ?array
    {
        $this->ensureTablesExist();

        $query = $this->newSelect()
            ->from(static::$tableName)
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
        $this->ensureTablesExist();

        $query = $this->newSelect()
            ->from(static::$tableName)
            ->cols(['*'])
            ->where('gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['startDate ASC']);

        return $this->runSelect($query)->fetchAll() ?: [];
    }

    public function addProgramHistory(array $data): int
    {
        return $this->insert($data);
    }

    public function updateProgramHistory(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    private function ensureTablesExist(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `gibbonStudentProgramHistory` (
            `gibbonStudentProgramHistoryID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `gibbonPersonID` INT(10) UNSIGNED NOT NULL,
            `programType` ENUM('MTS', 'BTh', 'Iconography', 'Iconology', 'Gap-Year', 'Non-Degree') NOT NULL,
            `concentration` ENUM('Biblical', 'Professional', 'General', 'Certificate', 'Masters') DEFAULT 'General',
            `studentLevel` ENUM('Freshman', 'Sophomore', 'Junior', 'Senior', 'M1', 'M2', 'M3') DEFAULT NULL,
            `startDate` DATE NOT NULL,
            `switchDate` DATE DEFAULT NULL,
            `graduationDate` DATE DEFAULT NULL,
            `status` ENUM('Active', 'Switched', 'Graduated', 'Withdrawn', 'On Leave') NOT NULL DEFAULT 'Active',
            `notes` TEXT DEFAULT NULL,
            PRIMARY KEY (`gibbonStudentProgramHistoryID`),
            KEY `gibbonPersonID` (`gibbonPersonID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $this->db()->statement($sql);
    }
}
