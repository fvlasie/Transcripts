<?php

namespace Gibbon\Module\Transcripts\Services;

use Gibbon\Module\Transcripts\Gateway\TranscriptGateway;
use Gibbon\Module\Transcripts\Gateway\StudentProgramGateway;
use Gibbon\Module\Transcripts\Domain\TranscriptRecord;
use Gibbon\Module\Transcripts\Domain\StudentProgramHistory;

/**
 * Service to aggregate, compute, and render transcript data and summary GPAs.
 */
class TranscriptService
{
    private TranscriptGateway $transcriptGateway;
    private StudentProgramGateway $programGateway;

    public function __construct(TranscriptGateway $transcriptGateway, StudentProgramGateway $programGateway)
    {
        $this->transcriptGateway = $transcriptGateway;
        $this->programGateway = $programGateway;
    }

    public function generateStudentTranscript(int $gibbonPersonID, bool $useSecularAliases = false): array
    {
        $rawRecords = $this->transcriptGateway->getStudentTranscriptRecords($gibbonPersonID);
        $programHistory = $this->programGateway->getAllProgramsByPerson($gibbonPersonID);

        $records = [];
        $totalCredits = 0.0;
        $totalWeightedPoints = 0.0;

        foreach ($rawRecords as $data) {
            $record = new TranscriptRecord($data);
            $records[] = $record;

            if ($record->getGpaPoints() !== null && $record->getCredits() > 0) {
                $totalCredits += $record->getCredits();
                $totalWeightedPoints += ($record->getGpaPoints() * $record->getCredits());
            }
        }

        $cumulativeGPA = $totalCredits > 0 ? round($totalWeightedPoints / $totalCredits, 2) : 0.0;

        $formattedPrograms = array_map(function ($p) {
            return (new StudentProgramHistory($p))->toArray();
        }, $programHistory);

        return [
            'personID' => $gibbonPersonID,
            'useSecularAliases' => $useSecularAliases,
            'programHistory' => $formattedPrograms,
            'cumulativeGPA' => $cumulativeGPA,
            'totalCredits' => $totalCredits,
            'records' => array_map(function (TranscriptRecord $r) use ($useSecularAliases) {
                return [
                    'schoolYear' => $r->getSchoolYearName(),
                    'term' => $r->getTermName($useSecularAliases),
                    'courseCode' => $r->getCourseCode(),
                    'courseName' => $r->getCourseName(),
                    'courseLevel' => $r->getCourseLevel(),
                    'modeOfInstruction' => $r->getModeOfInstruction(),
                    'credits' => $r->getCredits(),
                    'letterGrade' => $r->getLetterGrade(),
                    'numericGrade' => $r->getNumericGrade(),
                    'gpaPoints' => $r->getGpaPoints()
                ];
            }, $records)
        ];
    }
}
