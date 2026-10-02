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
                    'concentrationCredits' => [],
                ];
            }

            $credits = (float)($record['credits'] ?? 0);
            $gpaPoints = $record['gpaPoints'] ?? null;
            $gpaWeight = ($gpaPoints !== null && $credits > 0) ? $credits : 0.0;
            $learningArea = trim((string)($record['learningArea'] ?? ''));

            $terms[$termKey]['courses'][] = [
                'courseName' => $this->formatCourseName($record),
                'courseNameHtml' => $this->formatHangingCourseNameHtml($this->formatCourseName($record)),
                'letterGrade' => $record['letterGrade'] ?? '-',
                'gpaPoints' => $gpaPoints,
                'creditsLabel' => $this->formatCredits($credits),
                'learningArea' => $learningArea,
            ];

            $terms[$termKey]['totalCredits'] += $credits;
            $terms[$termKey]['gpaUnits'] = ($terms[$termKey]['gpaUnits'] ?? 0) + $gpaWeight;
            if ($learningArea !== '') {
                $terms[$termKey]['concentrationCredits'][$learningArea] = ($terms[$termKey]['concentrationCredits'][$learningArea] ?? 0) + $credits;
            }

            if ($gpaWeight > 0) {
                $terms[$termKey]['weightedPoints'] += ($gpaPoints * $gpaWeight);
            }
        }

        $termList = array_values(array_map(function (array $term) {
            $gpaUnits = (float)($term['gpaUnits'] ?? 0);
            $term['termGPA'] = $gpaUnits > 0
                ? round($term['weightedPoints'] / $gpaUnits, 2)
                : null;
            $term['totalCreditsLabel'] = $this->formatCredits((float)$term['totalCredits']);

            $byArea = $term['concentrationCredits'] ?? [];
            ksort($byArea);
            $parts = [];
            foreach ($byArea as $name => $amount) {
                $parts[] = $name.' '.$this->formatCredits((float)$amount);
            }
            $term['concentrationSummary'] = implode(', ', $parts);

            unset($term['weightedPoints'], $term['gpaUnits'], $term['concentrationCredits']);

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

    private function formatCredits(float $credits): string
    {
        return abs($credits - round($credits)) < 0.001
            ? number_format($credits, 0)
            : number_format($credits, 2);
    }
}
