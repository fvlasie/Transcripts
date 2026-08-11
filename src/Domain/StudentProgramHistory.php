<?php

namespace Gibbon\Module\Transcripts\Domain;

/**
 * Domain entity representing a student's program enrollment history and key dates.
 */
class StudentProgramHistory
{
    private ?int $id;
    private int $personId;
    private string $programType;
    private string $concentration;
    private ?string $studentLevel;
    private string $startDate;
    private ?string $switchDate;
    private ?string $graduationDate;
    private string $status;
    private ?string $notes;

    public function __construct(array $data = [])
    {
        $this->id = isset($data['gibbonStudentProgramHistoryID']) ? (int)$data['gibbonStudentProgramHistoryID'] : null;
        $this->personId = (int)($data['gibbonPersonID'] ?? 0);
        $this->programType = $data['programType'] ?? 'BTh';
        $this->concentration = $data['concentration'] ?? 'General';
        $this->studentLevel = $data['studentLevel'] ?? null;
        $this->startDate = $data['startDate'] ?? date('Y-m-d');
        $this->switchDate = $data['switchDate'] ?? null;
        $this->graduationDate = $data['graduationDate'] ?? null;
        $this->status = $data['status'] ?? 'Active';
        $this->notes = $data['notes'] ?? null;
    }

    public function getId(): ?int { return $this->id; }
    public function getPersonId(): int { return $this->personId; }
    public function getProgramType(): string { return $this->programType; }
    public function getConcentration(): string { return $this->concentration; }
    public function getStudentLevel(): ?string { return $this->studentLevel; }
    public function getStartDate(): string { return $this->startDate; }
    public function getSwitchDate(): ?string { return $this->switchDate; }
    public function getGraduationDate(): ?string { return $this->graduationDate; }
    public function getStatus(): string { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }

    public function toArray(): array
    {
        return [
            'gibbonStudentProgramHistoryID' => $this->id,
            'gibbonPersonID' => $this->personId,
            'programType' => $this->programType,
            'concentration' => $this->concentration,
            'studentLevel' => $this->studentLevel,
            'startDate' => $this->startDate,
            'switchDate' => $this->switchDate,
            'graduationDate' => $this->graduationDate,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
