<?php

namespace Gibbon\Module\Transcripts\Services;

use Gibbon\Module\Transcripts\Domain\TranscriptGateway;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;
use Gibbon\Module\Transcripts\Domain\TranscriptRecord;
use Gibbon\Module\Transcripts\Domain\StudentProgramHistory;

class TranscriptService
{
    private TranscriptGateway $transcriptGateway;
    private StudentProgramGateway $programGateway;

    public function __construct(TranscriptGateway $transcriptGateway, StudentProgramGateway $programGateway)
    {
        $this->transcriptGateway = $transcriptGateway;
        $this->programGateway = $programGateway;
    }

    /**
     * Builds the transcript from every grade the student has, whatever their status or year.
     * The program only labels the transcript; it does not filter courses.
     */
    public function generateStudentTranscript(int $gibbonPersonID, ?array $program = null): array
    {
        $rawRecords = $this->latestPerClassAndTerm($this->transcriptGateway->getStudentGradeRecords($gibbonPersonID));
        $programHistory = $this->programGateway->getAllProgramsByPerson($gibbonPersonID);

        $records = [];
        $totalCredits = 0.0;
        $totalWeightedPoints = 0.0;
        $gpaUnits = 0.0;

        foreach ($rawRecords as $data) {
            $record = new TranscriptRecord($data);
            $records[] = [$record, $data];

            if ($record->isCreditEarned()) {
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
            'records' => array_map(function (array $pair) {
                [$r, $data] = $pair;

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
                    'gibbonPersonID' => $r->getPersonId(),
                    'gibbonCourseID' => $r->getCourseID(),
                    'gibbonCourseClassID' => $r->getCourseClassID(),
                    'gibbonReportingValueID' => $r->getReportingValueID(),
                    'gibbonReportingCriteriaID' => $r->getReportingCriteriaID(),
                    'gibbonReportingCycleID' => $r->getReportingCycleID(),
                    'gibbonSchoolYearTermID' => $r->getSchoolYearTermID(),
                    'gibbonSchoolYearID' => $r->getSchoolYearID(),
                    'gibbonScaleGradeID' => $r->getScaleGradeID(),
                    'gibbonScaleID' => (int)($data['gibbonScaleID'] ?? 0),
                    'isPassFail' => $r->isPassFail(),
                    'cycleName' => $data['cycleName'] ?? '',
                    'timestampModified' => $data['timestampModified'] ?? null,
                    'modifiedTitle' => $data['modifiedTitle'] ?? '',
                    'modifiedPreferredName' => $data['modifiedPreferredName'] ?? '',
                    'modifiedSurname' => $data['modifiedSurname'] ?? '',
                    'hiddenDuplicates' => (int)($data['hiddenDuplicates'] ?? 0),
                ];
            }, $records),
        ];
    }

    /**
     * Keeps one grade per class and term. Rows arrive ordered so the latest cycle is last.
     */
    private function latestPerClassAndTerm(array $rows): array
    {
        $latest = [];
        $order = [];
        $counts = [];

        foreach ($rows as $row) {
            $termKey = !empty($row['gibbonSchoolYearTermID'])
                ? 'term'.$row['gibbonSchoolYearTermID']
                : 'cycle'.($row['gibbonReportingCycleID'] ?? 0);
            $key = ($row['gibbonCourseClassID'] ?? 0).'|'.$termKey;

            if (!isset($latest[$key])) {
                $order[] = $key;
            }
            $latest[$key] = $row;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $result = [];
        foreach ($order as $key) {
            $row = $latest[$key];
            $row['hiddenDuplicates'] = $counts[$key] - 1;
            $result[] = $row;
        }

        return $result;
    }
}
