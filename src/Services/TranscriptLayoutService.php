<?php

namespace Gibbon\Module\Transcripts\Services;

/**
 * Builds Saint Photios-style term-grouped transcript layout data for PDF rendering.
 */
class TranscriptLayoutService
{
    public function buildOfficialLayout(array $transcriptData, array $student, ?array $activeProgram, array $options = []): array
    {
        $records = $transcriptData['records'] ?? [];
        $terms = [];

        foreach ($records as $record) {
            $rawTermName = $record['termName'] ?? $record['term'] ?? 'Unknown';
            $termKey = ($record['schoolYear'] ?? 'Unknown').'|'.$rawTermName;

            if (!isset($terms[$termKey])) {
                $terms[$termKey] = [
                    'schoolYear' => $record['schoolYear'] ?? '',
                    'term' => $record['formattedTerm'] ?? $rawTermName,
                    'courses' => [],
                    'totalCredits' => 0.0,
                    'weightedPoints' => 0.0,
                    'biblicalCredits' => 0.0,
                    'generalCredits' => 0.0,
                    'professionalCredits' => 0.0,
                ];
            }

            $credits = (float)($record['credits'] ?? 0);
            $gpaPoints = $record['gpaPoints'] ?? null;
            $gpaWeight = ($gpaPoints !== null && $credits > 0) ? $credits : 0.0;
            $creditSplit = $this->splitCreditsByConcentration($record, $activeProgram);

            $terms[$termKey]['courses'][] = [
                'courseName' => $this->formatCourseName($record),
                'courseNameHtml' => $this->formatHangingCourseNameHtml($this->formatCourseName($record)),
                'letterGrade' => $record['letterGrade'] ?? '-',
                'gpaPoints' => $gpaPoints,
                'biblicalCredits' => $creditSplit['biblical'],
                'generalCredits' => $creditSplit['general'],
                'professionalCredits' => $creditSplit['professional'],
            ];

            $terms[$termKey]['totalCredits'] += $credits;
            $terms[$termKey]['gpaUnits'] = ($terms[$termKey]['gpaUnits'] ?? 0) + $gpaWeight;
            $terms[$termKey]['biblicalCredits'] += $creditSplit['biblical'];
            $terms[$termKey]['generalCredits'] += $creditSplit['general'];
            $terms[$termKey]['professionalCredits'] += $creditSplit['professional'];

            if ($gpaWeight > 0) {
                $terms[$termKey]['weightedPoints'] += ($gpaPoints * $gpaWeight);
            }
        }

        $termList = array_values(array_map(function (array $term) {
            $gpaUnits = (float)($term['gpaUnits'] ?? 0);
            $term['termGPA'] = $gpaUnits > 0
                ? round($term['weightedPoints'] / $gpaUnits, 2)
                : null;

            unset($term['weightedPoints'], $term['gpaUnits']);

            return $term;
        }, $terms));

        return [
            'isOfficial' => (bool)($options['isOfficial'] ?? true),
            'unofficialNotice' => $options['unofficialNotice'] ?? '',
            'cumulativeGPA' => $transcriptData['cumulativeGPA'] ?? 0,
            'totalCredits' => $transcriptData['totalCredits'] ?? 0,
            'student' => [
                'name' => $student['displayName'] ?? '',
                'identifier' => $student['identifier'] ?? '',
                'address' => $student['address'] ?? '',
                'dateOfBirth' => $student['dateOfBirth'] ?? '',
                'degreeProgram' => $student['degreeProgram'] ?? '',
                'dateAdmitted' => $student['dateAdmitted'] ?? '',
                'dateGraduated' => $student['dateGraduated'] ?? '',
                'graduationBanner' => $student['graduationBanner'] ?? '',
            ],
            'registrar' => [
                'name' => ($options['isOfficial'] ?? true) ? ($options['registrarName'] ?? '') : '',
                'signaturePath' => ($options['isOfficial'] ?? true) ? ($options['registrarSignaturePath'] ?? '') : '',
            ],
            'terms' => $termList,
        ];
    }

    private function formatCourseName(array $record): string
    {
        $code = trim($record['courseCode'] ?? '');
        $external = trim($record['externalCourseCode'] ?? '');
        $name = trim($record['courseName'] ?? '');

        if ($code && $external && strcasecmp($code, $external) !== 0) {
            $code .= ' ('.$external.')';
        }

        if ($code && $name) {
            return $code.' '.$name;
        }

        return $code ?: $name;
    }

    private function formatHangingCourseNameHtml(string $courseName): string
    {
        $courseName = trim($courseName);
        if ($courseName === '') {
            return '';
        }

        $wrapped = wordwrap($courseName, 36, "\n", true);
        $break = strpos($wrapped, "\n");
        if ($break === false) {
            return htmlspecialchars($courseName);
        }

        $first = substr($wrapped, 0, $break);
        $rest = trim(str_replace("\n", ' ', substr($wrapped, $break + 1)));

        return htmlspecialchars($first).'<div class="course-hang">'.htmlspecialchars($rest).'</div>';
    }

    private function splitCreditsByConcentration(array $record, ?array $activeProgram): array
    {
        $credits = (float)($record['credits'] ?? 0);
        $concentration = $activeProgram['concentration'] ?? 'General';

        if ($concentration === 'Professional') {
            return ['biblical' => 0.0, 'general' => 0.0, 'professional' => $credits];
        }

        if ($concentration === 'Biblical') {
            return ['biblical' => $credits, 'general' => 0.0, 'professional' => 0.0];
        }

        return ['biblical' => 0.0, 'general' => $credits, 'professional' => 0.0];
    }
}
