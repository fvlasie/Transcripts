<?php

namespace Gibbon\Module\Transcripts\Domain;

/**
 * Domain entity representing a grade/transcript record item.
 */
class TranscriptRecord
{
    private int $personId;
    private string $schoolYearName;
    private string $termName;
    private ?string $termSecularAlias;
    private string $courseName;
    private string $courseCode;
    private string $courseLevel;
    private string $modeOfInstruction;
    private float $credits;
    private ?string $letterGrade;
    private ?float $numericGrade;
    private ?float $gpaPoints;

    public function __construct(array $data)
    {
        $this->personId = (int)($data['gibbonPersonID'] ?? 0);
        $this->schoolYearName = $data['schoolYearName'] ?? '';
        $this->termName = $data['termName'] ?? '';
        $this->termSecularAlias = $data['secularAlias'] ?? null;
        $this->courseName = $data['courseName'] ?? '';
        $this->courseCode = $data['courseCode'] ?? '';
        $this->courseLevel = $data['courseLevel'] ?? 'BTh';
        $this->modeOfInstruction = $data['modeOfInstruction'] ?? 'In-person';
        $this->credits = (float)($data['credits'] ?? 0.0);
        $this->letterGrade = $data['letterGrade'] ?? null;
        $this->numericGrade = isset($data['numericGrade']) ? (float)$data['numericGrade'] : null;
        $this->gpaPoints = isset($data['gpaPoints']) ? (float)$data['gpaPoints'] : $this->calculateGpaPoints();
    }

    private function calculateGpaPoints(): ?float
    {
        if ($this->letterGrade === null) {
            return null;
        }

        $gradeMap = [
            'A+' => 4.0, 'A' => 4.0, 'A-' => 3.7,
            'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
            'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
            'D+' => 1.3, 'D' => 1.0, 'F' => 0.0
        ];

        return $gradeMap[strtoupper(trim($this->letterGrade))] ?? null;
    }

    public function getPersonId(): int { return $this->personId; }
    public function getSchoolYearName(): string { return $this->schoolYearName; }
    public function getTermName(bool $useSecularAlias = false): string
    {
        if ($useSecularAlias && !empty($this->termSecularAlias)) {
            return $this->termSecularAlias;
        }
        return $this->termName;
    }
    public function getCourseName(): string { return $this->courseName; }
    public function getCourseCode(): string { return $this->courseCode; }
    public function getCourseLevel(): string { return $this->courseLevel; }
    public function getModeOfInstruction(): string { return $this->modeOfInstruction; }
    public function getCredits(): float { return $this->credits; }
    public function getLetterGrade(): ?string { return $this->letterGrade; }
    public function getNumericGrade(): ?float { return $this->numericGrade; }
    public function getGpaPoints(): ?float { return $this->gpaPoints; }
}
