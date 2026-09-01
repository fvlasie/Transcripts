<?php

namespace Gibbon\Module\Transcripts\Services;

use Gibbon\Module\Transcripts\Domain\TranscriptGateway;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;
use Gibbon\Module\Transcripts\Domain\CourseProgramGateway;
use Gibbon\Module\Transcripts\Domain\TranscriptRecord;
use Gibbon\Module\Transcripts\Domain\StudentProgramHistory;

class TranscriptService
{
    private TranscriptGateway $transcriptGateway;
    private StudentProgramGateway $programGateway;
    private CourseProgramGateway $courseProgramGateway;

    public function __construct(TranscriptGateway $transcriptGateway, StudentProgramGateway $programGateway, CourseProgramGateway $courseProgramGateway)
    {
        $this->transcriptGateway = $transcriptGateway;
        $this->programGateway = $programGateway;
        $this->courseProgramGateway = $courseProgramGateway;
    }

    public function generateStudentTranscript(int $gibbonPersonID, ?array $program = null): array
    {
        $rawRecords = $this->transcriptGateway->getStudentTranscriptRecords($gibbonPersonID);
        $programHistory = $this->programGateway->getAllProgramsByPerson($gibbonPersonID);
        $programType = $program['programType'] ?? '';
        $assignedCourseCodes = $programType !== ''
            ? $this->courseProgramGateway->getCourseCodesByProgramType($programType)
            : [];
        $courseLevel = !empty($program) ? getTranscriptsProgramCourseLevel($program) : null;

        $records = [];
        $totalCredits = 0.0;
        $totalWeightedPoints = 0.0;
        $gpaUnits = 0.0;

        foreach ($rawRecords as $data) {
            $record = new TranscriptRecord($data);

            if ($programType !== '') {
                $courseCode = $record->getCourseCode();
                if (!empty($assignedCourseCodes)) {
                    if (!in_array($courseCode, $assignedCourseCodes, true)) {
                        continue;
                    }
                } elseif ($courseLevel !== null && $record->getCourseLevel() !== $courseLevel) {
                    continue;
                }
            }

            $records[] = $record;

            $letter = strtoupper(trim((string)$record->getLetterGrade()));
            if ($record->getCredits() > 0 && $letter !== '' && $letter !== 'F') {
                $totalCredits += $record->getCredits();
            }

            $gpaWeight = $record->getGpaWeight();
            if ($gpaWeight > 0) {
                $totalWeightedPoints += ($record->getGpaPoints() * $gpaWeight);
                $gpaUnits += $gpaWeight;
            }
        }

        $cumulativeGPA = $gpaUnits > 0 ? round($totalWeightedPoints / $gpaUnits, 2) : 0.0;

        $formattedPrograms = array_map(function ($p) {
            return (new StudentProgramHistory($p))->toArray();
        }, $programHistory);

        return [
            'personID' => $gibbonPersonID,
            'program' => $program,
            'programHistory' => $formattedPrograms,
            'cumulativeGPA' => $cumulativeGPA,
            'totalCredits' => $totalCredits,
            'records' => array_map(function (TranscriptRecord $r) {
                return [
                    'schoolYear' => $r->getSchoolYearName(),
                    'term' => $r->getFormattedTermName(),
                    'termName' => $r->getTermName(),
                    'formattedTerm' => $r->getFormattedTermName(),
                    'secularAlias' => $r->getSecularAlias(),
                    'courseCode' => $r->getCourseCode(),
                    'externalCourseCode' => $r->getExternalCourseCode(),
                    'courseName' => $r->getCourseName(),
                    'courseLevel' => $r->getCourseLevel(),
                    'modeOfInstruction' => $r->getModeOfInstruction(),
                    'credits' => $r->getCredits(),
                    'letterGrade' => $r->getLetterGrade(),
                    'numericGrade' => $r->getNumericGrade(),
                    'gpaPoints' => $r->getGpaPoints(),
                    'gibbonCourseClassID' => $r->getCourseClassID(),
                    'gibbonReportingValueID' => $r->getReportingValueID(),
                    'gibbonReportingCriteriaID' => $r->getReportingCriteriaID(),
                    'gibbonReportingCycleID' => $r->getReportingCycleID(),
                    'gibbonSchoolYearTermID' => $r->getSchoolYearTermID(),
                    'gibbonSchoolYearID' => $r->getSchoolYearID(),
                    'gibbonScaleGradeID' => $r->getScaleGradeID(),
                ];
            }, $records),
        ];
    }
}
