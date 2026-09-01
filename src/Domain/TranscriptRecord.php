<?php

namespace Gibbon\Module\Transcripts\Domain;

class TranscriptRecord
{
    private int $personId;
    private string $schoolYearName;
    private string $termName;
    private ?string $secularAlias;
    private string $courseName;
    private string $courseCode;
    private ?string $externalCourseCode;
    private string $courseLevel;
    private string $modeOfInstruction;
    private float $credits;
    private ?string $letterGrade;
    private ?float $numericGrade;
    private ?float $gpaPoints;
    private int $courseClassID;
    private int $reportingValueID;
    private int $reportingCriteriaID;
    private int $reportingCycleID;
    private int $schoolYearTermID;
    private int $schoolYearID;
    private int $scaleGradeID;
    private int $courseID;

    public function __construct(array $data)
    {
        $this->personId = (int)($data['gibbonPersonID'] ?? 0);
        $this->schoolYearName = $data['schoolYearName'] ?? '';
        $this->termName = $data['termName'] ?? '';
        $this->secularAlias = $data['secularAlias'] ?? null;
        $this->courseName = $data['courseName'] ?? '';
        $this->courseCode = $data['courseCode'] ?? '';
        $this->externalCourseCode = trim((string)($data['externalCourseCode'] ?? '')) ?: null;
        $this->courseLevel = $data['courseLevel'] ?? 'BTh';
        $this->modeOfInstruction = $data['modeOfInstruction'] ?? 'In-person';
        $this->credits = (float)($data['credits'] ?? 0.0);
        $letterGrade = trim((string)($data['letterGrade'] ?? ''));
        $this->letterGrade = $letterGrade !== '' ? $letterGrade : null;
        $this->numericGrade = is_numeric($data['numericGrade'] ?? null) ? (float)$data['numericGrade'] : null;
        $this->gpaPoints = is_numeric($data['gpaPoints'] ?? null) ? (float)$data['gpaPoints'] : $this->calculateGpaPoints();
        $this->courseClassID = (int)($data['gibbonCourseClassID'] ?? 0);
        $this->reportingValueID = (int)($data['gibbonReportingValueID'] ?? 0);
        $this->reportingCriteriaID = (int)($data['gibbonReportingCriteriaID'] ?? 0);
        $this->reportingCycleID = (int)($data['gibbonReportingCycleID'] ?? 0);
        $this->schoolYearTermID = (int)($data['gibbonSchoolYearTermID'] ?? 0);
        $this->schoolYearID = (int)($data['gibbonSchoolYearID'] ?? 0);
        $this->scaleGradeID = (int)($data['gibbonScaleGradeID'] ?? 0);
        $this->courseID = (int)($data['gibbonCourseID'] ?? 0);
    }

    private function calculateGpaPoints(): ?float
    {
        $gradeMap = [
            'A+' => 4.0, 'A' => 4.0, 'A-' => 3.7,
            'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
            'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
            'D+' => 1.3, 'D' => 1.0, 'F' => 0.0,
        ];

        $normalized = strtoupper(str_replace([' ', '−', '–'], ['', '-', '-'], trim((string)$this->letterGrade)));
        if ($normalized !== '' && isset($gradeMap[$normalized])) {
            return $gradeMap[$normalized];
        }

        if ($normalized !== '' && preg_match('/^([A-D][+-]?|F)\b/', $normalized, $matches) && isset($gradeMap[$matches[1]])) {
            return $gradeMap[$matches[1]];
        }

        if ($this->numericGrade !== null && $this->numericGrade >= 0 && $this->numericGrade <= 4.3) {
            return round($this->numericGrade, 2);
        }

        return $gradeMap[$normalized] ?? null;
    }

    public function getGpaWeight(): float
    {
        if ($this->gpaPoints === null) {
            return 0.0;
        }

        return $this->credits > 0 ? $this->credits : 0.0;
    }

    public function getPersonId(): int { return $this->personId; }
    public function getSchoolYearName(): string { return $this->schoolYearName; }

    public function getTermName(): string
    {
        return $this->termName;
    }

    public function getSecularAlias(): ?string
    {
        return $this->secularAlias;
    }

    public function getFormattedTermName(): string
    {
        $alias = $this->resolveSecularAliasLabel();

        if ($alias === null) {
            return $this->termName;
        }

        return $this->termName.' ('.$alias.')';
    }

    private function resolveSecularAliasLabel(): ?string
    {
        if (!empty($this->secularAlias)) {
            return $this->normalizeSecularAlias($this->secularAlias);
        }

        $defaults = [
            'Nativity' => 'Fall',
            'Pascha' => 'Spring',
            'Pentecost' => 'Summer',
        ];

        return $defaults[$this->termName] ?? null;
    }

    private function normalizeSecularAlias(string $alias): string
    {
        return preg_replace('/\s+(Term|Semester)$/i', '', trim($alias)) ?: trim($alias);
    }

    public function getCourseName(): string { return $this->courseName; }
    public function getCourseCode(): string { return $this->courseCode; }
    public function getExternalCourseCode(): ?string { return $this->externalCourseCode; }
    public function getCourseLevel(): string { return $this->courseLevel; }
    public function getModeOfInstruction(): string { return $this->modeOfInstruction; }
    public function getCredits(): float { return $this->credits; }
    public function getLetterGrade(): ?string { return $this->letterGrade; }
    public function getNumericGrade(): ?float { return $this->numericGrade; }
    public function getGpaPoints(): ?float { return $this->gpaPoints; }
    public function getCourseClassID(): int { return $this->courseClassID; }
    public function getReportingValueID(): int { return $this->reportingValueID; }
    public function getReportingCriteriaID(): int { return $this->reportingCriteriaID; }
    public function getReportingCycleID(): int { return $this->reportingCycleID; }
    public function getSchoolYearTermID(): int { return $this->schoolYearTermID; }
    public function getSchoolYearID(): int { return $this->schoolYearID; }
    public function getScaleGradeID(): int { return $this->scaleGradeID; }
    public function getCourseID(): int { return $this->courseID; }
}
